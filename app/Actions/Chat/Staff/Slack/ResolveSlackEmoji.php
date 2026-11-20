<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackEmoji;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Turns a Slack emoji name (":wave::skin-tone-3:", "wave::skin-tone-3", "+1", a custom one) into a Unicode character
 * or aiku's copy of a custom emoji image; null when unknown, to be shown as the name. STANDARD holds the common Slack names only.
 */
class ResolveSlackEmoji
{
    use AsAction;

    public const int MAX_ALIAS_HOPS = 5;

    public const array SKIN_TONES = [
        'skin-tone-2' => "\u{1F3FB}",
        'skin-tone-3' => "\u{1F3FC}",
        'skin-tone-4' => "\u{1F3FD}",
        'skin-tone-5' => "\u{1F3FE}",
        'skin-tone-6' => "\u{1F3FF}",
    ];

    public const array STANDARD = [
        '+1'                             => '👍',
        'thumbsup'                       => '👍',
        '-1'                             => '👎',
        'thumbsdown'                     => '👎',
        'white_check_mark'               => '✅',
        'heavy_check_mark'               => '✔️',
        'ballot_box_with_check'          => '☑️',
        'x'                              => '❌',
        'heavy_multiplication_x'         => '✖️',
        'negative_squared_cross_mark'    => '❎',
        'warning'                        => '⚠️',
        'no_entry'                       => '⛔',
        'no_entry_sign'                  => '🚫',
        'exclamation'                    => '❗',
        'heavy_exclamation_mark'         => '❗',
        'question'                       => '❓',
        'grey_question'                  => '❔',
        'bangbang'                       => '‼️',
        'heart'                          => '❤️',
        'orange_heart'                   => '🧡',
        'yellow_heart'                   => '💛',
        'green_heart'                    => '💚',
        'blue_heart'                     => '💙',
        'purple_heart'                   => '💜',
        'black_heart'                    => '🖤',
        'white_heart'                    => '🤍',
        'broken_heart'                   => '💔',
        'two_hearts'                     => '💕',
        'sparkling_heart'                => '💖',
        'heart_eyes'                     => '😍',
        'smile'                          => '😄',
        'smiley'                         => '😃',
        'grinning'                       => '😀',
        'grin'                           => '😁',
        'laughing'                       => '😆',
        'satisfied'                      => '😆',
        'sweat_smile'                    => '😅',
        'joy'                            => '😂',
        'rolling_on_the_floor_laughing'  => '🤣',
        'slightly_smiling_face'          => '🙂',
        'upside_down_face'               => '🙃',
        'wink'                           => '😉',
        'blush'                          => '😊',
        'innocent'                       => '😇',
        'smiling_face_with_3_hearts'     => '🥰',
        'kissing_heart'                  => '😘',
        'yum'                            => '😋',
        'stuck_out_tongue'               => '😛',
        'stuck_out_tongue_winking_eye'   => '😜',
        'zany_face'                      => '🤪',
        'thinking_face'                  => '🤔',
        'face_with_raised_eyebrow'       => '🤨',
        'neutral_face'                   => '😐',
        'expressionless'                 => '😑',
        'no_mouth'                       => '😶',
        'smirk'                          => '😏',
        'unamused'                       => '😒',
        'face_with_rolling_eyes'         => '🙄',
        'grimacing'                      => '😬',
        'relieved'                       => '😌',
        'pensive'                        => '😔',
        'sleepy'                         => '😪',
        'sleeping'                       => '😴',
        'mask'                           => '😷',
        'nerd_face'                      => '🤓',
        'sunglasses'                     => '😎',
        'partying_face'                  => '🥳',
        'confused'                       => '😕',
        'worried'                        => '😟',
        'slightly_frowning_face'         => '🙁',
        'open_mouth'                     => '😮',
        'hushed'                         => '😯',
        'astonished'                     => '😲',
        'flushed'                        => '😳',
        'pleading_face'                  => '🥺',
        'cry'                            => '😢',
        'sob'                            => '😭',
        'scream'                         => '😱',
        'disappointed'                   => '😞',
        'sweat'                          => '😓',
        'weary'                          => '😩',
        'tired_face'                     => '😫',
        'triumph'                        => '😤',
        'rage'                           => '😡',
        'angry'                          => '😠',
        'exploding_head'                 => '🤯',
        'face_palm'                      => '🤦',
        'shrug'                          => '🤷',
        'hugging_face'                   => '🤗',
        'shushing_face'                  => '🤫',
        'skull'                          => '💀',
        'poop'                           => '💩',
        'hankey'                         => '💩',
        'clown_face'                     => '🤡',
        'see_no_evil'                    => '🙈',
        'hear_no_evil'                   => '🙉',
        'speak_no_evil'                  => '🙊',
        'wave'                           => '👋',
        'raised_hands'                   => '🙌',
        'clap'                           => '👏',
        'pray'                           => '🙏',
        'muscle'                         => '💪',
        'ok_hand'                        => '👌',
        'v'                              => '✌️',
        'crossed_fingers'                => '🤞',
        'handshake'                      => '🤝',
        'point_up'                       => '☝️',
        'point_up_2'                     => '👆',
        'point_down'                     => '👇',
        'point_left'                     => '👈',
        'point_right'                    => '👉',
        'raised_hand'                    => '✋',
        'hand'                           => '✋',
        'call_me_hand'                   => '🤙',
        'fist'                           => '✊',
        'facepunch'                      => '👊',
        'punch'                          => '👊',
        'writing_hand'                   => '✍️',
        'saluting_face'                  => '🫡',
        'eyes'                           => '👀',
        'eye'                            => '👁️',
        'brain'                          => '🧠',
        'tada'                           => '🎉',
        'confetti_ball'                  => '🎊',
        'balloon'                        => '🎈',
        'gift'                           => '🎁',
        'birthday'                       => '🎂',
        'trophy'                         => '🏆',
        'medal'                          => '🎖️',
        'fire'                           => '🔥',
        '100'                            => '💯',
        'star'                           => '⭐',
        'star2'                          => '🌟',
        'sparkles'                       => '✨',
        'zap'                            => '⚡',
        'boom'                           => '💥',
        'rocket'                         => '🚀',
        'bulb'                           => '💡',
        'mag'                            => '🔍',
        'lock'                           => '🔒',
        'unlock'                         => '🔓',
        'key'                            => '🔑',
        'bell'                           => '🔔',
        'pushpin'                        => '📌',
        'round_pushpin'                  => '📍',
        'paperclip'                      => '📎',
        'memo'                           => '📝',
        'pencil'                         => '📝',
        'pencil2'                        => '✏️',
        'clipboard'                      => '📋',
        'calendar'                       => '📆',
        'date'                           => '📅',
        'email'                          => '✉️',
        'envelope'                       => '✉️',
        'phone'                          => '☎️',
        'telephone_receiver'             => '📞',
        'iphone'                         => '📱',
        'computer'                       => '💻',
        'package'                        => '📦',
        'truck'                          => '🚚',
        'articulated_lorry'              => '🚛',
        'airplane'                       => '✈️',
        'ship'                           => '🚢',
        'moneybag'                       => '💰',
        'money_with_wings'               => '💸',
        'dollar'                         => '💵',
        'euro'                           => '💶',
        'pound'                          => '💷',
        'credit_card'                    => '💳',
        'chart_with_upwards_trend'       => '📈',
        'chart_with_downwards_trend'     => '📉',
        'bar_chart'                      => '📊',
        'hourglass'                      => '⌛',
        'hourglass_flowing_sand'         => '⏳',
        'alarm_clock'                    => '⏰',
        'stopwatch'                      => '⏱️',
        'wrench'                         => '🔧',
        'hammer'                         => '🔨',
        'hammer_and_wrench'              => '🛠️',
        'gear'                           => '⚙️',
        'link'                           => '🔗',
        'construction'                   => '🚧',
        'rotating_light'                 => '🚨',
        'sos'                            => '🆘',
        'new'                            => '🆕',
        'ok'                             => '🆗',
        'free'                           => '🆓',
        'information_source'             => 'ℹ️',
        'arrow_right'                    => '➡️',
        'arrow_left'                     => '⬅️',
        'arrow_up'                       => '⬆️',
        'arrow_down'                     => '⬇️',
        'arrows_counterclockwise'        => '🔄',
        'repeat'                         => '🔁',
        'heavy_plus_sign'                => '➕',
        'heavy_minus_sign'               => '➖',
        'red_circle'                     => '🔴',
        'large_orange_circle'            => '🟠',
        'large_yellow_circle'            => '🟡',
        'large_green_circle'             => '🟢',
        'large_blue_circle'              => '🔵',
        'large_purple_circle'            => '🟣',
        'black_circle'                   => '⚫',
        'white_circle'                   => '⚪',
        'coffee'                         => '☕',
        'tea'                            => '🍵',
        'beer'                           => '🍺',
        'beers'                          => '🍻',
        'wine_glass'                     => '🍷',
        'champagne'                      => '🍾',
        'pizza'                          => '🍕',
        'cake'                           => '🍰',
        'cookie'                         => '🍪',
        'sunny'                          => '☀️',
        'cloud'                          => '☁️',
        'umbrella'                       => '☂️',
        'snowflake'                      => '❄️',
        'rainbow'                        => '🌈',
        'earth_africa'                   => '🌍',
        'earth_americas'                 => '🌎',
        'earth_asia'                     => '🌏',
        'globe_with_meridians'           => '🌐',
        'house'                          => '🏠',
        'office'                         => '🏢',
        'factory'                        => '🏭',
        'shopping_trolley'               => '🛒',
        'label'                          => '🏷️',
        'gem'                            => '💎',
        'candle'                         => '🕯️',
        'crystal_ball'                   => '🔮',
        'herb'                           => '🌿',
        'seedling'                       => '🌱',
        'four_leaf_clover'               => '🍀',
        'rose'                           => '🌹',
        'sunflower'                      => '🌻',
        'cherry_blossom'                 => '🌸',
        'dog'                            => '🐶',
        'cat'                            => '🐱',
        'unicorn_face'                   => '🦄',
        'raising_hand'                   => '🙋',
        'man-raising-hand'               => '🙋‍♂️',
        'woman-raising-hand'             => '🙋‍♀️',
        'man-facepalming'                => '🤦‍♂️',
        'woman-facepalming'              => '🤦‍♀️',
        'man-shrugging'                  => '🤷‍♂️',
        'woman-shrugging'                => '🤷‍♀️',
        'flag-gb'                        => '🇬🇧',
        'gb'                             => '🇬🇧',
        'uk'                             => '🇬🇧',
        'flag-de'                        => '🇩🇪',
        'de'                             => '🇩🇪',
        'flag-fr'                        => '🇫🇷',
        'fr'                             => '🇫🇷',
        'flag-es'                        => '🇪🇸',
        'es'                             => '🇪🇸',
        'flag-it'                        => '🇮🇹',
        'it'                             => '🇮🇹',
        'flag-sk'                        => '🇸🇰',
        'flag-cz'                        => '🇨🇿',
        'flag-pl'                        => '🇵🇱',
        'flag-hu'                        => '🇭🇺',
        'flag-pt'                        => '🇵🇹',
        'flag-nl'                        => '🇳🇱',
        'flag-ie'                        => '🇮🇪',
        'flag-my'                        => '🇲🇾',
        'flag-id'                        => '🇮🇩',
        'flag-in'                        => '🇮🇳',
        'flag-cn'                        => '🇨🇳',
        'cn'                             => '🇨🇳',
        'flag-us'                        => '🇺🇸',
        'us'                             => '🇺🇸',
        'flag-eu'                        => '🇪🇺',
        'checkered_flag'                 => '🏁',
        'triangular_flag_on_post'        => '🚩',
    ];

