<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Ordering\OrdersBacklogTabsEnum;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Guards against HELP-3116: 273 orders sat submitted for a year, invisible because their pay status
 * was never computed and so matched neither half of the backlog. Staff work from those queues alone,
 * so an order in none of them is an order nobody will ever chase.
 *
 * Each check is the complement of what the backlog shows rather than a list of known faults, so the
 * next way an order falls out of the queues raises this alarm without anyone having predicted it.
 */
class MonitorOrdersInLimbo
{
    use AsAction;

    public const int STALE_SUBMITTED_DAYS = 60;

    public string $commandSignature = 'monitor:orders_in_limbo';
    public string $commandDescription = 'Alert Discord about orders that no backlog queue shows';

    /**
     * @return array<int, string> list of problems found
     */
    public function handle(?Command $command = null): array
    {
        $issues = array_merge(
            $this->statesNoTabShows(),
            $this->submittedOrdersInNeitherPayQueue(),
            $this->submittedOrdersNobodyHasTouched(),
        );

        foreach ($issues as $issue) {
            $command?->error($issue);
        }

        if ($issues) {
            $this->notifyDiscord($issues, $command);
        } else {
            $command?->info('Every live order is in a queue someone can see');
        }

        return $issues;
    }

    /**
     * A state the backlog was never taught to show. The list is normally empty, in which case this
     * costs no query at all; it fills the moment someone adds a state without giving it a home.
     */
    protected function statesNoTabShows(): array
    {
        $accountedFor = collect(OrdersBacklogTabsEnum::statesShown())
            ->merge(OrdersBacklogTabsEnum::statesNeedingNoQueue())
            ->pluck('value');

        return collect(OrderStateEnum::cases())
            ->reject(fn (OrderStateEnum $state) => $accountedFor->contains($state->value))
            ->map(function (OrderStateEnum $state) {
                $count = Order::where('state', $state)->count();

                return "State `{$state->value}` is shown by no backlog tab and holds $count orders";
            })
            ->all();
    }

    /**
     * The paid and unpaid tabs are meant to partition the submitted orders between them. If they
     * ever stop adding up, orders are falling through the gap exactly as they did in HELP-3116.
     */
    protected function submittedOrdersInNeitherPayQueue(): array
    {
        $submitted = Order::where('state', OrderStateEnum::SUBMITTED);

        $total   = (clone $submitted)->count();
        $shown   = (clone $submitted)->paySettled()->count() + (clone $submitted)->payNotSettled()->count();
        $missing = $total - $shown;

        return $missing === 0
            ? []
            : ["$missing of $total submitted orders are in neither the paid nor the unpaid queue"];
    }

    /**
     * Visible but never acted on is the symptom the queues exist to prevent, and the one that would
     * have caught HELP-3116 a year before anyone noticed.
     *
     * Only orders that went stale in the last day, never the whole standing pile: an alert that
     * repeats the same hundreds of orders every morning is one everybody learns to ignore inside a
     * week, and it would drown the checks above it. Each order raises its hand exactly once.
     */
    protected function submittedOrdersNobodyHasTouched(): array
    {
        $wentStaleAfter  = now()->subDays(self::STALE_SUBMITTED_DAYS + 1);
        $wentStaleBefore = now()->subDays(self::STALE_SUBMITTED_DAYS);

        return Order::where('state', OrderStateEnum::SUBMITTED)
            ->whereBetween('submitted_at', [$wentStaleAfter, $wentStaleBefore])
            ->selectRaw('shop_id, count(*) as total')
            ->groupBy('shop_id')
            ->with('shop:id,code')
            ->get()
            ->map(fn ($row) => "Shop `{$row->shop?->code}` has {$row->total} orders that passed ".self::STALE_SUBMITTED_DAYS.' days submitted today without being dispatched')
            ->all();
    }

    public function asCommand(Command $command): int
    {
        return $this->handle($command) === [] ? 0 : 1;
    }

    protected function notifyDiscord(array $issues, ?Command $command = null): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured. Please set it in config/services.php or .env');

            return;
        }

        try {
            Http::post($webhookUrl, [
                'content' => "👻 **Orders nobody can see** 👻\n\n".implode("\n", $issues),
            ]);
        } catch (\Exception $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
