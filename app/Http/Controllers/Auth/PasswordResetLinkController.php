<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{10,12}$/'],
        ]);

        // Find user by both email and phone number to ensure they match
        $user = \App\Models\User::where('email', $request->email)
                                ->where('phone_number', $request->phone_number)
                                ->first();

        if (!$user) {
            return back()->withInput($request->only('email', 'phone_number'))
                        ->withErrors(['general' => 'No account found with this email and phone number combination.']);
        }

        // Generate a random simple password (8 characters: 4 letters + 4 numbers)
        $newPassword = $this->generateSimplePassword();

        try {
            // Update user password
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($newPassword),
            ]);

            // Send SMS with new password
            $smsService = new \App\Services\SmsService();
            $smsService->sendForgotPasswordSms($user->phone_number, $newPassword);

            return back()->with('status', 'A new password has been sent to your phone number.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Password reset failed for email: ' . $request->email . ', phone: ' . $request->phone_number . ' - ' . $e->getMessage());
            return back()->withInput($request->only('email', 'phone_number'))
                        ->withErrors(['general' => 'Failed to reset password. Please try again.']);
        }
    }

    /**
     * Generate a simple random password (4 letters + 4 numbers)
     */
    private function generateSimplePassword(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';

        $password = '';
        // Add 4 random letters
        for ($i = 0; $i < 4; $i++) {
            $password .= $letters[rand(0, strlen($letters) - 1)];
        }
        // Add 4 random numbers
        for ($i = 0; $i < 4; $i++) {
            $password .= $numbers[rand(0, strlen($numbers) - 1)];
        }

        return $password;
    }
}
