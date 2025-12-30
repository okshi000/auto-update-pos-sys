<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup using mysqldump';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = 'backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.sql';
        // Save to project root backups folder (outside backend)
        $directory = base_path('../backups');
        
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $filename;

        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $database = config('database.connections.mysql.database');
        $port = config('database.connections.mysql.port');

        // Try to find mysqldump
        $mysqldump = 'mysqldump';
        if (file_exists('C:/xampp/mysql/bin/mysqldump.exe')) {
            $mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';
        }

        // Build command
        // Note: Using --password=PASSWORD is not secure for process list but fine for local dev/single user
        // If password is empty, don't include the flag
        $passwordFlag = $password ? "--password=\"$password\"" : "";
        
        $command = sprintf(
            '"%s" --user="%s" %s --host="%s" --port="%s" "%s" > "%s"',
            $mysqldump,
            $username,
            $passwordFlag,
            $host,
            $port,
            $database,
            $path
        );

        $this->info('Starting backup...');
        
        $returnVar = null;
        $output = null;
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info('Backup created successfully: ' . $filename);
            return 0;
        } else {
            $this->error('Backup failed. Return code: ' . $returnVar);
            return 1;
        }
    }
}
