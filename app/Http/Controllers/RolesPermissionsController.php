<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shop;
use App\Models\SubShop;

class RolesPermissionsController extends Controller
{
        /**
     * Assign default permissions to predefined roles.
     */
    public function givePermission()
    {
        try {
            $superAdmin_role = Role::where("name","Super Admin")->first();
            $admin_role = Role::where('name', 'admin')->first();
            $manager_role = Role::where('name', 'manager')->first();
            $staff_role = Role::where('name', 'staff')->first();


            if ($superAdmin_role) {
                $superAdmin_permissions = Permission::all();
                $superAdmin_role->givePermissionTo($superAdmin_permissions);
            }

            if ($admin_role) {
                $admin_role->givePermissionTo([
                    'manage single main shop',
                    'manage main shop subshops',
                   
                ]);
            }

            if ($manager_role) {
                $manager_role->givePermissionTo([
                    'manage main shop subshops',
                   
                ]);
            }

             if ($staff_role) {
                $staff_role->givePermissionTo([
                    'manage specific subshop',
                   
                ]);
            }


            return redirect()->route('dashboard')->with('success', 'Permissions assigned successfully.');
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to assign permissions: ' . $e->getMessage());
        }
    }
  public function update()
    {
        $role = Role::where('name','Super Admin')->first();
        $user = User::where('name', 'James Mwita')
                    ->where('email', 'jmwita424@gmail.com')
                    ->first();

        if (!$user) {
            return view('dashboard')->with('error', 'User not found');
        }

        $user->assignRole($role);

        $role2 = Role::where('name', 'Super Admin')->first();

        // Assign ALL permissions in the system
        $role2->syncPermissions(Permission::all());

        
        return redirect()->route('dashboard')->with('success', 'Super Admin role assigned to user successfully.');
    }

    public function index(Request $request)
    {
        // Check if user has permission to manage roles/permissions
        $auth = $request->user();
        if (!$auth || !$this->isOwnerOrSuperAdmin($auth)) {
            abort(403, 'Unauthorized to access roles and permissions management.');
        }

        $search = request('search');
        $permissionsQuery = Permission::query();

        // If Super Admin, load all roles; if Owner, restrict to shopkeeper role and owner's shop roles
        $shops = collect();
        if ($this->isSuperAdmin($auth)) {
            $roles = Role::all();
            $shopIds = $roles->pluck('shop_id')->filter()->unique();
            if ($shopIds->isNotEmpty()) {
                $shops = Shop::whereIn('id', $shopIds)->pluck('name', 'id');
            }
        } else {
            // For owners, show global roles, shopkeeper, and their shop roles
            $shopId = $this->getCurrentShopId($request);
            if ($shopId) {
                $roles = Role::where(function($q) use ($shopId) {
                    $q->whereNull('shop_id')->orWhere('shop_id', $shopId);
                })->get();
            } else {
                $roles = collect();
            }
        }

        if ($search) {
            $permissionsQuery->where('name', 'like', '%' . $search . '%');
        }

        if ($this->isSuperAdmin($auth)) {
            $permissions = $permissionsQuery->get();
        } else {
            // Only permissions that the owner currently has
            $ownerPermIds = $auth->getAllPermissions()->pluck('id');
            $permissions = Permission::whereIn('id', $ownerPermIds)
                ->when($search, function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })
                ->get(); // Ensure we get a collection, not a query builder
        }
        $groupedPermissions = $permissions->groupBy(function($permission) {
            $parts = explode('_', $permission->name);
            return strtolower($parts[0]);
        });

        // For shopkeeper role, get permissions from current shop's shopkeeper users
        $shopkeeperPermissions = collect();
        $shopkeeperRole = Role::where('name', 'shopkeeper')->first();
        $shopkeeperUsersCount = 0;
        if ($shopkeeperRole) {
            $shopkeeperUsers = $this->getShopkeeperUsers($request);
            $shopkeeperUsersCount = $shopkeeperUsers->count();
            if ($shopkeeperUsers->isNotEmpty()) {
                // Intersection of permissions across all shopkeeper users in this shop
                $commonIds = null;
                foreach ($shopkeeperUsers as $u) {
                    $ids = $u->getAllPermissions()->pluck('id');
                    $commonIds = is_null($commonIds) ? $ids : $commonIds->intersect($ids);
                    if ($commonIds->isEmpty()) break;
                }
                $shopkeeperPermissions = $commonIds && $commonIds->isNotEmpty()
                    ? Permission::whereIn('id', $commonIds)->get()
                    : collect();
            }
        }

