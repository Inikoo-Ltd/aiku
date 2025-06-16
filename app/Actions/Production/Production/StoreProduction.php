<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production;

use App\Actions\OrgAction;
use App\Actions\Production\Production\Search\ProductionRecordSearch;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProductions;
use App\Actions\SysAdmin\Group\Seeders\SeedAikuScopedSections;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProductions;
use App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions;
use App\Actions\SysAdmin\User\UserAddRoles;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Production\Production\ProductionStateEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use App\Rules\IUnique;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreProduction extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, $modelData): Production
    {
        data_set($modelData, 'group_id', $organisation->group_id);

        $production = DB::transaction(function () use ($organisation, $modelData) {
            /** @var Production $production */
            $production = $organisation->productions()->create($modelData);
            $production->stats()->create();

            SeedProductionPermissions::run($production);

            $orgAdmins = $organisation->group->users()->with('roles')->get()->filter(
                fn ($user) => $user->roles->where('name', "org-admin-$organisation->id")->toArray()
            );
            foreach ($orgAdmins as $orgAdmin) {
                UserAddRoles::run($orgAdmin, [
                    Role::where('name', RolesEnum::getRoleName(RolesEnum::MANUFACTURING_ADMIN->value, $production))->first()
                ]);
            }
            SeedJobPositions::run($organisation);
            SeedAikuScopedSections::make()->seedProductionAikuScopedSection($production);

            return $production;
        });

        GroupHydrateProductions::dispatch($organisation->group)->delay($this->hydratorsDelay);
        OrganisationHydrateProductions::dispatch($organisation)->delay($this->hydratorsDelay);
        ProductionRecordSearch::dispatch($production);


        return $production;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("inventory.productions.edit");
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'required',
                'max:12',
                'alpha_dash',
                new IUnique(
                    table: 'productions',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                    ]
                ),
            ],
            'name' => ['required', 'max:250', 'string'],

        ];
        if (!$this->strict) {
            $rules['sources']   = ['sometimes', 'nullable', 'array'];
            $rules['opened_at'] = ['sometimes', 'nullable', 'date'];
            $rules['closed_at'] = ['sometimes', 'nullable', 'date'];
            $rules['state']     = ['sometimes', Rule::enum(ProductionStateEnum::class)];
            $rules              = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Production
    {
        if (!$audit) {
            Production::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): Production
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }


    public function htmlResponse(Production $production): RedirectResponse
    {
        return Redirect::route('grp.org.productions.index');
    }

    public string $commandSignature = 'production:create {organisation : organisation slug} {code} {name} {--state=} {--created_at=} {--opened_at=} {--closed_at=} {--source=*} ';

    public function asCommand(Command $command): int
    {
        $this->asAction = true;

        try {
            /** @var Organisation $organisation */
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }
        $this->organisation = $organisation;
        setPermissionsTeamId($organisation->group->id);


        $modelData = [
            'code' => $command->argument('code'),
            'name' => $command->argument('name'),

        ];

        if ($command->option('state')) {
            $modelData['state'] = $command->option('state');
            $this->strict       = false;
        }

        if ($command->option('source')) {
            $this->strict         = false;
            $modelData['sources'] = [
                'suppliers' => $command->option('source')
            ];
        }

        if ($command->option('created_at')) {
            $this->strict            = false;
            $modelData['created_at'] = $command->option('created_at');
        }
        if ($command->option('opened_at')) {
            $this->strict           = false;
            $modelData['opened_at'] = $command->option('opened_at');
        }
        if ($command->option('closed_at')) {
            $this->strict           = false;
            $modelData['closed_at'] = $command->option('closed_at');
        }
        if (!$this->strict) {
            Production::disableAuditing();
            $this->hydratorsDelay = 60;
        }


        try {
            $this->initialisation($organisation, $modelData);
            $production = $this->handle($organisation, $this->validatedData);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }


        $command->info("Production $production->code created successfully 🎉");

        return 0;
    }

}
