<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\Comms\Mailshot\PrepareMailshotAbTestRecipients;
use App\Enums\Comms\Mailshot\MailshotAbTestWinningMetricEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMailshotAbTest
{
    use AsAction;

    public function handle(Mailshot $mailshot, bool $canEdit): ?array
    {
        $original = $mailshot->isAbTestVariant() ? $mailshot->abTestOriginal : $mailshot;

        if (!$original || $original->is_second_wave || !in_array($original->type, [MailshotTypeEnum::MARKETING, MailshotTypeEnum::NEWSLETTER])) {
            return null;
        }

        $variant          = $original->abTestVariant;
        $hasRemainingList = $original->ab_test_size_percentage < 100;
        $bothSent         = $variant && $original->state === MailshotStateEnum::SENT && $variant->state === MailshotStateEnum::SENT;
        $trackingEndsAt   = $original->abTestTrackingEndsAt();
        $isTrackingOver   = $bothSent && !$trackingEndsAt?->isFuture();
        $versions         = $variant ? [$this->version('A', $original), $this->version('B', $variant)] : [];
        $canEditSettings  = $canEdit && !$mailshot->isAbTestVariant() && in_array($original->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY]);

        return [
            'enabled'                   => (bool)$variant,
            'is_variant'                => $mailshot->isAbTestVariant(),
            'can_edit_settings'         => $canEditSettings,
            'estimated_recipients'      => $canEditSettings && $variant ? (PrepareMailshotAbTestRecipients::make()->audienceQuery($original)?->count('customers.id') ?? 0) : null,
            'winning_metric'            => $original->ab_test_winning_metric->value,
            'winning_metric_options'    => collect(MailshotAbTestWinningMetricEnum::cases())->map(fn (MailshotAbTestWinningMetricEnum $metric) => [
                'value' => $metric->value,
                'label' => $metric->label(),
            ])->all(),
            'is_blocked_by_second_wave' => $original->is_second_wave_enabled,
            'size_percentage'           => $original->ab_test_size_percentage,
            'winner_mailshot_id'        => $original->ab_test_winner_mailshot_id,
            'winner_sent_at'            => $original->ab_test_winner_sent_at,
            'auto_send_winner'          => $original->ab_test_auto_send_winner,
            'can_send_winner'           => $canEdit && $isTrackingOver && $hasRemainingList && !$original->ab_test_winner_sent_at,
            'variant_delay_seconds'     => $original->ab_test_variant_delay_seconds,
            'variant_sends_at'          => $variant && !$variant->start_sending_at ? $original->abTestVariantSendsAt() : null,
            'tracking_hours'            => $original->ab_test_tracking_hours,
            'tracking_ends_at'          => $trackingEndsAt,
            'results_tracked_until'     => Arr::get($original->data, 'AB_testing_data.tracked_until'),
            'suggested_winner_id'       => $isTrackingOver ? $this->suggestedWinnerId($versions, $original->ab_test_winning_metric) : null,
            'versions'                  => $versions,
            'update_route'              => [
                'name'       => 'grp.models.shop.mailshot.ab-test',
                'parameters' => [
                    'shop'     => $original->shop_id,
                    'mailshot' => $original->id,
                ],
            ],
            'send_winner_route'         => [
                'name'       => 'grp.models.shop.mailshot.ab-test.winner',
                'parameters' => [
                    'shop'     => $original->shop_id,
                    'mailshot' => $original->id,
                ],
            ],
        ];
    }

    private function version(string $label, Mailshot $mailshot): array
    {
        $routePrefix = $mailshot->type === MailshotTypeEnum::NEWSLETTER ? 'grp.org.shops.show.marketing.newsletters' : 'grp.org.shops.show.marketing.mailshots';
        $routeParameters = [
            'organisation'              => $mailshot->organisation->slug,
            'shop'                      => $mailshot->shop->slug,
            'mailshot'                  => $mailshot->slug,
        ];

        $counts = Arr::get($mailshot->data, 'AB_testing_data.stats') ?? $this->currentCounts($mailshot);

        return [
            'label'                     => $label,
            'id'                        => $mailshot->id,
            'subject'                   => $mailshot->subject,
            'sender_name'               => $mailshot->senderName(),
            'state'                     => $mailshot->state->value,
            'state_label'               => MailshotStateEnum::labels()[$mailshot->state->value],
            'route'                     => ['name' => $routePrefix.'.show', 'parameters' => $routeParameters],
            'workshop_route'            => ['name' => $routePrefix.'.workshop', 'parameters' => $routeParameters],
            'copy'                      => [
                'subject'      => $mailshot->subject,
                'name'         => $mailshot->name,
                'preview_text' => $mailshot->preview_text,
                'sender_name'  => $mailshot->sender_name,
            ],
            'update_route'              => [
                'name'       => 'grp.models.shop.mailshot.update',
                'parameters' => ['mailshot' => $mailshot->id],
                'method'     => 'patch',
            ],
            'suggest_copy_route'        => [
                'name'       => 'grp.json.mailshot.copy_suggestion',
                'parameters' => ['mailshot' => $mailshot->id],
                'method'     => 'post',
            ],
            'stats'                     => [
                'sent'              => $counts['sent'],
                'delivered'         => $counts['delivered'],
                'opened'            => $counts['opened'],
                'clicked'           => $counts['clicked'],
                'placed_orders'     => $counts['placed_orders'],
                'open_rate'         => $this->rate($counts['opened'], $counts['delivered']),
                'click_rate'        => $this->rate($counts['clicked'], $counts['delivered']),
                'placed_order_rate' => $this->rate($counts['placed_orders'], $counts['delivered']),
                'spam_rate'         => $this->rate($counts['spam'], $counts['sent']),
                'unsubscribe_rate'  => $this->rate($counts['unsubscribed'], $counts['sent']),
                'bounce_rate'       => $this->rate($counts['bounced'], $counts['sent']),
            ],
        ];
    }

    /**
     * The mailshot's running totals, which keep counting after the test: late opens and the winner's sends.
     *
     * @return array{sent: int, delivered: int, opened: int, clicked: int, placed_orders: int, spam: int, unsubscribed: int, bounced: int}
     */
    public function currentCounts(Mailshot $mailshot): array
    {
        $stats = $mailshot->stats;

        return [
            'sent'          => (int)$stats->number_dispatched_emails,
            'delivered'     => (int)$stats->number_deliveries_success,
            'opened'        => (int)$stats->number_delivered_open_success,
            'clicked'       => (int)$stats->number_opened_interact_success,
            'placed_orders' => (int)$stats->number_purchases,
            'spam'          => (int)$stats->number_dispatched_emails_state_spam,
            'unsubscribed'  => (int)$stats->number_dispatched_emails_state_unsubscribed,
            'bounced'       => (int)$stats->number_dispatched_emails_state_hard_bounce + (int)$stats->number_dispatched_emails_state_soft_bounce,
        ];
    }

    /**
     * The chosen metric decides; null when the two versions are level on it.
     *
     * @param  array<int, array{id: int, stats: array<string, int|float>}>  $versions
     */
    private function suggestedWinnerId(array $versions, MailshotAbTestWinningMetricEnum $winningMetric): ?int
    {
        [$versionA, $versionB] = $versions;

        return match ($versionA['stats'][$winningMetric->value] <=> $versionB['stats'][$winningMetric->value]) {
            1       => $versionA['id'],
            -1      => $versionB['id'],
            default => null,
        };
    }

    private function rate(int $count, int $base): float
    {
        return $base ? round($count / $base * 100, 2) : 0.0;
    }
}
