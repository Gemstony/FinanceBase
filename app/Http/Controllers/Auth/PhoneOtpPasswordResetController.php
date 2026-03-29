<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class PhoneOtpPasswordResetController extends Controller
{
    private const SESSION_KEY = 'password_reset_otp';
    private const OTP_EXPIRY_MINUTES = 5;
    private const MAX_VERIFY_ATTEMPTS = 3;

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request, SmsService $smsService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'phone_number' => ['required', 'string', 'max:30'],
        ]);

        $phoneCandidates = $this->phoneCandidates($validated['phone_number']);

        $user = User::query()
            ->where('email', $validated['email'])
            ->whereIn('phone_number', $phoneCandidates)
            ->first();

        if (!$user) {
            return back()
                ->withInput($request->only('email', 'phone_number'))
                ->withErrors(['general' => 'No account found with this email and phone number combination.']);
        }

        $phone = (string) $user->phone_number;

        DB::table('password_reset_otps')
            ->where('email', $validated['email'])
            ->where('phone', $phone)
            ->delete();

        $otpPlain = (string) random_int(100000, 999999);

        DB::table('password_reset_otps')->insert([
            'email' => $validated['email'],
            'phone' => $phone,
            'otp' => Hash::make($otpPlain),
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'attempts' => 0,
            'created_at' => now(),
        ]);

          // Send OTP via new SMS Manager (event-based)
          try {
              $user = User::query()
                  ->where('email', $validated['email'])
                  ->whereIn('phone_number', $phoneCandidates)
                  ->first();
              
              if ($user && $user->phone_number) {
                  $sent = app(SmsManager::class)->sendEvent('otp.generated', [
                      'shop_id' => $user->shop_id ?? 1, // Default to shop 1 if not set
                      'subshop_id' => $user->subshop_id ?? null,
                      'user_id' => $user->id,
                      'phone' => $user->phone_number,
                      'data' => ['otp' => $otpPlain],
                      'sensitive' => true
                  ]);
                  
                  // If event-based SMS failed to queue, fall back to legacy service
                  if (!$sent) {
                      \Log::warning('SMS Manager failed to queue OTP, falling back to SmsService');
                      $smsService->sendSms(
                          $phone,
                          "Your password reset code is: {$otpPlain}. It expires in " . self::OTP_EXPIRY_MINUTES . " minutes.",
                          ['type' => 'password_reset_otp', 'sensitive' => true]
                      );
                  }
              }
          } catch (\Exception $e) {
              // Fallback to old SMS service if new manager fails
              \Log::warning('Failed to send OTP via SmsManager, falling back to SmsService: ' . $e->getMessage());
              $smsService->sendSms(
                  $phone,
                  "Your password reset code is: {$otpPlain}. It expires in " . self::OTP_EXPIRY_MINUTES . " minutes.",
                  ['type' => 'password_reset_otp', 'sensitive' => true]
              );
          }

        $request->session()->put(self::SESSION_KEY, [
            'email' => $validated['email'],
            'phone_number' => $phone,
            'verified' => false,
        ]);

        return redirect()->route('password.otp.verify')->with('status', 'OTP has been sent to your phone number.');
    }

    public function showVerifyOtp(Request $request): View|RedirectResponse
    {
        if (!$this->hasPendingSession($request)) {
            return redirect()->route('password.request')->withErrors(['general' => 'Please request an OTP first.']);
        }

        return view('auth.verify-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        if (!$this->hasPendingSession($request)) {
            return redirect()->route('password.request')->withErrors(['general' => 'Please request an OTP first.']);
        }

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pending = $request->session()->get(self::SESSION_KEY);

        $otpRow = DB::table('password_reset_otps')
            ->where('email', $pending['email'])
            ->where('phone', $pending['phone_number'])
            ->orderByDesc('id')
            ->first();

        if (!$otpRow) {
            $this->clearSession($request);
            return redirect()->route('password.request')->withErrors(['general' => 'OTP session expired. Please request a new code.']);
        }

        if (now()->greaterThan($otpRow->expires_at)) {
            DB::table('password_reset_otps')->where('id', $otpRow->id)->delete();
            $this->clearSession($request);
            return redirect()->route('password.request')->withErrors(['general' => 'OTP expired. Please request a new code.']);
        }

        if ((int) $otpRow->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            DB::table('password_reset_otps')->where('id', $otpRow->id)->delete();
            $this->clearSession($request);
            return redirect()->route('password.request')->withErrors(['general' => 'Too many OTP attempts. Please request a new code.']);
        }

        $isValid = Hash::check($validated['otp'], $otpRow->otp);

        if (!$isValid) {
            DB::table('password_reset_otps')->where('id', $otpRow->id)->increment('attempts');

            $attemptsAfter = (int) $otpRow->attempts + 1;
            if ($attemptsAfter >= self::MAX_VERIFY_ATTEMPTS) {
                DB::table('password_reset_otps')->where('id', $otpRow->id)->delete();
                $this->clearSession($request);
                return redirect()->route('password.request')->withErrors(['general' => 'Too many OTP attempts. Please request a new code.']);
            }

            return back()->withErrors(['otp' => 'Invalid OTP code.']);
        }

        $request->session()->put(self::SESSION_KEY, [
            'email' => $pending['email'],
            'phone_number' => $pending['phone_number'],
            'verified' => true,
        ]);

        return redirect()->route('password.otp.reset');
    }

    public function showResetForm(Request $request): View|RedirectResponse
    {
        if (!$this->isVerified($request)) {
            return redirect()->route('password.request')->withErrors(['general' => 'Please verify the OTP first.']);
        }

        return view('auth.reset-password-phone');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        if (!$this->isVerified($request)) {
            return redirect()->route('password.request')->withErrors(['general' => 'Please verify the OTP first.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $verified = $request->session()->get(self::SESSION_KEY);

        $user = User::query()
            ->where('email', $verified['email'])
            ->where('phone_number', $verified['phone_number'])
            ->first();

        if (!$user) {
            $this->clearSession($request);
            return redirect()->route('password.request')->withErrors(['general' => 'Account not found. Please try again.']);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        DB::table('password_reset_otps')
            ->where('email', $verified['email'])
            ->where('phone', $verified['phone_number'])
            ->delete();

        $this->clearSession($request);

        return redirect()->route('login')->with('status', 'Password reset successfully. Please login.');
    }

    private function phoneCandidates(string $input): array
    {
        $digits = preg_replace('/\D/', '', $input);
        if ($digits === '') {
            return [];
        }

        $candidates = [$digits];

        if (str_starts_with($digits, '0') && strlen($digits) > 1) {
            $candidates[] = '255' . substr($digits, 1);
        }

        if (str_starts_with($digits, '255') && strlen($digits) > 3) {
            $candidates[] = '0' . substr($digits, 3);
        }

        if (!str_starts_with($digits, '255') && !str_starts_with($digits, '0')) {
            $candidates[] = '255' . $digits;
        }

        return array_values(array_unique($candidates));
    }

    private function hasPendingSession(Request $request): bool
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && !empty($pending['email']) && !empty($pending['phone_number']);
    }

    private function isVerified(Request $request): bool
    {
        $data = $request->session()->get(self::SESSION_KEY);

        return is_array($data) && ($data['verified'] ?? false) === true;
    }

    private function clearSession(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
