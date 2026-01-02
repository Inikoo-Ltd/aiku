<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Models\Web\WebsiteVisitor;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWebsiteVisitor
{
    use AsAction;

    public function handle(WebsiteVisitor $visitor, string $currentUrl): WebsiteVisitor
    {
        $now = now();
        $duration = $now->timestamp - $visitor->first_seen_at->timestamp;

        $visitor->last_seen_at = $now;
        $visitor->page_views = $visitor->page_views + 1;
        $visitor->duration_seconds = $duration;
        $visitor->exit_page = $currentUrl;
        $visitor->is_bounce = false;
        $visitor->save();

        $cacheKey = "visitor:session:{$visitor->session_id}:{$visitor->website_id}";
        Cache::put($cacheKey, $visitor, 1800);

        return $visitor;
    }
}
