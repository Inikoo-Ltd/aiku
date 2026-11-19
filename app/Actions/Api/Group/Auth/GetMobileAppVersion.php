<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-13h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileAppVersion
{
    use AsController;

    public const array PLATFORMS = ['android', 'ios'];

    public const int DEFAULT_VERSION_CODE = 1;

    public const string DEFAULT_VERSION_NAME = '0.1.0';

    /**
     * @return array<string, array{minimum_version_code: int, latest_version_code: int, latest_version_name: string, update_url: string|null}>
     */
    public function handle(): array
    {
        $versions = config('app.mobile_versions');
        $versions = is_array($versions) ? $versions : [];

        return collect(self::PLATFORMS)
            ->mapWithKeys(fn (string $platform) => [$platform => $this->platformVersion(Arr::get($versions, $platform))])
            ->all();
    }

    /**
     * @return array{minimum_version_code: int, latest_version_code: int, latest_version_name: string, update_url: string|null}
     */
    private function platformVersion(mixed $platformVersion): array
    {
        $platformVersion = is_array($platformVersion) ? $platformVersion : [];

        return [
            'minimum_version_code' => $this->versionCode(Arr::get($platformVersion, 'minimum_version_code')),
            'latest_version_code'  => $this->versionCode(Arr::get($platformVersion, 'latest_version_code')),
            'latest_version_name'  => $this->versionName(Arr::get($platformVersion, 'latest_version_name')),
            'update_url'           => $this->updateUrl(Arr::get($platformVersion, 'update_url')),
        ];
    }

    private function versionCode(mixed $versionCode): int
    {
        $versionCode = filter_var($versionCode, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $versionCode === false ? self::DEFAULT_VERSION_CODE : $versionCode;
    }

    private function versionName(mixed $versionName): string
    {
        $versionName = is_scalar($versionName) ? trim((string) $versionName) : '';

        return $versionName === '' ? self::DEFAULT_VERSION_NAME : $versionName;
    }

    private function updateUrl(mixed $updateUrl): ?string
    {
        $updateUrl = is_string($updateUrl) ? trim($updateUrl) : '';

        return filter_var($updateUrl, FILTER_VALIDATE_URL) === false ? null : $updateUrl;
    }

    public function asController(): JsonResponse
    {
        return response()->json(['data' => $this->handle()]);
    }
}
