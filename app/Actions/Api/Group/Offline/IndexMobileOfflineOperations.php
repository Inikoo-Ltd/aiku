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

class IndexMobileOfflineOperations
{
    use AsController;
    use WithMobileOfflineAccess;

    public const int RECENT_DAYS = 7;

    public const int MAX_ITEMS = 500;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user, string $deviceUuid, string $state): array
    {
        $operations = MobileOfflineOperation::query()
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $user->id)
            ->when(
                $state === 'open',
                fn ($query) => $query->whereIn('state', MobileOfflineOperation::OPEN_STATES)->orderBy('device_sequence'),
                fn ($query) => $query->where('synced_at', '>=', now()->subDays(self::RECENT_DAYS))->orderByDesc('synced_at')->orderByDesc('id')
            )
            ->limit(self::MAX_ITEMS)
            ->get();

        return $this->presenter()->items($operations, $user);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $headers = MobileOfflineDeviceHeaders::requiredFromRequest($request);

        $data = Validator::make($request->all(), [
            'state' => ['sometimes', 'nullable', 'in:open,recent'],
        ])->validate();

        return response()->json([
            'data' => $this->handle($user, $headers->deviceUuid, $data['state'] ?? 'open'),
        ]);
    }
}
