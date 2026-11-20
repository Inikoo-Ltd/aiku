<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class RollbackMobileOfflineOperation
{
    use AsController;
    use WithMobileOfflineAccess;

    /**
     * @return array{operation: array<string, mixed>}
     * @throws Throwable
     */
    public function handle(MobileOfflineOperation $operation, User $user, ?string $reason, MobileOfflineDeviceHeaders $headers): array
    {
        $rolledBack = $this->engine()->rollback($operation, $user, $reason, $headers);

        return [
            'operation' => $this->presenter()->items(collect([$rolledBack->refresh()]), $user)[0],
        ];
    }

    /**
     * @throws Throwable
     */
    public function asController(MobileOfflineOperation $operation, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandleOperation($user, $operation);

        $data = Validator::make($request->all(), [
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        return response()->json(['data' => $this->handle($operation, $user, $data['reason'] ?? null, MobileOfflineDeviceHeaders::fromRequest($request))]);
    }
}
