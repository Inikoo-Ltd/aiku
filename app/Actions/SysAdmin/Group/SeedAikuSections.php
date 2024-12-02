<?php

/*
 * author Arya Permana - Kirin
 * created on 21-11-2024-11h-16m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\Group;

use App\Actions\Analytics\AikuSection\StoreAikuSection;
use App\Actions\GrpAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Models\Analytics\AikuSection;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedAikuSections extends GrpAction
{
    use AsAction;
    use WithAttachMediaToModel;

    public function handle(Group $group): AikuSection
    {
        foreach (AikuSectionEnum::cases() as $case) {
            $code = $case->value;
            $name = $case->labels()[$case->value];
            if ($group->aikuSections()->where('code', $code)->exists()) {
                continue;
            }

            $aikuSection = StoreAikuSection::make()->action(
                $group,
                [
                    'code' => $code,
                    'name' => $name,
                ]
            );

            //            $scopeType = $case->scopeType();
            //            match ($scopeType) {
            //                'Group' => $storedSection->groups()->attach($group),
            //                'Organisation' => $storedSection->organisations()->attach($group->organisations->pluck('id')->toArray()),
            //                'Shop' => $storedSection->shops()->attach($group->shops->pluck('id')->toArray()),
            //                'Fulfilment' => $storedSection->fulfilments()->attach($group->fulfilments->pluck('id')->toArray()),
            //                'Production' => $storedSection->productions()->attach($group->productions->pluck('id')->toArray()),
            //                'Warehouse' => $storedSection->warehouses()->attach($group->warehouses->pluck('id')->toArray()),
            //                default => throw new \Exception("Unknown scope type: {$scopeType}"),
            //            };
        }

        return $aikuSection;
    }

    public string $commandSignature = 'group:seed_aiku_sections {group : group slug}';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding aiku sections types for group: $group->name");
            $this->handle($group);
        }

        return 0;
    }
}
