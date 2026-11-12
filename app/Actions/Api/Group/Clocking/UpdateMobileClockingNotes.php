<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-09h-04m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Clocking\UpdateClockingNotes;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileClockingNotes
{
    use AsController;

    public function authorize(ActionRequest $request): bool
    {
        /** @var Clocking $clocking */
        $clocking = $request->route('clocking');
        /** @var User $user */
        $user = $request->user();

        return $clocking->subject instanceof Employee
            && $user->employees()->whereKey($clocking->subject_id)->exists()
            && UpdateClockingNotes::canEditClockings($user, $clocking->organisation);
    }

    public function rules(): array
    {
        return [
            'notes' => ['present', 'nullable', 'string', 'max:500'],
        ];
    }

    public function asController(Clocking $clocking, ActionRequest $request): JsonResponse
    {
        $clocking = UpdateClockingNotes::run($clocking, $request->validated()['notes'], null);

        return response()->json([
            'data' => [
                'id'    => $clocking->id,
                'notes' => $clocking->notes,
            ],
        ]);
    }
}
