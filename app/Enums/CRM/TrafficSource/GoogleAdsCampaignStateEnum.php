<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\TrafficSource;

use App\Enums\EnumHelperTrait;

/**
 * Where a Google Ads campaign stands between being written in Aiku and showing ads to people.
 *
 * Three states and two doors between them. A campaign is built here first and exists nowhere else;
 * publishing creates it at Google, always paused, because a campaign that starts spending the moment
 * it is submitted has no undo; switching it on is a separate, deliberate act.
 *
 * Going back is not a state. Once a campaign exists at Google it is edited there or through the
 * campaign page, never returned to a draft, because Google has an id for it that Aiku cannot take
 * back. Pausing a published campaign returns it to `published_paused`, which is the same door in
 * reverse rather than a fourth state.
 */
enum GoogleAdsCampaignStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case PUBLISHED_PAUSED = 'published_paused';
    case PUBLISHED_SERVING = 'published_serving';
    case NOT_SYNCED = 'not_synced';

    public static function labels(): array
    {
        return [
            self::IN_PROCESS->value        => __('In process'),
            self::PUBLISHED_PAUSED->value  => __('Published, paused'),
            self::PUBLISHED_SERVING->value => __('Published, serving'),
            self::NOT_SYNCED->value        => __('Not synced from Google'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            self::IN_PROCESS->value        => ['icon' => 'fal fa-seedling', 'class' => 'text-indigo-500', 'tooltip' => __('In process, being written in Aiku and not at Google yet')],
            self::PUBLISHED_PAUSED->value  => ['icon' => 'fal fa-pause', 'class' => 'text-indigo-500', 'tooltip' => __('Created at Google and paused, spending nothing')],
            self::PUBLISHED_SERVING->value => ['icon' => 'fal fa-paper-plane', 'class' => 'text-green-600', 'tooltip' => __('Running at Google')],
            self::NOT_SYNCED->value        => ['icon' => 'fal fa-question-circle', 'class' => 'text-gray-400', 'tooltip' => __('Status not read from Google, the shop is not connected to the Google Ads API, so it may still be spending')],
        ];
    }

    /** The column holding when this state was reached, which is what the timeline is drawn from. */
    public function timestampColumn(): string
    {
        return match ($this) {
            self::IN_PROCESS        => 'in_process_at',
            self::PUBLISHED_PAUSED  => 'published_at',
            self::PUBLISHED_SERVING => 'serving_at',
            self::NOT_SYNCED        => 'published_at',
        };
    }

    /** Whether the campaign is still Aiku's alone, and so still freely editable here. */
    public function isInProcess(): bool
    {
        return $this === self::IN_PROCESS;
    }

    public function isPublished(): bool
    {
        return $this !== self::IN_PROCESS;
    }

    public function isNotSynced(): bool
    {
        return $this === self::NOT_SYNCED;
    }

    /**
     * @return array<int, self>
     */
    public static function timelineCases(): array
    {
        return [self::IN_PROCESS, self::PUBLISHED_PAUSED, self::PUBLISHED_SERVING];
    }

    /**
     * Google's own word for a published campaign, mapped to the two states that mean anything here.
     * Everything that is not actively serving is holding, whatever Google's reason for it. No word at
     * all means Google was never asked, which says nothing about whether it is spending.
     */
    public static function fromGoogleStatus(?string $status): self
    {
        if (blank($status)) {
            return self::NOT_SYNCED;
        }

        return in_array($status, ['ELIGIBLE', 'ENABLED', 'LIMITED'], true)
            ? self::PUBLISHED_SERVING
            : self::PUBLISHED_PAUSED;
    }
}
