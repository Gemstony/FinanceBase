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
        abort(404);
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
