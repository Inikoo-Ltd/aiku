<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The wallpaper behind staff chat messages: the one list of patterns and base colours, and which one a person sees in a conversation.
 * A pattern is a seamless SVG tile at resources/js/Components/Messaging/wallpapers/{key}.svg, so adding one is that file and one line in PATTERNS.
 * A picture that covers the whole chat instead of repeating (watercolour, INI-597) is also listed in COVER_PATTERNS in chatWallpaper.ts; it carries its own colours.
 * A pattern with a window (MM-DD to MM-DD, it may cross the new year) is offered only inside it, and whoever picked it goes back to their previous pattern once it closes.
 */
class StaffChatWallpaper
{
    public const string NONE = 'none';

    /** @var array<string, array{label: string, from?: string, to?: string}> */
    public const array PATTERNS = [
        'dots'  => ['label' => 'Dots'],
        'waves' => ['label' => 'Waves'],
        'watercolour' => ['label' => 'Watercolour'],
        'snow'  => ['label' => 'Winter lights', 'from' => '12-01', 'to' => '01-06'],
    ];

    /** Soft enough for dark text and solid bubbles in any organisation theme. */
    public const array COLOURS = [
        'sand'     => '#f5f2ec',
        'sage'     => '#eef3ec',
        'sky'      => '#ecf2f7',
        'blush'    => '#f7efef',
        'lavender' => '#f1eff7',
        'butter'   => '#f7f4e6',
        'mist'     => '#f0f2f3',
    ];

    public const array MODES = ['same', 'workplace', 'person'];

    /** rows: names and text in lines; bubbles: each message in its own bubble. */
    public const array STYLES = ['rows', 'bubbles'];

    public static function isOffered(string $pattern, ?Carbon $today = null): bool
    {
        $definition = self::PATTERNS[$pattern] ?? null;
        if (!$definition) {
            return false;
        }
        if (!isset($definition['from'])) {
            return true;
        }

        $day = ($today ?? now())->format('m-d');

        return $definition['from'] <= $definition['to']
            ? $day >= $definition['from'] && $day <= $definition['to']
            : $day >= $definition['from'] || $day <= $definition['to'];
    }

    public static function isSeasonal(string $pattern): bool
    {
        return isset(self::PATTERNS[$pattern]['from']);
    }

    /**
     * Which season a seasonal pattern is in, by the year its window opened, so last year's "seen" does not hide this year's "new" mark.
     */
    public static function seasonKey(string $pattern, ?Carbon $today = null): string
    {
        $today = $today ?? now();
        $from  = self::PATTERNS[$pattern]['from'] ?? '01-01';
        $year  = $today->format('m-d') >= $from ? $today->year : $today->year - 1;

        return "$pattern:$year";
    }

    /**
     * @return string[]
     */
    public static function openSeasonKeys(?Carbon $today = null): array
    {
        return collect(array_keys(self::PATTERNS))
            ->filter(fn (string $pattern) => self::isSeasonal($pattern) && self::isOffered($pattern, $today))
            ->map(fn (string $pattern) => self::seasonKey($pattern, $today))
            ->values()
            ->all();
    }

    /**
     * @return array{pattern: string, colour: string, mode: string, style: string, previous_pattern: string|null, seen: string[]}
     */
    public static function stored(User $user): array
    {
        $stored = (array) Arr::get($user->settings, 'chat_wallpaper', []);

        return [
            'pattern'          => (string) ($stored['pattern'] ?? self::NONE),
            'colour'           => (string) ($stored['colour'] ?? self::NONE),
            'mode'             => in_array($stored['mode'] ?? null, self::MODES, true) ? $stored['mode'] : 'same',
            'style'            => in_array($stored['style'] ?? null, self::STYLES, true) ? $stored['style'] : 'rows',
            'previous_pattern' => $stored['previous_pattern'] ?? null,
            'seen'             => array_values((array) ($stored['seen'] ?? [])),
        ];
    }

    /**
     * @return array{pattern: string|null, colour: string|null}
     */
    public static function general(User $user, ?Carbon $today = null): array
    {
        $stored = self::stored($user);

        return self::shown($stored['pattern'], $stored['colour'], $stored['previous_pattern'], $today);
    }

