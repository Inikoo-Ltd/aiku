<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest;

use App\Actions\GrpAction;
use App\Actions\HumanResources\JobPosition\SyncUserJobPositions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateGuests;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateUniversalSearch;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\Traits\WithPreparePositionsForValidation;
use App\Actions\Traits\WithReorganisePositions;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Rules\Phone;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreGuest extends GrpAction
{
    use WithPreparePositionsForValidation;
    use WithReorganisePositions;

    private bool $validatePhone = false;

    /**
     * @throws \Throwable
     */
    public function handle(Group $group, array $modelData): Guest
    {
        $positions = Arr::get($modelData, 'positions', []);
        data_forget($modelData, 'positions');
        $positions = $this->reorganisePositionsSlugsToIds($positions);


        data_set($modelData, 'status', true, overwrite: false);

        $userData = Arr::get($modelData, 'user', []);
        data_set($userData, 'status', $modelData['status'], overwrite: false);
        data_set($userData, 'contact_name', Arr::get($modelData, 'contact_name'), overwrite: false);
        data_set($userData, 'email', Arr::get($modelData, 'email'), overwrite: false);

        $guest = DB::transaction(function () use ($group, $modelData, $userData, $positions) {
            /** @var Guest $guest */
            $guest = $group->guests()->create(Arr::except($modelData, ['user',]));
            $guest->stats()->create();
            $user = StoreUser::make()->action($guest, $userData, $this->hydratorsDelay, strict: $this->strict);
            SyncUserJobPositions::run($user, $positions);

            return $guest;
        });

        GuestHydrateUniversalSearch::dispatch($guest);
        GroupHydrateGuests::dispatch($group);

        return $guest;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("sysadmin.edit");
    }

    public function prepareForValidation(): void
    {

        if ($this->has('username')) {
            $this->set('user.username', $this->get('username'));
        }

        if ($this->has('password')) {
            $this->set('user.password', $this->get('password'));
        }


        if (!$this->has('code')) {
            $this->set('code', $this->get('user.username'));
        }
        if ($this->get('phone')) {
            $this->set('phone', preg_replace('/[^0-9+]/', '', $this->get('phone')));
        }

        if ($this->get('positions')) {
            $this->set('phone', preg_replace('/[^0-9+]/', '', $this->get('phone')));
        }

        $this->prepareJobPositionsForValidation();
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($validator->errors()->has('code')) {
            $validator->errors()->add('user.username', $validator->errors()->first('code'));
        }
    }

    public function rules(): array
    {
        $phoneValidation = ['sometimes', 'nullable'];

        if ($this->validatePhone) {
            $phoneValidation[] = new Phone();
        }


        $rules = [
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::notIn(['export', 'create']),
                new IUnique(table: 'guests'),

            ],

            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'phone'        => $phoneValidation,
            'email'        => ['sometimes', 'nullable', 'email'],
            'positions'    => ['sometimes', 'array'],
            'status'       => ['sometimes', 'boolean'],

            'positions.*.slug'   => ['sometimes', 'string'],
            'positions.*.scopes' => ['sometimes', 'array'],

            'positions.*.scopes.organisations.slug.*' => ['sometimes', Rule::exists('organisations', 'slug')->where('group_id', $this->group->id)],
            'positions.*.scopes.warehouses.slug.*'    => ['sometimes', Rule::exists('warehouses', 'slug')->where('group_id', $this->group->id)],
            'positions.*.scopes.fulfilments.slug.*'   => ['sometimes', Rule::exists('fulfilments', 'slug')->where('group_id', $this->group->id)],
            'positions.*.scopes.shops.slug.*'         => ['sometimes', Rule::exists('shops', 'slug')->where('group_id', $this->group->id)],


            'user.username'          => [
                'required',
                $this->strict ? new AlphaDashDot() : 'string',
                new IUnique(
                    table: 'users',
                    column: 'username',
                    extraConditions: [

                        [
                            'column' => 'group_id',
                            'value'  => $this->group->id
                        ],
                    ]
                ),
                Rule::notIn(['export', 'create'])
            ],
            'user.password'          => ['required', app()->isLocal() || app()->environment('testing') || !$this->strict ? Password::min(3) : Password::min(8)],
            'user.reset_password'    => ['sometimes', 'boolean'],
            'user.email'             => [
                'sometimes',
                'nullable',
                'email',
                new IUnique(
                    table: 'users',
                    extraConditions: [
                        [
                            'column' => 'group_id',
                            'value'  => $this->group->id
                        ],
                    ]
                ),

            ],
            'user.contact_name'      => ['sometimes', 'string', 'max:255'],
            'user.auth_type'         => ['sometimes', Rule::enum(UserAuthTypeEnum::class)],
            'user.status'            => ['sometimes', 'required', 'boolean'],
            'user.user_model_status' => ['sometimes', 'boolean'],
            'user.language_id'       => ['sometimes', 'required', 'exists:languages,id'],

        ];

        if (!$this->strict) {
            $rules['deleted_at'] = ['sometimes', 'date'];
            $rules['created_at'] = ['sometimes', 'date'];
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];

            $rules['user.deleted_at']      = ['sometimes', 'date'];
            $rules['user.created_at']      = ['sometimes', 'date'];
            $rules['user.fetched_at']      = ['sometimes', 'date'];
            $rules['user.source_id']       = ['sometimes', 'string', 'max:255'];
            $rules['user.legacy_password'] = ['sometimes', 'required', 'string', 'max:255'];
        }


        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Guest
    {

        $this->initialisation(app('group'), $request);

        return $this->handle($this->group, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Guest
    {
        if (!$audit) {
            Guest::disableAuditing();
        }
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->initialisation($group, $modelData);


        return $this->handle($group, $this->validatedData);
    }

    public string $commandSignature = 'guest:create {group : group slug} {name} {username}
     {--positions=}
     {--P|password=} {--e|email=} {--t|phone=} {--identity_document_number=} {--identity_document_type=}';


    public function asCommand(Command $command): int
    {
        $this->asAction = true;

        try {
            /** @var Group $group */
            $group       = Group::where('slug', $command->argument('group'))->firstOrFail();
            $this->group = $group;
            app()->instance('group', $group);
            setPermissionsTeamId($group->id);
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $positions = json_decode($command->option('positions'), true);


        $fields = [
            'positions'    => $positions,
            'contact_name' => $command->argument('name'),
            'email'        => $command->option('email'),
            'phone'        => $command->option('phone'),
            'user'         => [
                'username' => $command->argument('username'),
                'password' => $command->option('password') ?? (app()->isLocal() ? 'hello' : wordwrap(Str::random(), 4, '-', true))

            ]

        ];

        $this->fill($fields);

        try {
            $guest = $this->handle($group, $this->validateAttributes());
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $command->info("Guest <fg=yellow>$guest->slug</> created 👍");


        return 0;
    }

    public function htmlResponse(Guest $guest): RedirectResponse
    {
        return Redirect::route('grp.sysadmin.guests.show', $guest->slug);
    }

}
