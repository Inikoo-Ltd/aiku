<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Web\WebBlockType\StoreWebBlockType;
use App\Actions\Web\WebBlockType\UpdateWebBlockType;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedWebBlockTypes
{
    use AsAction;

    public function handle(Group $group): void
    {
        foreach (Storage::disk('datasets')->files('web-block-types') as $file) {
            $rawWebBlockTypeData = Storage::disk('datasets')->json($file);
            $webBlockTypeData    = Arr::only(
                $rawWebBlockTypeData,
                [
                    'scope',
                    'code',
                    'name',
                    'category',
                    'fixed',
                    'data'
                ]
            );

            $additionalData = [];

            if (Arr::has($rawWebBlockTypeData, 'icon')) {
                $additionalData = [
                    'icon' => Arr::get($rawWebBlockTypeData, 'icon')
                ];
            }

            if (Arr::has($rawWebBlockTypeData, 'component')) {
                $additionalData = [
                    'component' => Arr::get($rawWebBlockTypeData, 'component')
                ];
            }


            if ($additionalData != []) {
                $data = array_merge($webBlockTypeData['data'], $additionalData);
                data_set($webBlockTypeData, 'data', $data);
            }


            $webBlockType = $group->webBlockTypes()->where('code', Arr::get($webBlockTypeData, 'code'))->first();
            if ($webBlockType) {
                $webBlockType = UpdateWebBlockType::run($webBlockType, $webBlockTypeData);
            } else {
                $webBlockType = StoreWebBlockType::run($group, $webBlockTypeData);
            }


            $imagePath = 'web-block-types/screenshots/'.$webBlockType->code.'.png';
            if (Storage::disk('datasets')->exists($imagePath)) {
                SaveModelImage::run(
                    $webBlockType,
                    [
                        'path' => Storage::disk('datasets')->path($imagePath),
                        'originalName' => $webBlockType->code.'.png',

                    ],
                    'screenshot'
                );
            }
        }
    }

    public string $commandSignature = 'group:seed_web_block_types';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding web block types for group: $group->name");
            $this->handle($group);
        }

        return 0;
    }
}
