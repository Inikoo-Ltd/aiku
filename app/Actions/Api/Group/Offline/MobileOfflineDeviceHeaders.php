<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class MobileOfflineDeviceHeaders
{
    public const string DEVICE_HEADER = 'X-Aiku-Device';
    public const string PLATFORM_HEADER = 'X-Aiku-Platform';
    public const string APP_VERSION_HEADER = 'X-Aiku-App-Version';

    public const array PLATFORMS = ['android', 'ios'];

    private function __construct(
        public ?string $deviceUuid,
        public string $platform,
        public ?string $appVersion,
        public ?int $personalAccessTokenId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $device   = trim((string) $request->header(self::DEVICE_HEADER));
        $platform = strtolower(trim((string) $request->header(self::PLATFORM_HEADER)));
        $version  = trim((string) $request->header(self::APP_VERSION_HEADER));
        $token    = $request->user()?->currentAccessToken();

        return new self(
            Str::isUuid($device) ? strtolower($device) : null,
            in_array($platform, self::PLATFORMS, true) ? $platform : 'unknown',
            $version === '' ? null : Str::limit($version, 32, ''),
            $token instanceof PersonalAccessToken && $token->exists ? (int) $token->getKey() : null,
        );
    }

    /**
     * @throws ValidationException
     */
    public static function requiredFromRequest(Request $request): self
    {
        $headers = self::fromRequest($request);
        if ($headers->deviceUuid === null) {
            throw ValidationException::withMessages([self::DEVICE_HEADER => __('This request needs the device id of the app.')]);
        }

        return $headers;
    }
}