    /**
     * @param  array{pattern?: string|null, colour?: string|null}|null  $ownChoice
     *
     * @return array{pattern: string|null, colour: string|null}
     */
    public static function forConversation(User $viewer, StaffConversation $conversation, ?array $ownChoice, ?Carbon $today = null): array
    {
        $general = self::general($viewer, $today);

        if ($ownChoice) {
            $chosen = self::shown((string) ($ownChoice['pattern'] ?? self::NONE), (string) ($ownChoice['colour'] ?? self::NONE), null, $today);

            return $chosen['pattern'] || $chosen['colour'] ? $chosen : $general;
        }

        $mode = self::stored($viewer)['mode'];
        if ($mode === 'same' || $conversation->type !== 'dm' || $conversation->context_type) {
            return $general;
        }

        $other = $conversation->participants->first(fn (User $participant) => $participant->id !== $viewer->id);
        if (!$other) {
            return $general;
        }

        $colourKey = $mode === 'person'
            ? self::colourFor($other->id)
            : ($other->employed_in_organisation_id ? self::colourFor($other->employed_in_organisation_id) : null);

        return $colourKey ? ['pattern' => $general['pattern'], 'colour' => self::COLOURS[$colourKey]] : $general;
    }

    /**
     * @return array{pattern: string|null, colour: string|null}
     */
    public static function forSlackChannel(User $viewer, int $slackChannelId, ?Carbon $today = null): array
    {
        $ownChoice = Arr::get($viewer->settings, "chat_wallpaper_channels.$slackChannelId");
        if ($ownChoice) {
            $chosen = self::shown((string) ($ownChoice['pattern'] ?? self::NONE), (string) ($ownChoice['colour'] ?? self::NONE), null, $today);
            if ($chosen['pattern'] || $chosen['colour']) {
                return $chosen;
            }
        }

        return self::general($viewer, $today);
    }

    public static function colourFor(int $id): string
    {
        $keys = array_keys(self::COLOURS);

        return $keys[$id % count($keys)];
    }

    /**
     * What the picker offers today: patterns inside their window, base colours, the "new" mark for seasons the person has not seen, and how many people use each pattern.
     *
     * @return array{patterns: array<int, array{key: string, label: string, is_seasonal: bool, is_new: bool, users: int}>, colours: array<int, array{key: string, hex: string}>, stored: array<string, mixed>}
     */
    public static function catalogue(User $user, bool $withUsage = false): array
    {
        $stored = self::stored($user);
        $usage  = $withUsage ? self::usage() : [];

        return [
            'patterns' => collect(self::PATTERNS)
                ->filter(fn (array $definition, string $key) => self::isOffered($key))
                ->map(fn (array $definition, string $key) => [
                    'key'         => $key,
                    'label'       => $definition['label'],
                    'is_seasonal' => self::isSeasonal($key),
                    'is_new'      => self::isSeasonal($key) && !in_array(self::seasonKey($key), $stored['seen'], true),
                    'users'       => $usage[$key] ?? 0,
                ])->values()->all(),
            'colours'  => collect(self::COLOURS)->map(fn (string $hex, string $key) => ['key' => $key, 'hex' => $hex])->values()->all(),
            'stored'   => [
                'pattern' => $stored['pattern'],
                'colour'  => $stored['colour'],
                'mode'    => $stored['mode'],
                'style'   => $stored['style'],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function usage(): array
    {
        return DB::table('users')
            ->where('status', true)
            ->whereRaw("settings->'chat_wallpaper'->>'pattern' IS NOT NULL")
            ->selectRaw("settings->'chat_wallpaper'->>'pattern' AS pattern, count(*) AS users")
            ->groupBy('pattern')
            ->pluck('users', 'pattern')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array{pattern: string|null, colour: string|null}
     */
    private static function shown(string $pattern, string $colour, ?string $previousPattern, ?Carbon $today): array
    {
        if ($pattern !== self::NONE && !self::isOffered($pattern, $today)) {
            $pattern = $previousPattern && self::isOffered($previousPattern, $today) ? $previousPattern : self::NONE;
        }

        return [
            'pattern' => $pattern === self::NONE ? null : $pattern,
            'colour'  => self::COLOURS[$colour] ?? null,
        ];
    }
}
