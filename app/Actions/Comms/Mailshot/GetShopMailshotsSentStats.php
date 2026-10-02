<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 01 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopMailshotsSentStats
{
    use AsObject;

    public const FIELDS = [
        'newsletters',
        'marketing_mailshots',
        'abandoned_cart_mailshots',
        'mailshots',
        'abandoned_cart_reminder_emails',
        'emails_sent',
    ];

    private const ABANDONED_CART_REMINDER_OUTBOX_CODES = [
        OutboxCodeEnum::ABANDONED_CART_REMINDER_1,
        OutboxCodeEnum::ABANDONED_CART_REMINDER_2,
        OutboxCodeEnum::ABANDONED_CART_REMINDER_3,
        OutboxCodeEnum::ABANDONED_CHECKOUT,
    ];

    /**
     * Abandoned cart reminders are counted from the outbox daily time series because their dispatched emails are archived after the email retention period.
     *
     * @return array<int, array<string, mixed>> one row per open shop, with "{field}_{interval}" and "{field}_{interval}_ly" counts of the mailshots and emails it sent
     */
    public function handle(Group|Organisation $parent, $fromDate = null, $toDate = null): array
    {
        $shops = $parent->shops()
            ->select(['shops.id', 'shops.slug', 'shops.name', 'shops.state', 'shops.colour', 'shops.organisation_id'])
            ->whereNull('shops.closed_at')
            ->with(['organisation' => fn ($query) => $query->select(['id', 'slug'])])
            ->get();

        if ($shops->isEmpty()) {
            return [];
        }

        $shopIds             = $shops->pluck('id')->all();
        $ranges              = CalculateTimeSeriesStats::make()->getRanges($fromDate, $toDate);
        $mailshotCounts      = $this->countMailshotsSent($shopIds, $ranges);
        $reminderEmailCounts = $this->countAbandonedCartReminderEmails($shopIds, array_keys($ranges), $fromDate, $toDate);

        return $shops->map(fn ($shop) => array_merge($this->shopStats(array_keys($ranges), $mailshotCounts[$shop->id] ?? [], $reminderEmailCounts[$shop->id] ?? []), [
            'id'                => $shop->id,
            'slug'              => $shop->slug,
            'name'              => $shop->name,
            'state'             => $shop->state?->value,
            'colour'            => $shop->colour,
            'organisation_slug' => $shop->organisation?->slug ?? 'unknown',
        ]))->all();
    }

    /**
     * @param array<int, string> $suffixes
     * @param array<string, array<string, int>> $mailshotCounts keyed by range suffix
     * @param array<string, int> $reminderEmailCounts keyed by range suffix
     * @return array<string, int>
     */
    private function shopStats(array $suffixes, array $mailshotCounts, array $reminderEmailCounts): array
    {
        $stats = [];
        foreach ($suffixes as $suffix) {
            $mailshots      = $mailshotCounts[$suffix] ?? [];
            $reminderEmails = $reminderEmailCounts[$suffix] ?? 0;

            $stats["newsletters_$suffix"]                    = $mailshots['newsletters'] ?? 0;
            $stats["marketing_mailshots_$suffix"]            = $mailshots['marketing_mailshots'] ?? 0;
            $stats["abandoned_cart_mailshots_$suffix"]       = $mailshots['abandoned_cart_mailshots'] ?? 0;
            $stats["mailshots_$suffix"]                      = $mailshots['mailshots'] ?? 0;
            $stats["abandoned_cart_reminder_emails_$suffix"] = $reminderEmails;
            $stats["emails_sent_$suffix"]                    = ($mailshots['mailshot_emails'] ?? 0) + $reminderEmails;
        }

        return $stats;
    }

    /**
     * @param array<int, int> $shopIds
     * @param array<string, array{0: \Carbon\Carbon, 1: \Carbon\Carbon}> $ranges
     * @return array<int, array<string, array<string, int>>> keyed by shop id, then range suffix
     */
    private function countMailshotsSent(array $shopIds, array $ranges): array
    {
        $rangeRows     = [];
        $rangeBindings = [];
        foreach ($ranges as $suffix => [$start, $end]) {
            $rangeRows[] = '(?::text, ?::timestamptz, ?::timestamptz)';
            array_push($rangeBindings, $suffix, $start, $end);
        }

        $rows = DB::table('mailshots')
            ->leftJoin('mailshot_stats', 'mailshot_stats.mailshot_id', '=', 'mailshots.id')
            ->join(DB::raw('(values '.implode(', ', $rangeRows).') as ranges(suffix, starts_at, ends_at)'), function ($join) {
                $join->whereRaw('mailshots.sent_at >= ranges.starts_at')->whereRaw('mailshots.sent_at <= ranges.ends_at');
            })
            ->addBinding($rangeBindings, 'join')
            ->select(['mailshots.shop_id', 'ranges.suffix'])
            ->selectRaw('COUNT(*) FILTER (WHERE mailshots.type = ?) as newsletters', [MailshotTypeEnum::NEWSLETTER->value])
            ->selectRaw('COUNT(*) FILTER (WHERE mailshots.type = ?) as marketing_mailshots', [MailshotTypeEnum::MARKETING->value])
            ->selectRaw('COUNT(*) FILTER (WHERE mailshots.type = ?) as abandoned_cart_mailshots', [MailshotTypeEnum::ABANDONED_CART->value])
            ->selectRaw('COUNT(*) as mailshots')
            ->selectRaw('COALESCE(SUM(mailshot_stats.number_dispatched_emails), 0) as mailshot_emails')
            ->whereIn('mailshots.shop_id', $shopIds)
            ->where('mailshots.state', MailshotStateEnum::SENT->value)
            ->whereIn('mailshots.type', [
                MailshotTypeEnum::NEWSLETTER->value,
                MailshotTypeEnum::MARKETING->value,
                MailshotTypeEnum::ABANDONED_CART->value,
            ])
            ->whereNull('mailshots.deleted_at')
            ->groupBy('mailshots.shop_id', 'ranges.suffix')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            foreach (['newsletters', 'marketing_mailshots', 'abandoned_cart_mailshots', 'mailshots', 'mailshot_emails'] as $field) {
                $counts[$row->shop_id][$row->suffix][$field] = (int) $row->$field;
            }
        }

        return $counts;
    }

    /**
     * @param array<int, int> $shopIds
     * @param array<int, string> $suffixes
     * @return array<int, array<string, int>> keyed by shop id, then range suffix
     */
    private function countAbandonedCartReminderEmails(array $shopIds, array $suffixes, $fromDate, $toDate): array
    {
        $shopIdsByTimeSeries = DB::table('outbox_time_series')
            ->join('outboxes', 'outboxes.id', '=', 'outbox_time_series.outbox_id')
            ->whereIn('outboxes.shop_id', $shopIds)
            ->whereIn('outboxes.code', array_map(fn (OutboxCodeEnum $code) => $code->value, self::ABANDONED_CART_REMINDER_OUTBOX_CODES))
            ->where('outbox_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->pluck('outboxes.shop_id', 'outbox_time_series.id');

        $timeSeriesStats = CalculateTimeSeriesStats::run(
            $shopIdsByTimeSeries->keys()->all(),
            ['emails' => 'dispatched_emails'],
            'outbox_time_series_records',
            'outbox_time_series_id',
            $fromDate,
            $toDate,
        );

        $counts = [];
        foreach ($timeSeriesStats as $timeSeriesId => $stats) {
            $shopId = $shopIdsByTimeSeries[$timeSeriesId];
            foreach ($suffixes as $suffix) {
                $counts[$shopId][$suffix] = ($counts[$shopId][$suffix] ?? 0) + (int) ($stats["emails_$suffix"] ?? 0);
            }
        }

        return $counts;
    }
}
