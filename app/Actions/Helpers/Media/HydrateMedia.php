<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Actions\Helpers\Media\Hydrators\MediaHydrateDimensions;
use App\Actions\Helpers\Media\Hydrators\MediaHydrateMultiplicity;
use App\Actions\Helpers\Media\Hydrators\MediaHydrateUsage;
use App\Actions\HydrateModel;
use App\Models\Helpers\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class HydrateMedia extends HydrateModel
{
    public string $commandSignature = 'hydrate:medias {--id=}';

    public function handle(Media $media): void
    {
        MediaHydrateUsage::run($media);
        MediaHydrateMultiplicity::run($media);
        MediaHydrateDimensions::run($media);
    }

    public function asCommand(Command $command): int
    {
        $exitCode = 0;
        if ($command->option('id')) {
            /** @var Media $media */
            $media = Media::find($command->option('id'));
            $this->handle($media);
        } else {

            $count = Media::count();
            $bar   = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();
            Media::chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
            $bar->finish();

        }

        return $exitCode;
    }
}
