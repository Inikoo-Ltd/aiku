<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Marketing;

use App\Actions\CRM\SocialMedia\GetSocialMediaPerformance;
use App\Actions\OrgAction;
use App\Enums\CRM\SocialMedia\SocialMediaPlatformEnum;
use App\Enums\CRM\SocialMedia\SocialMediaPostTypeEnum;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSocialMediaPosts extends OrgAction
{
    use WithSocialMediaScope;

    public function htmlResponse(ActionRequest $request): StreamedResponse
    {
        if ($request->string('report')->toString() === 'summary') {
            return $this->summary($request);
        }

        $query    = GetSocialMediaPerformance::make()->exportQuery($this->parent, $this->periodFrom, $this->periodTo, $this->filters($request));
        $filename = 'social-media-posts-'.($this->periodFrom?->toDateString() ?? 'all').'-'.($this->periodTo?->toDateString() ?? now()->toDateString()).'.csv';

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                __('Published'), __('Platform'), __('Account'), __('Shop'), __('Country'), __('Format'), __('Description'), __('Link'),
                __('Reach'), __('Impressions'), __('Reactions'), __('Comments'), __('Shares'), __('Saves'), __('Link clicks'), __('Follows'),
                __('Engagements'), __('Engagement rate %'),
            ]);

            foreach ($query->cursor() as $post) {
                fputcsv($output, [
                    Carbon::parse($post->published_at)->toDateTimeString(),
                    SocialMediaPlatformEnum::labels()[$post->platform] ?? $post->platform,
                    $post->account_name,
                    $post->shop_name,
                    $post->country_code,
                    SocialMediaPostTypeEnum::labels()[$post->type] ?? $post->type,
                    $post->description,
                    $post->permalink,
                    $post->reach,
                    $post->impressions,
                    $post->reactions,
                    $post->comments,
                    $post->shares,
                    $post->saves,
                    $post->link_clicks,
                    $post->follows,
                    $post->engagements,
                    $post->reach > 0 ? round($post->engagements / $post->reach * 100, 2) : null,
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * The period on one sheet for monthly reporting: every headline figure beside the period it is
     * compared with, then the platforms, the accounts and the best posts.
     */
    private function summary(ActionRequest $request): StreamedResponse
    {
        $report   = GetSocialMediaPerformance::run($this->parent, $this->periodFrom, $this->periodTo, $this->filters($request));
        $filename = 'social-media-report-'.($report['from'] ?? 'all').'-'.($report['to'] ?? now()->toDateString()).'.csv';

        return response()->streamDownload(function () use ($report) {
            $output   = fopen('php://output', 'w');
            $current  = $report['kpis']['current'];
            $previous = $report['kpis']['previous'] ?? [];

            fputcsv($output, [__('Social media report'), $report['from'], $report['to']]);
            if ($report['previous_from']) {
                fputcsv($output, [__('Compared with'), $report['previous_from'], $report['previous_to']]);
            }
            if ($report['insight']) {
                fputcsv($output, [__('Insight'), $report['insight']]);
            }

            fputcsv($output, []);
            fputcsv($output, [__('Figure'), __('This period'), __('Compared period'), __('Change %')]);
            foreach ([
                'posts'             => __('Posts'),
                'views'             => __('Views (all)'),
                'organic_views'     => __('Organic views'),
                'ad_views'          => __('Views from ads'),
                'unsplit_views'     => __('Views not split (synced before the split)'),
                'avg_daily_viewers' => __('Viewers per day'),
                'interactions'      => __('Interactions'),
                'interaction_rate'  => __('Interaction rate %'),
                'link_clicks'       => __('Link clicks'),
                'visits'            => __('Profile visits'),
                'new_follows'       => __('New follows'),
                'followers'         => __('Followers'),
                'spend'             => __('Paid spend'),
                'revenue'           => __('Attributed revenue · Aiku'),
                'orders'            => __('Orders · Aiku'),
                'roas'              => __('ROAS · Aiku'),
                'cpa'               => __('Cost per order · Aiku'),
                'meta_purchases'    => __('Purchases · Meta'),
                'meta_value'        => __('Purchase value · Meta'),
                'meta_roas'         => __('ROAS · Meta'),
                'ad_impressions'    => __('Ad impressions'),
                'ad_clicks'         => __('Ad clicks'),
                'ctr'               => __('CTR %'),
                'meta_registrations'         => __('Registrations · Meta'),
                'meta_cost_per_registration' => __('Cost per registration · Meta'),
            ] as $key => $label) {
                $now    = $current[$key] ?? null;
                $before = $key === 'followers' && ($previous['followers_accounts'] ?? null) !== ($current['followers_accounts'] ?? null) ? null : ($previous[$key] ?? null);
                fputcsv($output, [$label, $now, $before, $now !== null && $before ? round(($now - $before) / abs($before) * 100, 1) : null]);
            }

            fputcsv($output, []);
            fputcsv($output, [__('Platform'), __('Views (all)'), __('Share of views %'), __('Organic views'), __('Views from ads'), __('Posts'), __('Followers')]);
            foreach ($report['platforms'] as $platform) {
                fputcsv($output, [$platform['label'], $platform['views'], $platform['views_share'], $platform['organic_views'], $platform['ad_views'], $platform['posts'], $platform['followers']]);
            }

            fputcsv($output, []);
            fputcsv($output, [__('Account'), __('Platform'), __('Brand'), __('Market'), __('Followers'), __('Posts'), __('Views (all)'), __('Organic views'), __('Views from ads'), __('Interactions'), __('Link clicks')]);
            foreach ($report['accounts'] as $account) {
                fputcsv($output, [$account['name'], $account['platform_label'], $account['shop_name'], $account['country_code'], $account['followers'], $account['posts'], $account['views'], $account['organic_views'], $account['ad_views'], $account['interactions'], $account['link_clicks']]);
            }

            fputcsv($output, []);
            fputcsv($output, [__('Top posts'), __('Published'), __('Platform'), __('Format'), __('Viewers'), __('Engagements'), __('Engagement rate %'), __('Link')]);
            foreach (array_slice($report['top_posts'], 0, 10) as $post) {
                fputcsv($output, [$post['description'], Carbon::parse($post['published_at'])->toDateString(), $post['platform_label'], $post['type_label'], $post['reach'], $post['engagements'], $post['engagement_rate'], $post['permalink']]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
