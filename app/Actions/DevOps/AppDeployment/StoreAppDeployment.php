<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 22:48:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\AppDeployment;

use App\Models\DevOps\AppDeployment;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreAppDeployment
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'deploy:record-deployment {--commit= : The commit hash of the deployment}';
    }

    public function getCommandDescription(): string
    {
        return 'Record a new app deployment in the database';
    }


    public function asCommand(Command $command): int
    {
        $commit = $command->option('commit');

        AppDeployment::create([
            'commit_hash' => $commit,
        ]);

        $command->info('Deployment recorded successfully'.($commit ? " for commit $commit" : '').'.');

        return 0;
    }
}