        return view('settings.settings', compact('roles', 'permissions', 'groupedPermissions', 'search', 'shopkeeperPermissions', 'shopkeeperUsersCount', 'shops'));
    }

    public function createRole(Request $request)
    {
        // Allow Super Admin or Owner to create roles
        $auth = $request->user();
        if (!$auth || !$this->isOwnerOrSuperAdmin($auth)) {
            return redirect()->back()->with('error', 'Unauthorized to create roles.');
        }

        // For owners, require shop context; for Super Admin, allow choosing global or current shop
        $shopId = null;
        if (!$this->isSuperAdmin($auth)) {
            $shopId = $this->getCurrentShopId($request);
            if (!$shopId) {
                return redirect()->back()->with('error', 'Please choose a shop first.');
            }
        } else {
            // Super Admin: if not global, use current shop
            if (!$request->has('is_global') || !$request->is_global) {
                $shopId = $this->getCurrentShopId($request);
                if (!$shopId) {
                    return redirect()->back()->with('error', 'Please choose a shop first or make it global.');
                }
            }
        }

        $request->validate([
            'name' => 'required|string',
        ]);

        // Check uniqueness: for global roles (Super Admin), unique across all; for shop roles, unique within shop
        $query = Role::where('name', $request->name);
        if ($shopId) {
            $query->where('shop_id', $shopId);
        } else {
            $query->whereNull('shop_id');
        }

        if ($query->exists()) {
            return redirect()->back()->with('error', 'Role name already exists in this shop.');
        }

        Role::create([
            'name' => $request->name,
            'shop_id' => $shopId,
        ]);

        return redirect()->back()->with('success', 'Role created successfully.');
    }

    public function createPermission(Request $request)
    {
        // Only Super Admin can create permissions
        $auth = $request->user();
        if (!$auth || !$this->isSuperAdmin($auth)) {
            return redirect()->back()->with('error', 'Only Super Admin can create permissions.');
        }
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        Permission::create(['name' => $request->name]);

        return redirect()->back()->with('success', 'Permission created successfully.');
    }

    public function assignPermissionsToRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::find($request->role_id);

        // Check if user has permission to modify roles
        $auth = $request->user();
        if (!$auth || !$this->isOwnerOrSuperAdmin($auth)) {
            return redirect()->back()->with('error', 'Unauthorized to modify permissions.');
        }

        // Owner can assign to 'shopkeeper' and their own shop roles, only from their own permissions
        if (!$this->isSuperAdmin($auth)) {
            $shopId = $this->getCurrentShopId($request);
            if (!$shopId) {
                return redirect()->back()->with('error', 'Please choose a shop first.');
            }

            // Check if the role belongs to the owner's shop, is global, or is shopkeeper
            if ($role->shop_id != $shopId && $role->shop_id !== null && $role->name !== 'shopkeeper') {
                return redirect()->back()->with('error', 'You can only assign permissions to roles in your shop, global roles, or shopkeeper.');
            }

            if ($role->name === 'shopkeeper') {
                $shopkeeperUsers = $this->getShopkeeperUsers($request);
                $requested = collect($request->permissions ?? []);
                $allowedIds = $auth->getAllPermissions()->pluck('id');
                $filteredIds = $requested->intersect($allowedIds);
                $permissions = Permission::whereIn('id', $filteredIds)->get();

                // Assign permissions directly to each shopkeeper user in current shop's subshops
                foreach ($shopkeeperUsers as $user) {
                    $user->syncPermissions($permissions);
                }

                // CRITICAL: NEVER assign permissions to the 'shopkeeper' role itself
                // This ensures shopkeeper permissions are shop-specific, not global
                $role->syncPermissions([]);

                return redirect()->back()->with('success', 'Permissions assigned to shopkeeper users in your shop successfully.');
            } else {
                // For custom shop roles, assign to the role itself, but only from owner's permissions
                $requested = collect($request->permissions ?? []);
                $allowedIds = $auth->getAllPermissions()->pluck('id');
                $filteredIds = $requested->intersect($allowedIds);
                $permissions = Permission::whereIn('id', $filteredIds)->get();

                $role->syncPermissions($permissions);

                return redirect()->back()->with('success', 'Permissions assigned to role successfully.');
            }
        }

        // Super Admin: assign normally (role-based)
        $permissions = Permission::whereIn('id', $request->permissions ?? [])->get();
        $role->syncPermissions($permissions);

        return redirect()->back()->with('success', 'Permissions assigned successfully.');
    }

    public function editRole(Request $request, $id)
    {
        // Allow Super Admin or Owner to edit roles
        $auth = $request->user();
        if (!$auth || !$this->isOwnerOrSuperAdmin($auth)) {
            return redirect()->back()->with('error', 'Unauthorized to edit roles.');
        }

        $role = Role::findOrFail($id);

        // Owners can only edit their shop roles
        if (!$this->isSuperAdmin($auth)) {
            $shopId = $this->getCurrentShopId($request);
            if ($role->shop_id != $shopId) {
                return redirect()->back()->with('error', 'You can only edit roles in your shop.');
            }
        }

        $request->validate([
            'name' => 'required|string',
        ]);

        // For Super Admin, allow changing scope
        $newShopId = $role->shop_id;
        if ($this->isSuperAdmin($auth)) {
            if ($request->has('is_global') && $request->is_global) {
                $newShopId = null;
            } else {
                $newShopId = $this->getCurrentShopId($request);
                if (!$newShopId) {
                    return redirect()->back()->with('error', 'Please choose a shop first.');
                }
            }
        }

        // Check uniqueness within the new scope
        $query = Role::where('name', $request->name)->where('id', '!=', $id);
        if ($newShopId) {
            $query->where('shop_id', $newShopId);
        } else {
            $query->whereNull('shop_id');
        }

        if ($query->exists()) {
            return redirect()->back()->with('error', 'Role name already exists in this scope.');
        }

        $role->name = $request->name;
        $role->shop_id = $newShopId;
        $role->save();

        return redirect()->back()->with('success', 'Role updated successfully.');
    }

    public function deleteRole($id)
    {
        // Allow Super Admin or Owner to delete roles
        $auth = request()->user();
        if (!$auth || !$this->isOwnerOrSuperAdmin($auth)) {
            return redirect()->back()->with('error', 'Unauthorized to delete roles.');
        }

        $role = Role::findOrFail($id);

        // Owners can only delete their shop roles, not shopkeeper
        if (!$this->isSuperAdmin($auth)) {
            if ($role->name === 'shopkeeper') {
                return redirect()->back()->with('error', 'Cannot delete the shopkeeper role.');
            }
            $shopId = $this->getCurrentShopId(request());
            if ($role->shop_id != $shopId) {
                return redirect()->back()->with('error', 'You can only delete roles in your shop.');
            }
        }

        $role->delete();

        return redirect()->back()->with('success', 'Role deleted successfully.');
    }

    public function editPermission(Request $request, $id)
    {
        // Only Super Admin can edit permissions
        $auth = $request->user();
        if (!$auth || !$this->isSuperAdmin($auth)) {
            return redirect()->back()->with('error', 'Only Super Admin can edit permissions.');
        }
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $id,
        ]);

        $permission = Permission::findOrFail($id);
        $permission->name = $request->name;
        $permission->save();

        return redirect()->back()->with('success', 'Permission updated successfully.');
    }

    public function deletePermission($id)
    {
        // Only Super Admin can delete permissions
        $auth = request()->user();
        if (!$auth || !$this->isSuperAdmin($auth)) {
            return redirect()->back()->with('error', 'Only Super Admin can delete permissions.');
        }
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return redirect()->back()->with('success', 'Permission deleted successfully.');
    }

    /**
     * Get the current shop ID for the authenticated user
     */
    private function getCurrentShopId(Request $request): ?int
    {
        // Prefer current subshop context if set
        $subshopId = (int) $request->session()->get('subshop_id');
        if ($subshopId) {
            $sub = SubShop::select('shop_id')->find($subshopId);
            if ($sub && $sub->shop_id) {
                return (int) $sub->shop_id;
            }
        }

        // Fallback: owner's main shop
        $user = $request->user();
        if ($user && $user->shop) {
            return (int) $user->shop->id;
        }

        return null;
    }

    /**
     * Get shopkeeper users for the current user's shop
     */
    private function getShopkeeperUsers(Request $request)
    {
        $shopId = $this->getCurrentShopId($request);
        if (!$shopId) return collect();

        return User::role('shopkeeper')
            ->whereHas('subshops', function($q) use ($shopId) {
                $q->where('sub_shops.shop_id', $shopId);
            })
            ->get();
    }

    /**
     * Check if user is owner or super admin
     */
    private function isOwnerOrSuperAdmin(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('Super Admin') ||
               Shop::where('user_id', $user->id)->exists();
    }

    /**
     * Check if user is Super Admin
     */
    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
}
