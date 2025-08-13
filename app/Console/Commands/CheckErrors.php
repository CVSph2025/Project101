<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckErrors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-errors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check recent errors in Laravel log';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!file_exists($logPath)) {
            $this->info('No laravel.log file found.');
            return;
        }

        $lines = file($logPath);
        $recentLines = array_slice($lines, -20); // Last 20 lines
        
        $this->info('Last 20 lines of laravel.log:');
        $this->info('===============================');
        
        foreach ($recentLines as $line) {
            if (trim($line)) {
                $this->line(trim($line));
            }
        }
    }
}
