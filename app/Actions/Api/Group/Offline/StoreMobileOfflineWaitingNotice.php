<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Models\SysAdmin\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileOfflineWaitingNotice
{
    use AsController;

    public const int DELAY_HOURS = 24;

    /**
     * @param array{user_id: int, count: int, oldest_performed_at?: ?string} $modelData
     */
    public function handle(User $sender, string $deviceUuid, array $modelData): void
    {
        CheckMobileOfflineWaitingNotice::dispatch(
            $deviceUuid,
            (int) $modelData['user_id'],
            $sender->id,
            (int) $modelData['count'],
            $modelData['oldest_performed_at'] ?? null,
            now()->toIso8601String(),
        )->delay(now()->addHours(self::DELAY_HOURS));
    }

    public function asController(ActionRequest $request): Response
    {
        /** @var User $user */
        $user    = $request->user();
        $headers = MobileOfflineDeviceHeaders::requiredFromRequest($request);

        $data = Validator::make($request->all(), [
            'user_id'             => ['required', 'integer', Rule::exists('users', 'id')->where('group_id', $user->group_id)],
            'count'               => ['required', 'integer', 'min:1', 'max:1000000'],
            'oldest_performed_at' => ['nullable', 'date'],
        ])->validate();

        $this->handle($user, $headers->deviceUuid, $data);

        return response()->noContent();
    }
}
