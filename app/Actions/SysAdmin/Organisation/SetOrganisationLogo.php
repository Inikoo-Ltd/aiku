<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 09 Dec 2023 03:25:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Helpers\Media\StoreMediaFromIcon;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SetOrganisationLogo
{
    use AsAction;
    use WithAttachMediaToModel;

    public function handle(Organisation $organisation): Organisation
    {
        $media = StoreMediaFromIcon::run($organisation);
        $this->attachMediaToModel($organisation, $media, 'logo');
        $organisation->updateQuietly(
            [
                'image_id' => $media->id,
            ]
        );

        return $organisation;
    }

    public string $commandSignature = 'org:logo {organisation : Organisation slug}';

    public function asCommand(Command $command): int
    {
        try {
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception) {
            $command->error('Organisation not found');

            return 1;
        }

        try {
            $this->handle($organisation);

            return 0;
        } catch (Exception $exception) {
            $command->error('Error setting logo, '.$exception->getMessage());

            return 1;
        }

    }
}
