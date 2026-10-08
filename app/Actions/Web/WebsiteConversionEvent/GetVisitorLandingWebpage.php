<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent;

use App\Models\Web\WebsiteVisitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetVisitorLandingWebpage
{
    use AsObject;

    public const int LOOKBACK_DAYS = 30;

    private const int MAX_SESSIONS = 20;

    public function handle(WebsiteVisitor $visitor, ?Carbon $at = null): ?int
    {
        $at ??= now();

        $sessionIds = WebsiteVisitor::query()
            ->where('website_id', $visitor->website_id)
            ->where(function ($query) use ($visitor) {
                $query->where('id', $visitor->id)
                    ->orWhere('visitor_hash', $visitor->visitor_hash);

                if ($visitor->web_user_id) {
                    $query->orWhere('web_user_id', $visitor->web_user_id);
                }
            })
            ->where('first_seen_at', '<=', $at)
            ->where('first_seen_at', '>=', $at->copy()->subDays(self::LOOKBACK_DAYS))
            ->orderByDesc('first_seen_at')
            ->limit(self::MAX_SESSIONS)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return null;
        }

        $landingWebpages = DB::table('website_page_views')
            ->whereIn('website_visitor_id', $sessionIds)
            ->selectRaw('DISTINCT ON (website_visitor_id) website_visitor_id, webpage_id')
            ->orderBy('website_visitor_id')
            ->orderBy('id')
            ->get()
            ->pluck('webpage_id', 'website_visitor_id');

        foreach ($sessionIds as $sessionId) {
            if ($landingWebpages->get($sessionId)) {
                return (int) $landingWebpages->get($sessionId);
            }
        }

        return null;
    }
}
