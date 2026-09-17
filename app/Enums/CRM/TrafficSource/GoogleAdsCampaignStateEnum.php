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

    public static function labels(): array
    {
        return [
            self::IN_PROCESS->value        => __('In process'),
            self::PUBLISHED_PAUSED->value  => __('Published, paused'),
            self::PUBLISHED_SERVING->value => __('Published, serving'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            self::IN_PROCESS->value        => ['icon' => 'fal fa-seedling', 'class' => 'text-indigo-500', 'tooltip' => __('In process, being written in Aiku and not at Google yet')],
            self::PUBLISHED_PAUSED->value  => ['icon' => 'fal fa-pause', 'class' => 'text-indigo-500', 'tooltip' => __('Created at Google and paused, spending nothing')],
            self::PUBLISHED_SERVING->value => ['icon' => 'fal fa-paper-plane', 'class' => 'text-green-600', 'tooltip' => __('Running at Google')],
        ];
    }

    /**
     * What each state means for the person reading it, rather than what it is called.
     */
    public static function descriptions(): array
    {
        return [
            self::IN_PROCESS->value        => __('Only in Aiku. Nothing has been sent to Google and nothing can spend.'),
            self::PUBLISHED_PAUSED->value  => __('Google has the campaign and is holding it. It will not show or spend until it is switched on.'),
            self::PUBLISHED_SERVING->value => __('Google is showing these ads and the budget is being spent.'),
        ];
    }

    /** The column holding when this state was reached, which is what the timeline is drawn from. */
    public function timestampColumn(): string
    {
        return match ($this) {
            self::IN_PROCESS        => 'in_process_at',
            self::PUBLISHED_PAUSED  => 'published_at',
            self::PUBLISHED_SERVING => 'serving_at',
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

    /**
     * Google's own word for a published campaign, mapped to the two states that mean anything here.
     * Everything that is not actively serving is holding, whatever Google's reason for it.
     */
    public static function fromGoogleStatus(?string $status): self
    {
        return in_array($status, ['ELIGIBLE', 'ENABLED', 'LIMITED'], true)
            ? self::PUBLISHED_SERVING
            : self::PUBLISHED_PAUSED;
    }
}
