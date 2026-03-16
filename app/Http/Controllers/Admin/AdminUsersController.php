<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shop;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Super Admin']);
    }

    /**
     * Display all users with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'shop', 'subShops', 'authentications' => function($query) {
            $query->where('login_successful', true)->orderBy('login_at', 'desc')->limit(1);
        }]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('shop')) {
            $query->whereHas('shop', function($q) use ($request) {
                $q->where('id', $request->shop);
            })->orWhereHas('subShops', function($q) use ($request) {
                $q->where('shop_id', $request->shop);
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'unassigned') {
                $query->whereDoesntHave('shop')->whereDoesntHave('subShops');
            } else {
                $query->whereHas('shop', function($q) use ($request) {
                    $q->where('is_active', $request->status === 'active');
                })->orWhereHas('subShops', function($q) use ($request) {
                    $q->where('is_active', $request->status === 'active');
                });
            }
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(25);
        $shops = Shop::with('subShops')->get();

        return view('shops_management.admin_users_management', compact('users', 'shops'));
    }

    /**
     * Store a new user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'subshop_id' => ['nullable', 'exists:sub_shops,id'],
        ]);

        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Handle shop assignment
            if ($request->filled('shop_id')) {
                if ($request->role === 'owner') {
                    // Assign as shop owner
                    $shop = Shop::find($request->shop_id);
                    if ($shop) {
                        $shop->update(['owner_id' => $user->id]);
                    }
                } else {
                    // Assign to subshop
                    if ($request->filled('subshop_id')) {
                        $subshop = SubShop::find($request->subshop_id);
                        if ($subshop) {
                            $user->subShops()->attach($subshop->id, [
                                'role' => $request->role,
                                'is_active' => true,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return redirect()->route('superadmin.users.index')
                ->with('success', 'User created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to create user. Please try again.')
                ->withInput();
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:25'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'subshop_id' => ['nullable', 'exists:sub_shops,id'],
        ]);

        try {
            DB::beginTransaction();

            // Update user info
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
            ]);

            // Update role
            $user->syncRoles([$request->role]);

            // Handle shop assignment changes
            $user->subShops()->detach(); // Remove existing subshop assignments
            
            // Remove shop ownership if user was an owner
            if ($user->shop) {
                $user->shop->update(['owner_id' => null]);
            }

            if ($request->filled('shop_id')) {
                if ($request->role === 'owner') {
                    // Assign as shop owner
                    $shop = Shop::find($request->shop_id);
                    if ($shop) {
                        $shop->update(['owner_id' => $user->id]);
                    }
                } else {
                    // For non-owner roles, assign to subshop if provided
                    if ($request->filled('subshop_id')) {
                        $subshop = SubShop::find($request->subshop_id);
                        if ($subshop) {
                            // Verify the subshop belongs to the selected shop
                            if ($subshop->shop_id == $request->shop_id) {
                                $user->subShops()->attach($subshop->id, [
                                    'role' => $request->role,
                                    'is_active' => true,
                                ]);
                            } else {
                                throw new \Exception('Selected subshop does not belong to the selected shop.');
                            }
                        }
                    } else {
                        // If no subshop selected for non-owner, we don't assign to any subshop
                        // The user will have shop_id assignment through the shop relationship
                        // This is handled by the general shop assignment logic
                    }
                }
            }

            DB::commit();

            return redirect()->route('superadmin.users.index')
                ->with('success', 'User updated successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error updating user: ' . json_encode($e->errors()));
            
            return redirect()->back()
                ->withErrors($e->errors())
                ->with('error', 'Validation failed. Please check your input.')
                ->withInput();
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating user: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Failed to update user: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        if ($user->email === auth()->user()->email) {
            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        try {
            DB::beginTransaction();

            // Remove shop ownership
            if ($user->shop) {
                $user->shop->update(['owner_id' => null]);
            }

            // Remove subshop assignments
            $user->subShops()->detach();

            // Delete user
            $user->delete();

            DB::commit();

            return redirect()->route('superadmin.users.index')
                ->with('success', 'User deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to delete user. Please try again.');
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return redirect()->route('superadmin.users.index')
                ->with('success', 'Password reset successfully!');

        } catch (\Exception $e) {
            Log::error('Error resetting password: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to reset password. Please try again.');
        }
    }

    /**
     * Bulk actions on users
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'users' => ['required', 'array'],
            'users.*' => ['exists:users,id'],
            'action' => ['required', 'string', 'in:activate,deactivate,delete,assign_role,remove_assignment'],
            'role' => ['required_if:action,assign_role', 'string', 'exists:roles,name'],
        ]);

        try {
            DB::beginTransaction();

            $users = User::whereIn('id', $request->users)->get();
            $count = 0;

            foreach ($users as $user) {
                // Skip if trying to delete current user
                if ($request->action === 'delete' && $user->email === auth()->user()->email) {
                    continue;
                }

                switch ($request->action) {
                    case 'activate':
                        $this->activateUser($user);
                        $count++;
                        break;

                    case 'deactivate':
                        $this->deactivateUser($user);
                        $count++;
                        break;

                    case 'delete':
                        $this->deleteUser($user);
                        $count++;
                        break;

                    case 'assign_role':
                        $user->syncRoles([$request->role]);
                        $count++;
                        break;

                    case 'remove_assignment':
                        $this->removeAssignments($user);
                        $count++;
                        break;
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Action performed on {$count} users successfully!"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error performing bulk action: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to perform bulk action. Please try again.'
            ], 500);
        }
    }

    /**
     * Export users to CSV
     */
    public function export(Request $request)
    {
        $query = User::with(['roles', 'shop', 'subShops', 'authentications' => function($query) {
            $query->where('login_successful', true)->orderBy('login_at', 'desc')->limit(1);
        }]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        $filename = "users_export_" . date('Y-m-d_H-i-s') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($file, [
                'ID', 'Name', 'Email', 'Phone', 'Role', 'Shop', 'Subshops', 
                'Status', 'Joined', 'Last Login', 'Email Verified'
            ]);

            // CSV Data
            foreach ($users as $user) {
                $role = $user->roles->first()?->name ?? 'No Role';
                $shop = $user->shop?->name ?? 'Unassigned';
                $subshops = $user->subShops->pluck('name')->implode(', ') ?: 'None';
                $status = 'Unknown';
                
                if ($user->shop) {
                    $status = $user->shop->is_active ? 'Active' : 'Inactive';
                } elseif ($user->subShops->count() > 0) {
                    $activeSubshops = $user->subShops->where('is_active', true);
                    $status = $activeSubshops->count() > 0 ? 'Active' : 'Inactive';
                } else {
                    $status = 'Unassigned';
                }

                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone_number ?? '',
                    $role,
                    $shop,
                    $subshops,
                    $status,
                    $user->created_at->format('Y-m-d H:i:s'),
                    $user->authentications && $user->authentications->count() > 0 
                        ? $user->authentications->first()->login_at->format('Y-m-d H:i:s') 
                        : 'Never',
                    $user->email_verified_at ? 'Yes' : 'No'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper methods
     */
    private function activateUser(User $user)
    {
        if ($user->shop) {
            $user->shop->update(['is_active' => true]);
        }
        
        $user->subShops()->updateExistingPivot($user->subShops->pluck('id'), ['is_active' => true]);
    }

    private function deactivateUser(User $user)
    {
        if ($user->shop) {
            $user->shop->update(['is_active' => false]);
        }
        
        $user->subShops()->updateExistingPivot($user->subShops->pluck('id'), ['is_active' => false]);
    }

    private function deleteUser(User $user)
    {
        // Remove shop ownership
        if ($user->shop) {
            $user->shop->update(['owner_id' => null]);
        }

        // Remove subshop assignments
        $user->subShops()->detach();

        // Delete user
        $user->delete();
    }

    private function removeAssignments(User $user)
    {
        if ($user->shop) {
            $user->shop->update(['owner_id' => null]);
        }
        
        $user->subShops()->detach();
    }
}
