<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SeoAiVisibilityTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW    = 'overview';
    case PROMPTS     = 'prompts';
    case CITED_PAGES = 'cited_pages';

    public function blueprint(): array
    {
        return match ($this) {
            SeoAiVisibilityTabsEnum::OVERVIEW => [
                'title'   => __('Share of voice'),
                'icon'    => 'fal fa-chart-line',
                'tooltip' => __('How often ChatGPT names and cites us next to each competitor, and the LLM Mentions figures.'),
            ],
            SeoAiVisibilityTabsEnum::PROMPTS => [
                'title'   => __('Prompts'),
                'icon'    => 'fal fa-comments',
                'tooltip' => __('The questions asked to ChatGPT every week and what it answered.'),
            ],
            SeoAiVisibilityTabsEnum::CITED_PAGES => [
                'title'   => __('Cited pages'),
                'icon'    => 'fal fa-link',
                'tooltip' => __('Our webpages ChatGPT cites, and the websites it cites most.'),
            ],
        };
    }
}
