<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoRankingAlert;
use App\Notifications\SeoRankingAlertsNotification;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tells the people watching a keyword when it fell, once the Google checks have been collected:
 * one notification per person and shop. Every alert is marked as handled, watched or not.
 */
class NotifySeoRankingAlerts
{
    use AsAction;

    public string $jobQueue = 'default';

    public function handle(): int
    {
        $alerts = SeoRankingAlert::query()
            ->whereNull('notified_at')
            ->with('trackedKeyword')
            ->get();

        if ($alerts->isEmpty()) {
            return 0;
        }

        $watchers = DB::table('seo_keyword_watchers')
            ->whereIn('tracked_keyword_id', $alerts->pluck('tracked_keyword_id')->unique())
            ->get(['tracked_keyword_id', 'user_id'])
            ->groupBy('user_id');

        $users = User::whereIn('id', $watchers->keys())->where('status', true)->get()->keyBy('id');
        $shops = Shop::whereIn('id', $alerts->pluck('trackedKeyword.shop_id')->unique())->with('organisation')->get()->keyBy('id');
        $sent  = 0;

        foreach ($watchers as $userId => $watched) {
            $user = $users->get($userId);

            if (!$user) {
                continue;
            }

            $alerts->whereIn('tracked_keyword_id', $watched->pluck('tracked_keyword_id'))
                ->groupBy('trackedKeyword.shop_id')
                ->each(function ($shopAlerts, $shopId) use ($user, $shops, &$sent) {
                    $user->notify(new SeoRankingAlertsNotification($shops[$shopId], $shopAlerts->values()));
                    $sent++;
                });
        }

        SeoRankingAlert::whereIn('id', $alerts->pluck('id'))->update(['notified_at' => now()]);

        return $sent;
    }
}
