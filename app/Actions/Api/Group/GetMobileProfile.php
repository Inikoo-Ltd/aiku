<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-11h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileProfile
{
    use AsController;

    /**
     * @return array{id: int, username: string, name: string|null, position: string|null, job_title: string|null, job_positions: array<int, array{code: string, name: string}>, organisation: array{id: int, code: string, name: string}|null}
     */
    public function handle(User $user): array
    {
        $employee     = GetUserCurrentEmployee::run($user);
        $jobTitle     = trim((string) $employee?->job_title) ?: null;
        $jobPositions = collect($employee?->jobPositions ?? [])
            ->merge($user->pseudoJobPositions)
            ->unique('id')
            ->map(fn (JobPosition $jobPosition) => ['code' => $jobPosition->code, 'name' => $jobPosition->name])
            ->values();

        return [
            'id'            => $user->id,
            'username'      => $user->username,
            'name'          => $user->contact_name,
            'position'      => $jobTitle ?? ($jobPositions->pluck('name')->implode(', ') ?: null),
            'job_title'     => $jobTitle,
            'job_positions' => $jobPositions->all(),
            'organisation'  => $employee ? [
                'id'   => $employee->organisation->id,
                'code' => $employee->organisation->code,
                'name' => $employee->organisation->name,
            ] : null,
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->handle($user)]);
    }
}
