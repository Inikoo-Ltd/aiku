<?php

namespace App\Services;

use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StickyBetweenDates
{
    private const SETTINGS_KEY  = 'table_between_dates';
    private const RANGE_PATTERN = '/^\d{8}-\d{8}$/';

    public static function filterKey(string $column): string
    {
        return Str::afterLast($column, '.');
    }

    /**
     * @param  array<string, string>  $filters
     * @param  array<int, string>     $allowedColumns
     *
     * @return array<string, string>
     */
    public static function apply(array $filters, array $allowedColumns, ?string $defaultColumn, ?string $prefix = null): array
    {
        $user = self::stickyUser();

        if ($prefix || !$user) {
            return $filters;
        }

        $requestedKey = self::requestedKey($filters, $allowedColumns);

        if ($requestedKey !== null) {
            $range = self::sanitiseRange($filters[$requestedKey]);

            if ($range === null) {
                self::forget($user);
            } else {
                self::remember($user, $range);
            }

            return $filters;
        }

        $storedRange = self::sanitiseRange(Arr::get($user->settings ?? [], self::SETTINGS_KEY));

        if ($storedRange === null || $defaultColumn === null) {
            return $filters;
        }

        $filters[self::filterKey($defaultColumn)] = $storedRange;

        return $filters;
    }

    /**
     * @param  array<int, string>  $declaredColumns
     *
     * @return array{column: string, range: string}|null
     */
    public static function resolve(array $declaredColumns, ?string $prefix = null): ?array
    {
        $defaultColumn = $declaredColumns[0] ?? null;

        if ($defaultColumn === null) {
            return null;
        }

        $filters      = request()->input(($prefix ? $prefix . '_' : '') . 'between', []);
        $requestedKey = self::requestedKey($filters, array_merge($declaredColumns, ['created_at', 'updated_at']));

        if ($requestedKey !== null) {
            $range = self::sanitiseRange($filters[$requestedKey]);

            return $range === null ? null : ['column' => $requestedKey, 'range' => $range];
        }

        $user = self::stickyUser();

        if (!$user) {
            return null;
        }

        $storedRange = self::sanitiseRange(Arr::get($user->settings ?? [], self::SETTINGS_KEY));

        return $storedRange === null ? null : ['column' => self::filterKey($defaultColumn), 'range' => $storedRange];
    }

    /**
     * @param  array<string, string>  $filters
     * @param  array<int, string>     $allowedColumns
     */
    private static function requestedKey(array $filters, array $allowedColumns): ?string
    {
        foreach ($allowedColumns as $column) {
            $filterKey = self::filterKey($column);

            if (array_key_exists($filterKey, $filters)) {
                return $filterKey;
            }
        }

        return null;
    }

    private static function sanitiseRange(mixed $range): ?string
    {
        if (!is_string($range)) {
            return null;
        }

        $range = trim($range);

        return preg_match(self::RANGE_PATTERN, $range) ? $range : null;
    }

    private static function remember(User $user, string $range): void
    {
        if (Arr::get($user->settings ?? [], self::SETTINGS_KEY) === $range) {
            return;
        }

        self::storeRange($user, $range);
    }

    private static function forget(User $user): void
    {
        if (Arr::get($user->settings ?? [], self::SETTINGS_KEY) === null) {
            return;
        }

        self::storeRange($user, null);
    }

    private static function storeRange(User $user, ?string $range): void
    {
        $settings = $user->settings ?? [];

        if ($range === null) {
            Arr::forget($settings, self::SETTINGS_KEY);
        } else {
            Arr::set($settings, self::SETTINGS_KEY, $range);
        }

        $user->updateQuietly(['settings' => $settings]);
    }

    private static function stickyUser(): ?User
    {
        if (!request()->routeIs('grp.*')) {
            return null;
        }

        $user = Auth::guard('web')->user();

        return $user instanceof User ? $user : null;
    }
}
