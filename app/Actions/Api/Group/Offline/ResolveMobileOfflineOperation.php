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

class ResolveMobileOfflineOperation
{
    use AsController;
    use WithMobileOfflineAccess;

    /**
     * @return array{operation: array<string, mixed>, results: array<int, array<string, mixed>>}
     * @throws Throwable
     */
    public function handle(MobileOfflineOperation $operation, User $user, string $resolution): array
    {
        $resolved  = $this->engine()->resolve($operation, $resolution, $user);
        $presenter = $this->presenter();

        return [
            'operation' => $presenter->items(collect([$resolved['operation']->refresh()]), $user)[0],
            'results'   => $presenter->results($resolved['reruns']),
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
            'resolution' => ['required', 'string', 'in:'.implode(',', MobileOfflineEngine::RESOLUTIONS)],
        ])->validate();

        return response()->json(['data' => $this->handle($operation, $user, $data['resolution'])]);
    }
}
