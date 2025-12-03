<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;

class DataController extends Controller
{
    public function index()
    {
        // Get storage statistics
        $storagePath = storage_path();
        $storageSize = $this->getFolderSize($storagePath);
        $storageSizeMB = round($storageSize / 1024 / 1024, 2);

        // Get disk usage for the partition containing storage
        $diskTotal = disk_total_space($storagePath);
        $diskFree = disk_free_space($storagePath);
        $diskUsed = $diskTotal - $diskFree;
        $diskUsagePercent = round(($diskUsed / $diskTotal) * 100, 2);

        // Get database size (approximate)
        $dbSize = $this->getDatabaseSize();
        $dbSizeMB = round($dbSize / 1024 / 1024, 2);

        // Get existing backups
        $backupPath = storage_path('app/backups');
        $backups = [];
        if (File::exists($backupPath)) {
            $files = File::files($backupPath);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $backups[] = [
                        'name' => basename($file),
                        'size' => round(filesize($file) / 1024 / 1024, 2) . ' MB',
                        'date' => date('Y-m-d H:i:s', filemtime($file)),
                        'path' => $file
                    ];
                }
            }
            // Sort by date descending
            usort($backups, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }

        return view('shops_management.data', compact(
            'storageSizeMB',
            'diskTotal',
            'diskFree',
            'diskUsed',
            'diskUsagePercent',
            'dbSizeMB',
            'backups'
        ));
    }

    public function backup(Request $request)
    {
        try {
            // Ensure backup directory exists
            $backupPath = storage_path('app/backups');
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }

            // Generate backup filename
            $filename = 'DukaBase_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupPath . '/' . $filename;

            // Get database credentials using config
            $dbHost = config('database.connections.mysql.host', '127.0.0.1');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            // Test database connection first
            try {
                DB::connection()->getPdo();
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Database connection failed: ' . $e->getMessage());
            }

            // Check if database name is set
            if (empty($dbName)) {
                return redirect()->back()->with('error', 'Database name is not configured. Please check your database configuration.');
            }

            // Create mysqldump command
            $command = [
                'mysqldump',
                '--host=' . $dbHost,
                '--user=' . $dbUser,
                '--password=' . $dbPass,
                '--skip-ssl',
                '-B',
                $dbName,
                '--result-file=' . $filepath
            ];

            // Debug: Log the command (remove in production)
            \Log::info('MySQL Dump Command: ' . implode(' ', array_map('escapeshellarg', $command)));

            // Execute the backup
            $process = new Process($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if ($process->isSuccessful()) {
                return redirect()->back()->with('success', 'Database backup created successfully: ' . $filename);
            } else {
                return redirect()->back()->with('error', 'Backup failed: ' . $process->getErrorOutput());
            }

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function downloadBackup($filename)
    {
        $filepath = storage_path('app/backups/' . $filename);

        if (!File::exists($filepath)) {
            abort(404);
        }

        return response()->download($filepath);
    }

    public function deleteBackup($filename)
    {
        $filepath = storage_path('app/backups/' . $filename);

        if (!File::exists($filepath)) {
            return redirect()->back()->with('error', 'Backup file not found.');
        }

        File::delete($filepath);

        return redirect()->back()->with('success', 'Backup deleted successfully.');
    }

    private function getFolderSize($folder)
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function getDatabaseSize()
    {
        $database = env('DB_DATABASE');
        $result = DB::select("SELECT 
            ROUND(SUM(data_length + index_length), 0) AS size 
            FROM information_schema.tables 
            WHERE table_schema = ?", [$database]);

        return $result[0]->size ?? 0;
    }
}
