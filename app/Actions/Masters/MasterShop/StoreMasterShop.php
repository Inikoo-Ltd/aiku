<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 17:54:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterShops;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreMasterShop extends OrgAction
{
    use WithMastersEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Group $group, array $modelData): MasterShop
    {
        $masterShop = DB::transaction(function () use ($group, $modelData) {


            /** @var MasterShop $masterShop */
            $masterShop = $group->masterShops()->create($modelData);
            $masterShop->stats()->create();
            $masterShop->salesIntervals()->create();
            $masterShop->orderingStats()->create();
            $masterShop->orderingIntervals()->create();

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $masterShop->timeSeries()->create(['frequency' => $frequency]);
            }

            return $masterShop;
        });
        GroupHydrateMasterShops::dispatch($group)->delay($this->hydratorsDelay);
        return $masterShop;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ShopTypeEnum::class)],
            'code' => [
                'required',
                $this->strict ? 'max:32' : 'max:255',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_shops',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name' => ['required', 'max:250', 'string'],

        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true): MasterShop
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;

        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Group $group, ActionRequest $request): MasterShop
    {
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $this->validatedData);
    }

    public function getCommandSignature(): string
    {
        return 'master_shop:create {group} {type} {code} {name}';
    }

    public function asCommand(Command $command): int
    {
        try {
            /** @var Group $group */
            $group = Group::where('slug', $command->argument('group'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }


        $data = [
            'type' => $command->argument('type'),
            'code' => $command->argument('code'),
            'name' => $command->argument('name'),
        ];


        try {
            $masterShop = $this->action($group, $data);
            $command->info("Master Shop $masterShop->slug created successfully 🎉");
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
