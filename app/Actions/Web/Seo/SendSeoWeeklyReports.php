<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Web\SeoReportSubscription;
use App\Notifications\SeoWeeklyReportNotification;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mondays, after the weekly checks: the SEO report of each subscribed shop, or of every website, to
 * the people who asked for it. A shop's report is built once however many people get it.
 */
class SendSeoWeeklyReports
{
    use AsAction;

    public string $commandSignature = 'seo:send_weekly_reports';

    public string $commandDescription = 'Email the weekly SEO report to the people who subscribed';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public int $jobTries = 1;

    public function handle(): int
    {
        $subscriptions = SeoReportSubscription::query()
            ->whereHas('user', fn ($query) => $query->where('status', true))
            ->with(['user', 'shop.organisation', 'shop.website'])
            ->get();

        $reports = [];
        $sent    = 0;

        foreach ($subscriptions as $subscription) {
            $shop = $subscription->shop;
            $key  = $shop?->id ?? 'portfolio';

            $reports[$key] ??= $shop
                ? new SeoWeeklyReportNotification(
                    __('SEO this week · :shop', ['shop' => $shop->name]),
                    $shop->name,
                    GetSeoWeeklyReport::run($shop),
                    ['name' => 'grp.org.shops.show.seo.dashboard', 'parameters' => [$shop->organisation->slug, $shop->slug]]
                )
                : new SeoWeeklyReportNotification(
                    __('SEO this week · every website'),
                    group()?->name ?? 'Aiku',
                    $this->portfolio(),
                    ['name' => 'grp.websites.seo.portfolio', 'parameters' => []]
                );

            $subscription->user->notify($reports[$key]);
            $subscription->update(['last_sent_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    /**
     * @return array<int, array{title: string, lines: array<int, string>}>
     */
    private function portfolio(): array
    {
        $portfolio = GetSeoPortfolio::run();

        return [[
            'title' => __('Every live website, last :days days', ['days' => $portfolio['days']]),
            'lines' => collect($portfolio['websites'])
                ->sortByDesc('visitors')
                ->map(fn (array $row) => __(':domain: :visitors visitors:change', ['domain' => $row['domain'], 'visitors' => number_format($row['visitors']), 'change' => GetSeoWeeklyReport::change($row['visitors'], $row['previous_visitors'])])
                    .($row['clicks'] !== null ? ', '.__(':clicks Google clicks', ['clicks' => number_format($row['clicks'])]).GetSeoWeeklyReport::change($row['clicks'], $row['previous_clicks']) : '')
                    .($row['health'] !== null ? ', '.__('health :health%', ['health' => $row['health']]) : ''))
                ->values()
                ->all(),
        ]];
    }

    public function asCommand(Command $command): int
    {
        $command->line($this->handle().' reports sent');

        return 0;
    }
}
