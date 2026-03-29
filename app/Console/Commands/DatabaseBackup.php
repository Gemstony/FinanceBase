<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     * php artisan db:backup
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     */
    protected $description = 'Create a backup of the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Starting database backup...');

            // Ensure backup directory exists
            $backupPath = storage_path('app/backups');
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
                $this->info('Created backup directory: ' . $backupPath);
            }

            // Generate backup filename
            $filename = 'FinanceBase_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupPath . '/' . $filename;

            // Get database credentials using config
            $dbHost = config('database.connections.mysql.host', '127.0.0.1');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            // Test database connection first
            try {
                DB::connection()->getPdo();
                $this->info('Database connection successful.');
            } catch (\Exception $e) {
                $this->error('Database connection failed: ' . $e->getMessage());
                Log::error('Database backup failed: connection error', [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
                return Command::FAILURE;
            }

            // Check if database name is set
            if (empty($dbName)) {
                $this->error('Database name is not configured. Please check your database configuration.');
                Log::error('Database backup failed: database name not configured');
                return Command::FAILURE;
            }

            $this->info('Backing up database: ' . $dbName);

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
            Log::info('MySQL Dump Command: ' . implode(' ', array_map('escapeshellarg', $command)));

            // Execute the backup
            $process = new Process($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if ($process->isSuccessful()) {
                $filesize = round(filesize($filepath) / 1024 / 1024, 2);
                $this->info('Database backup created successfully: ' . $filename);
                $this->info('Backup size: ' . $filesize . ' MB');
                $this->info('Backup location: ' . $filepath);
                
                Log::info('Database backup completed successfully', [
                    'filename' => $filename,
                    'size_mb' => $filesize,
                    'path' => $filepath,
                ]);
                
                return Command::SUCCESS;
            } else {
                $errorOutput = $process->getErrorOutput();
                $this->error('Backup failed: ' . $errorOutput);
                
                Log::error('Database backup failed', [
                    'error' => $errorOutput,
                    'command' => implode(' ', $command),
                ]);
                
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            
            Log::error('Database backup failed with exception', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            
            return Command::FAILURE;
        }
    }
}
