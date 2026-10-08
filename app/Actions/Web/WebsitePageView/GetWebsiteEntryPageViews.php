<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsitePageView;

use App\Actions\Web\WebsiteVisitor\UpdateWebsiteVisitor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteEntryPageViews
{
    use AsObject;

    public function handle(string $connection = 'aiku_no_sticky'): Builder
    {
        $maxIdleSeconds = UpdateWebsiteVisitor::MAX_IDLE_SECONDS;

        return DB::connection($connection)->table('website_page_views as entry_views')
            ->join('website_visitors as entry_visitors', 'entry_visitors.id', '=', 'entry_views.website_visitor_id')
            ->whereNotExists(
                fn ($query) => $query->select(DB::raw(1))
                    ->from('website_page_views as earlier_views')
                    ->whereColumn('earlier_views.website_visitor_id', 'entry_views.website_visitor_id')
                    ->whereColumn('earlier_views.id', '<', 'entry_views.id')
            )
            ->whereNotExists(
                fn ($query) => $query->select(DB::raw(1))
                    ->from('website_visitors as previous_visitors')
                    ->whereColumn('previous_visitors.website_id', 'entry_visitors.website_id')
                    ->whereColumn('previous_visitors.visitor_hash', 'entry_visitors.visitor_hash')
                    ->whereColumn('previous_visitors.id', '!=', 'entry_visitors.id')
                    ->whereColumn('previous_visitors.first_seen_at', '<', 'entry_visitors.first_seen_at')
                    ->whereRaw("previous_visitors.last_seen_at >= entry_visitors.first_seen_at - make_interval(secs => $maxIdleSeconds)")
            );
    }
}
