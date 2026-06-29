<?php

namespace App\Console\Commands\DevOps;

use Illuminate\Console\Command;

class RecordDeployment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:record-deployment {--commit= : The commit hash of the deployment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record a new app deployment in the database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $commit = $this->option('commit');

        \App\Models\DevOps\AppDeployment::create([
            'commit_hash' => $commit,
        ]);

        $this->info('Deployment recorded successfully' . ($commit ? " for commit {$commit}" : '') . '.');

        return 0;
    }
}
