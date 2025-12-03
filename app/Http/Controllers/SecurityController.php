<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;
use Akaunting\Firewall\Models\Ip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SecurityController extends Controller
{
    public function index()
    {
        // Get all authentication logs
        $logs = AuthenticationLog::orderBy('login_at', 'desc')->paginate(20);

        // Get all blocked IPs
        $blockedIps = Ip::all();

        // Calculate statistics
        $totalLogs = AuthenticationLog::count();
        $successfulLogins = AuthenticationLog::where('login_successful', true)->count();
        $failedLogins = AuthenticationLog::where('login_successful', false)->count();
        $blockedIpsCount = Ip::count();

        // Get recent activity (last 7 days)
        $recentLogs = AuthenticationLog::where('login_at', '>=', now()->subDays(7))->count();

        $statistics = [
            'total_logs' => $totalLogs,
            'successful_logins' => $successfulLogins,
            'failed_logins' => $failedLogins,
            'blocked_ips' => $blockedIpsCount,
            'recent_activity' => $recentLogs,
        ];

        return view('shops_management.security', compact('logs', 'blockedIps', 'statistics'));
    }

    public function blockIP(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);

        Ip::create([
            'ip' => $request->ip,
            'reason' => 'Blocked by admin'
        ]);

        return redirect()->back()->with('success', 'IP blocked successfully.');
    }

    public function unblockIP(Request $request)
    {
        Ip::where('ip', $request->ip)->delete();

        return redirect()->back()->with('success', 'IP unblocked successfully.');
    }

    public function clearOldDailyLogs(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can clear logs.');
        }

        $logsPath = storage_path('logs');
        $todayPrefix = 'laravel-'.now()->format('Y-m-d');
        $deleted = 0;

        if (File::exists($logsPath)) {
            foreach (File::files($logsPath) as $file) {
                $filename = $file->getFilename();
                // Delete daily log files older than today and not the generic laravel.log
                if (Str::startsWith($filename, 'laravel-') && !Str::startsWith($filename, $todayPrefix)) {
                    try {
                        File::delete($file->getRealPath());
                        $deleted++;
                    } catch (\Throwable $e) {
                        // continue; we won't break the whole operation on one failure
                    }
                }
            }
        }

        return redirect()->back()->with('success', "Cleared {$deleted} old daily log file(s). Today's log kept.");
    }

    public function clearAuthLogs(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can clear authentication logs.');
        }

        $count = AuthenticationLog::count();
        AuthenticationLog::truncate();

        return redirect()->back()->with('success', "Cleared {$count} authentication log record(s) from the database.");
    }

    public function clearCaches(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can clear caches.');
        }

        try {
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to clear caches: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Routes, views, config and app cache cleared, and application optimized.');
    }

    public function sessions(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }

        $table = config('session.table', 'sessions');
        $sessions = DB::table($table)
            ->leftJoin('users', 'users.id', '=', $table.'.user_id')
            ->select(
                $table.'.id',
                $table.'.user_id',
                DB::raw('COALESCE(users.name, "Guest") as user_name'),
                $table.'.ip_address',
                $table.'.user_agent',
                $table.'.last_activity'
            )
            ->orderByDesc($table.'.last_activity')
            ->get()
            ->map(function ($row) {
                $row->last_activity_human = Carbon::createFromTimestamp($row->last_activity)->diffForHumans();
                $row->last_activity_at = Carbon::createFromTimestamp($row->last_activity)->toDateTimeString();
                return $row;
            });

        return response()->json([
            'data' => $sessions,
            'lifetime' => (int) config('session.lifetime'),
        ]);
    }

    public function destroySession(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }
        $request->validate(['id' => 'required|string']);
        $table = config('session.table', 'sessions');
        DB::table($table)->where('id', $request->id)->delete();
        return response()->json(['success' => true]);
    }

    public function destroyOtherSessions(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }
        $table = config('session.table', 'sessions');
        $currentId = $request->session()->getId();
        DB::table($table)->where('id', '!=', $currentId)->delete();
        return response()->json(['success' => true]);
    }

    public function updateSessionTimeout(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'lifetime' => 'required|integer|min:1|max:1440',
        ]);

        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $contents = File::get($envPath);
            $pattern = "/^SESSION_LIFETIME=.*/m";
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, 'SESSION_LIFETIME='.$validated['lifetime'], $contents);
            } else {
                $contents .= "\nSESSION_LIFETIME=".$validated['lifetime']."\n";
            }
            File::put($envPath, $contents);
        }

        Artisan::call('config:clear');

        return response()->json(['success' => true, 'lifetime' => (int) $validated['lifetime']]);
    }

    public function timezoneInfo(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }

        $current = config('app.timezone');
        $timezones = \DateTimeZone::listIdentifiers();

        return response()->json([
            'current' => $current,
            'timezones' => $timezones,
        ]);
    }

    public function updateTimezone(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }

        $request->validate([
            'timezone' => 'required|string',
        ]);

        $tz = $request->input('timezone');
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return response()->json(['success' => false, 'message' => 'Invalid timezone.'], 422);
        }

        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $contents = File::get($envPath);
            $pattern = "/^APP_TIMEZONE=.*/m";
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, 'APP_TIMEZONE=' . $tz, $contents);
            } else {
                $contents .= "\nAPP_TIMEZONE=" . $tz . "\n";
            }
            File::put($envPath, $contents);
        }

        Artisan::call('config:clear');

        return response()->json(['success' => true, 'timezone' => $tz]);
    }
}