    /**
     * @return array{unicode: string}|array{url: string|null, name: string}|null
     */
    public function handle(string $name): ?array
    {
        return $this->many([$name])[$name];
    }

    /**
     * Resolves a whole page of names at once: one slack_emojis query per alias hop, never one per name.
     *
     * @param  iterable<string>  $names
     * @return array<string, array{unicode: string}|array{url: string|null, name: string}|null>
     */
    public function many(iterable $names): array
    {
        $parsed = [];
        foreach ($names as $name) {
            $parts = array_values(array_filter(explode(':', $name)));
            $parsed[$name] = $parts ? ['base' => $parts[0], 'tone' => self::SKIN_TONES[$parts[1] ?? ''] ?? ''] : null;
        }

        $customs = [];
        $targets = [];
        $pending = array_values(array_unique(array_column(array_filter($parsed), 'base')));
        for ($hops = 0; $hops <= self::MAX_ALIAS_HOPS && $pending !== []; $hops++) {
            $found = SlackEmoji::whereIn('name', $pending)->with('copy')->get()->keyBy('name');
            $next  = [];
            foreach ($pending as $base) {
                $emoji = $found[$base] ?? null;
                if (!$emoji) {
                    continue;
                }
                if ($emoji->alias_for) {
                    $targets[$base] = $emoji->alias_for;
                    $next[]         = $emoji->alias_for;
                } else {
                    $customs[$base] = ['url' => $emoji->copy?->fullResolutionUrls()['original_url'], 'name' => $emoji->name];
                }
            }
            $pending = array_values(array_diff(array_unique($next), array_keys($customs), array_keys($targets)));
        }

        $resolved = [];
        foreach ($parsed as $name => $entry) {
            $resolved[$name] = $entry ? $this->resolveBase($entry['base'], $entry['tone'], $customs, $targets) : null;
        }

        return $resolved;
    }

    /**
     * @param  array<string, array{url: string|null, name: string}>  $customs
     * @param  array<string, string>  $targets
     * @return array{unicode: string}|array{url: string|null, name: string}|null
     */
    private function resolveBase(string $base, string $tone, array $customs, array $targets): ?array
    {
        for ($hops = 0; $hops <= self::MAX_ALIAS_HOPS; $hops++) {
            if (isset($customs[$base])) {
                return $customs[$base];
            }
            if (!isset($targets[$base])) {
                break;
            }
            $base = $targets[$base];
        }

        $unicode = self::STANDARD[$base] ?? null;

        if ($unicode === null) {
            return null;
        }

        return ['unicode' => $tone ? str_replace("\u{FE0F}", '', $unicode).$tone : $unicode];
    }

}
