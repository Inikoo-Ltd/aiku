<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineDevice;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class SyncMobileOfflineActions
{
    use AsController;
    use WithMobileOfflineAccess;

    public const int MAX_ACTIONS = 50;

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array{results: array<int, array<string, mixed>>, last_sequence: int, open_count: int, expected_sequence?: int}
     * @throws Throwable
     */
    public function handle(Warehouse $warehouse, User $user, MobileOfflineDeviceHeaders $headers, array $actions): array
    {
        $engine   = $this->engine();
        $syncedAt = CarbonImmutable::now();
        $engine->ensureDevice($headers, $user);

        $outcomes         = [];
        $expectedSequence = null;
        foreach ($actions as $action) {
            $outcome    = $engine->syncAction($headers, $user, $warehouse, $action, $syncedAt);
            $outcomes[] = $outcome;
            if ($outcome['status'] === 'missing_previous') {
                $expectedSequence = $outcome['expected_sequence'];
                break;
            }
        }

        $presenter = $this->presenter();
        $versions  = $presenter->versionsOf(collect($outcomes)->pluck('operation'));

        $data = [
            'results'       => array_map(fn (array $outcome) => $presenter->syncResult($outcome, $versions), $outcomes),
            'last_sequence' => (int) MobileOfflineDevice::query()->where('device_uuid', $headers->deviceUuid)->where('user_id', $user->id)->value('last_sequence'),
            'open_count'    => $this->openCount($headers->deviceUuid, $user),
        ];

        if ($expectedSequence !== null) {
            $data['expected_sequence'] = $expectedSequence;
        }

        return $data;
    }

    /**
     * @throws Throwable
     */
    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureAuthorisedWarehouse($user, $warehouse);
        $headers = MobileOfflineDeviceHeaders::requiredFromRequest($request);

        $data = Validator::make($request->all(), [
            'actions'                => ['present', 'array', 'max:'.self::MAX_ACTIONS],
            'actions.*'              => ['array'],
            'actions.*.sequence'     => ['required', 'integer', 'min:1', 'max:9000000000000000'],
            'actions.*.operation'    => ['required', 'string', 'max:32'],
            'actions.*.performed_at' => ['nullable', 'date'],
            'actions.*.time_source'  => ['nullable', 'in:server,device'],
            'actions.*.seen_at'      => ['nullable', 'date'],
            'actions.*.payload'      => ['present', 'array'],
        ])->validate();

        return response()->json(['data' => $this->handle($warehouse, $user, $headers, $data['actions'])]);
    }
}
