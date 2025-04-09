<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Nov 2024 00:43:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;

trait WithOrganisationCommand
{
    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('organisation') == null) {
            $organisations = Organisation::all();
            foreach ($organisations as $organisation) {
                $this->handle($organisation);
            }

            return 0;
        }

        try {
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($organisation);

        return 0;
    }
}
