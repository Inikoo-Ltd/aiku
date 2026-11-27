<?php

/*
 * Author Louis Perez
 * Created on 27-11-2026-12h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Chat\Staff\Json;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the card shown on hovering a person in VX needs: their name, a picture big enough for the card,
 * the organisations they work for and when they were last active. Online, idle and focus come from
 * the live presence channel in the browser, so they are not repeated here.
 */
class GetStaffProfileCard
{
    use AsAction;

    /**
     * @return array{id: int, name: string, is_me: bool, avatar: array|null, organisations: array<int, array{slug: string, code: string, name: string}>, last_active_at: int|null}
     */
    public function handle(User $user, User $viewer): array
    {
        $user->loadMissing('image');

        return [
            'id'             => $user->id,
            'name'           => $user->chatName(),
            'is_me'          => $user->id === $viewer->id,
            'avatar'         => $user->image_id ? User::chatAvatarOf($user->imageSources(0, 160)) : null,
            'organisations'  => $this->organisationsOf($user),
            'last_active_at' => Cache::get('staff-last-active:'.$user->id),
        ];
    }

    /**
     * employed_in_organisation_id is set for few users, so the employee records the user is linked to
     * (still working or leaving) fill in the rest; the employed-in organisation, when set, comes first.
     *
     * @return array<int, array{slug: string, code: string, name: string}>
     */
    protected function organisationsOf(User $user): array
    {
        $employeeOrganisationIds = DB::table('user_has_models')
            ->join('employees', 'employees.id', '=', 'user_has_models.model_id')
            ->where('user_has_models.model_type', 'Employee')
            ->where('user_has_models.user_id', $user->id)
            ->whereIn('employees.state', ['working', 'leaving'])
            ->pluck('employees.organisation_id')
            ->all();

        $organisationIds = array_values(array_unique(array_filter([$user->employed_in_organisation_id, ...$employeeOrganisationIds])));

        if ($organisationIds === []) {
            return [];
        }

        $organisations = Organisation::query()->whereIn('id', $organisationIds)->get(['id', 'slug', 'code', 'name'])->keyBy('id');

        return collect($organisationIds)
            ->map(fn (int $id) => $organisations->get($id))
            ->filter()
            ->map(fn (Organisation $organisation) => [
                'slug' => $organisation->slug,
                'code' => $organisation->code,
                'name' => $organisation->name,
            ])
            ->values()
            ->all();
    }

    public function asController(User $user, ActionRequest $request): array
    {
        $viewer = $request->user();

        abort_unless($user->group_id === $viewer->group_id && $user->status, 404);

        return ['data' => $this->handle($user, $viewer)];
    }
}
