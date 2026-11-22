<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 20:53:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Helpers\AI\AskToAi;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\StockValueFinding;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The nightly check for stock values that cannot be right (HELP-4868). Plain rules find and
 * decide, so the same stock always gives the same answer:
 * - count_above_history: more on the shelves than 20 times the largest purchase ever received and
 *   than three years of use;
 * - price_above_purchases: a cost per SKO above three times the median price it was bought at.
 * Who acts comes from the movement that caused it: a person's count goes to the warehouse (a task
 * on its Important tasks), a purchase price to procurement (the list only), a system movement to
 * engineering (one PRE ticket per cause, listing every SKO), an Aurora-era movement to no one. AI
 * only writes the plain-words line for each new finding. Findings no longer found are resolved,
 * and a PRE whose findings are all resolved is closed.
 */
class CheckStockValues
{
    use AsAction;

    public const float COUNT_PURCHASE_MULTIPLE = 20;
    public const float COUNT_USAGE_YEARS       = 3;
    public const float PRICE_MULTIPLE          = 3;
    public const float MIN_EXCESS              = 500;
    public const int MIN_AGE_DAYS              = 2;
    public const int MAX_ROUTED_PER_NIGHT      = 10;
    public const int MAX_EXPLAINED_PER_NIGHT   = 30;

    private int $routed = 0;
    private int $explained = 0;

    /**
     * @return array{found: int, new: int, resolved: int, routed: int}
     */
    public function handle(Organisation $organisation, bool $route = false): array
    {
        $candidates   = $this->countAboveHistory($organisation)->concat($this->priceAbovePurchases($organisation));
        $minExcess    = $this->minExcess($organisation);
        $routedBefore = $this->routed;
        $now          = now();
        $seenIds      = [];
        $new          = 0;

        foreach ($candidates as $candidate) {
            $finding = StockValueFinding::firstOrNew(['org_stock_id' => $candidate['org_stock_id'], 'rule' => $candidate['rule']]);
            $isNew   = !$finding->exists || $finding->state === StockValueFinding::STATE_RESOLVED;

            if ($isNew && $candidate['excess'] < $minExcess) {
                continue;
            }

            if ($isNew) {
                $cause = $this->causeMovement($candidate['org_stock_id'], $candidate['rule']);
                $finding->fill([
                    'group_id'                    => $organisation->group_id,
                    'organisation_id'             => $organisation->id,
                    'owner'                       => $this->owner($cause),
                    'state'                       => StockValueFinding::STATE_OPEN,
                    'cause_org_stock_movement_id' => $cause?->id,
                    'location_id'                 => $cause?->location_id,
                    'ticket_key'                  => null,
                    'explanation'                 => null,
                    'staff_task_id'               => null,
                    'ticket_id'                   => null,
                    'first_seen_at'               => $now,
                    'resolved_at'                 => null,
                ]);
                $candidate['facts']['cause'] = $this->causeFacts($cause);
                $new++;
            } else {
                $candidate['facts']['cause'] = $finding->facts['cause'] ?? null;
            }

            $finding->fill([
                'excess_amount' => round($candidate['excess'], 2),
                'facts'         => $candidate['facts'],
                'last_seen_at'  => $now,
            ])->save();
            $seenIds[] = $finding->id;

            if (!$finding->explanation && $this->explained < self::MAX_EXPLAINED_PER_NIGHT) {
                $this->explained++;
                $finding->update(['explanation' => $this->explain($finding)]);
            }
        }

        $resolved = StockValueFinding::where('organisation_id', $organisation->id)
            ->where('state', StockValueFinding::STATE_OPEN)
            ->whereNotIn('id', $seenIds)
            ->update(['state' => StockValueFinding::STATE_RESOLVED, 'resolved_at' => $now]);

        if ($route) {
            $this->route($organisation);
        }

        return ['found' => count($seenIds), 'new' => $new, 'resolved' => $resolved, 'routed' => $this->routed - $routedBefore];
    }

    /**
     * The opening threshold is set in the group's currency and converted, so an organisation in
     * IDR is held to the same bar as one in GBP. Once open, a finding stays open while the rule
     * still flags it, whatever the amount, so it does not flap around the threshold.
     */
    protected function minExcess(Organisation $organisation): float
    {
        return self::MIN_EXCESS * (GetCurrencyExchange::run($organisation->group->currency, $organisation->currency) ?? 1);
    }

    /**
     * @return Collection<int, array{org_stock_id: int, rule: string, excess: float, facts: array<string, mixed>}>
     */
    protected function countAboveHistory(Organisation $organisation): Collection
    {
        $rows = DB::select(
            "with last_packing_change as (
                select distinct on (org_stock_id) org_stock_id, date, running_quantity_org_stock as converted_count
                from org_stock_movements where organisation_id = ? and reason = 'uom' order by org_stock_id, date desc, id desc
            ), purchases as (
                select m.org_stock_id, max(m.quantity) as largest_purchase
                from org_stock_movements m left join last_packing_change c on c.org_stock_id = m.org_stock_id
                where m.organisation_id = ? and m.type = 'purchase' and m.quantity > 0 and (c.date is null or m.date > c.date)
                group by m.org_stock_id
            ), usage as (
                select org_stock_id, -sum(least(quantity, 0)) as used_12_months
                from org_stock_movements where organisation_id = ? and type in ('picked', 'consumption', 'production') and date > now() - interval '1 year' group by org_stock_id
            ), reference as (
                select os.id, os.quantity_in_locations as quantity, os.sku_value, coalesce(p.largest_purchase, 0) as largest_purchase,
                    greatest(coalesce(u.used_12_months, 0), 0) as used_12_months, coalesce(c.converted_count, 0) as converted_count
                from org_stocks os
                left join purchases p on p.org_stock_id = os.id
                left join usage u on u.org_stock_id = os.id
                left join last_packing_change c on c.org_stock_id = os.id
                where os.organisation_id = ? and (p.largest_purchase is not null or c.converted_count > 0)
            )
            select *, greatest(largest_purchase, converted_count, ? * used_12_months) as plausible
            from reference
            where quantity > greatest(? * largest_purchase, 2 * converted_count) and quantity > ? * used_12_months",
            [$organisation->id, $organisation->id, $organisation->id, $organisation->id, self::COUNT_USAGE_YEARS, self::COUNT_PURCHASE_MULTIPLE, self::COUNT_USAGE_YEARS]
        );

        return collect($rows)
            ->map(fn ($row) => [
                'org_stock_id' => $row->id,
                'rule'         => StockValueFinding::RULE_COUNT,
                'excess'       => ((float)$row->quantity - (float)$row->plausible) * (float)$row->sku_value,
                'facts'        => [
                    'quantity'         => (float)$row->quantity,
                    'sku_value'        => (float)$row->sku_value,
                    'largest_purchase' => (float)$row->largest_purchase,
                    'used_12_months'   => (float)$row->used_12_months,
                    'converted_count'  => (float)$row->converted_count,
                ],
            ])
            ->filter(fn ($candidate) => $candidate['excess'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, array{org_stock_id: int, rule: string, excess: float, facts: array<string, mixed>}>
     */
    protected function priceAbovePurchases(Organisation $organisation): Collection
    {
        $rows = DB::select(
            "with last_packing_change as (
                select org_stock_id, max(date) as date from org_stock_movements where organisation_id = ? and reason = 'uom' group by org_stock_id
            ), purchases as (
                select m.org_stock_id, percentile_cont(0.5) within group (order by m.cost_per_sku) as median_cost, count(*) as priced_purchases
                from org_stock_movements m left join last_packing_change c on c.org_stock_id = m.org_stock_id
                where m.organisation_id = ? and m.type = 'purchase' and m.quantity > 0 and m.cost_per_sku > 0 and (c.date is null or m.date > c.date)
                group by m.org_stock_id
            )
            select os.id, os.quantity_in_locations as quantity, os.sku_value, p.median_cost, p.priced_purchases
            from org_stocks os
            join purchases p on p.org_stock_id = os.id
            where os.organisation_id = ? and os.quantity_in_locations > 0 and os.sku_value > ? * p.median_cost",
            [$organisation->id, $organisation->id, $organisation->id, self::PRICE_MULTIPLE]
        );

        return collect($rows)
            ->map(fn ($row) => [
                'org_stock_id' => $row->id,
                'rule'         => StockValueFinding::RULE_PRICE,
                'excess'       => (float)$row->quantity * ((float)$row->sku_value - (float)$row->median_cost),
                'facts'        => [
                    'quantity'         => (float)$row->quantity,
                    'sku_value'        => (float)$row->sku_value,
                    'median_cost'      => round((float)$row->median_cost, 6),
                    'priced_purchases' => (int)$row->priced_purchases,
                ],
            ])
            ->filter(fn ($candidate) => $candidate['excess'] > 0)
            ->values();
    }

    /**
     * A count is caused by the movement that added the most units, a price by the one that
     * added the most value in the last year, or failing that the dearest purchase; only
     * movements that add stock (not transfers), and only since the last packing change.
     */
    protected function causeMovement(int $orgStockId, string $rule): ?object
    {
        $lastPackingChange = DB::table('org_stock_movements')->where('org_stock_id', $orgStockId)->where('reason', OrgStockMovementReasonEnum::UOM->value)->max('date');
        $query             = DB::table('org_stock_movements')->where('org_stock_id', $orgStockId)->where('quantity', '>', 0)
            ->where(fn ($query) => $query->whereIn('type', [OrgStockMovementTypeEnum::PURCHASE->value, OrgStockMovementTypeEnum::FOUND->value, OrgStockMovementTypeEnum::ADJUSTMENT->value, OrgStockMovementTypeEnum::AUDIT->value]))
            ->when($lastPackingChange, fn ($query) => $query->where('date', '>=', $lastPackingChange));

        if ($rule === StockValueFinding::RULE_COUNT) {
            return $query->orderByDesc('quantity')->first();
        }

        return (clone $query)->where('date', '>', now()->subYear())->where('org_amount', '>', 0)->orderByDesc('org_amount')->first()
            ?? $query->where('type', OrgStockMovementTypeEnum::PURCHASE->value)->orderByDesc('cost_per_sku')->first();
    }

    protected function owner(?object $cause): string
    {
        return match (true) {
            $cause === null                                                                                                    => StockValueFinding::OWNER_LEGACY,
            in_array($cause->reason, [OrgStockMovementReasonEnum::UOM->value, OrgStockMovementReasonEnum::REVALUATION->value], true) => StockValueFinding::OWNER_ENGINEERING,
            $cause->source_id !== null                                                                                         => StockValueFinding::OWNER_LEGACY,
            $cause->type === OrgStockMovementTypeEnum::PURCHASE->value                                                         => StockValueFinding::OWNER_PROCUREMENT,
            $cause->user_id !== null                                                                                           => StockValueFinding::OWNER_WAREHOUSE,
            default                                                                                                            => StockValueFinding::OWNER_ENGINEERING,
        };
    }

    protected function causeFacts(?object $cause): ?array
    {
        if (!$cause) {
            return null;
        }

        return [
            'movement_id' => $cause->id,
            'date'        => substr((string)$cause->date, 0, 10),
            'type'        => $cause->type,
            'reason'      => $cause->reason,
            'quantity'    => (float)$cause->quantity,
            'amount'      => (float)$cause->org_amount,
            'user'        => $cause->user_id ? User::find($cause->user_id)?->contact_name : null,
            'location'    => $cause->location_id ? DB::table('locations')->where('id', $cause->location_id)->value('code') : null,
            'note'        => $cause->note ? mb_substr(strip_tags($cause->note), 0, 200) : null,
        ];
    }

    public function ruleText(StockValueFinding $finding, bool $withNames = true): string
    {
        $facts = $finding->facts;
        $cause = $facts['cause'] ?? null;
        $who   = $withNames && ($cause['user'] ?? null) ? ' '.__('by :user', ['user' => $cause['user']]) : '';
        $by    = $cause ? __(' It came from a :type on :date:who.', ['type' => trim(($cause['reason'] ?? '').' '.$cause['type']), 'date' => $cause['date'], 'who' => $who]) : '';

        if ($finding->rule === StockValueFinding::RULE_PRICE) {
            return __('Valued at :value each, but it is usually bought at :median.', ['value' => $facts['sku_value'], 'median' => $facts['median_cost']]).$by;
        }

        return ($facts['largest_purchase'] ?? 0) > 0
            ? __(':quantity on the shelves, but the largest delivery was :largest and :used were used in the last year.', ['quantity' => $facts['quantity'], 'largest' => $facts['largest_purchase'], 'used' => $facts['used_12_months']]).$by
            : __(':quantity on the shelves, but after its last packing change there were :converted.', ['quantity' => $facts['quantity'], 'converted' => $facts['converted_count'] ?? 0]).$by;
    }

    public static function actionText(StockValueFinding $finding): string
    {
        return match (true) {
            $finding->owner === StockValueFinding::OWNER_ENGINEERING => __('Nothing to do: engineers fix it.'),
            $finding->owner === StockValueFinding::OWNER_LEGACY      => __('Old data from the previous system: engineers review it.'),
            $finding->owner === StockValueFinding::OWNER_PROCUREMENT => __('Procurement checks the price on the delivery.'),
            $finding->rule === StockValueFinding::RULE_COUNT         => $finding->location_id ? __('Recount location :location.', ['location' => $finding->location?->code]) : __('Recount it in every location.'),
            default                                                  => __('A supervisor checks the price and reprices the stock.'),
        };
    }

    protected function explain(StockValueFinding $finding): string
    {
        $ruleText = $this->ruleText($finding, withNames: false);
        $facts    = $finding->facts;
        unset($facts['cause']['user']);
        $prompt   = "A stock check found this SKO's stock value cannot be right. Explain what looks wrong with the count on the shelves or with the price of one unit, in at most two short sentences for warehouse and office staff, in plain words, with no technical terms. Do not say what to do, that is added separately.\n"
            ."SKO: {$finding->orgStock->code} {$finding->orgStock->name}\nFinding: {$ruleText}\nFacts: ".json_encode($facts);

        try {
            $answer = AskToAi::run($prompt);
        } catch (Throwable) {
            $answer = null;
        }

        return trim((string)$answer) !== '' ? trim((string)$answer) : $this->ruleText($finding);
    }

    protected function route(Organisation $organisation): void
    {
        $findings = StockValueFinding::where('organisation_id', $organisation->id)
            ->where('state', StockValueFinding::STATE_OPEN)
            ->where(fn ($query) => $query->whereNull('staff_task_id')
                ->orWhereHas('staffTask', fn ($task) => $task->whereNotIn('status', [StaffTaskStatusEnum::TODO, StaffTaskStatusEnum::IN_PROGRESS])->where('closed_at', '<', now()->subDays(7))))
            ->whereNull('ticket_key')
            ->whereIn('owner', [StockValueFinding::OWNER_WAREHOUSE, StockValueFinding::OWNER_ENGINEERING])
            ->with(['orgStock', 'location'])
            ->orderByDesc('excess_amount')
            ->get();

        foreach ($findings as $finding) {
            if ($this->routed >= self::MAX_ROUTED_PER_NIGHT) {
                break;
            }
            $causeDate = $finding->facts['cause']['date'] ?? null;
            if ($causeDate && now()->diffInDays($causeDate, true) < self::MIN_AGE_DAYS) {
                continue;
            }

            if ($finding->owner === StockValueFinding::OWNER_WAREHOUSE) {
                $this->routeToWarehouse($finding);
            } else {
                $finding->update(['ticket_key' => $organisation->id.':'.$finding->rule.':'.($finding->facts['cause']['type'] ?? '').':'.($finding->facts['cause']['reason'] ?? '')]);
            }
            $this->routed++;
        }

        $this->syncPremonitions($organisation);
    }

    protected function routeToWarehouse(StockValueFinding $finding): void
    {
        $existing = StaffTask::query()->open()
            ->where('department', 'warehouse')
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('model_type', 'OrgStock')->where('model_id', $finding->org_stock_id))
                ->when($finding->location_id, fn ($query) => $query->orWhere(fn ($query) => $query->where('model_type', 'Location')->where('model_id', $finding->location_id)
                    ->whereRaw('subject collate "C" ilike ?', ['%'.$finding->orgStock->code.'%']))))
            ->where('id', '!=', $finding->staff_task_id ?? 0)
            ->first();

        $task = $existing ?? StoreStaffTask::make()->action(GetAikuAssistant::run($finding->group_id), [
            'subject'     => __('Check the stock of :code', ['code' => $finding->orgStock->code]).($finding->location ? ' '.__('in :location', ['location' => $finding->location->code]) : ''),
            'description' => ($finding->explanation ?? $this->ruleText($finding))."\n\n".self::actionText($finding),
            'department'  => 'warehouse',
            'model_type'  => $finding->location ? 'Location' : 'OrgStock',
            'model_id'    => $finding->location?->id ?? $finding->org_stock_id,
        ]);

        $finding->update(['staff_task_id' => $task->id]);
    }

    /**
     * One PRE per cause: its description lists every SKO with that cause still open; once none
     * is open it is resolved.
     */
    protected function syncPremonitions(Organisation $organisation): void
    {
        $keys = StockValueFinding::where('organisation_id', $organisation->id)->whereNotNull('ticket_key')->distinct()->pluck('ticket_key');

        foreach ($keys as $key) {
            $findings = StockValueFinding::where('ticket_key', $key)->with('orgStock')->orderBy('id')->get();
            $open     = $findings->where('state', StockValueFinding::STATE_OPEN);
            $ticket   = Ticket::find($open->whereNotNull('ticket_id')->max('ticket_id') ?? $findings->max('ticket_id'));

            if ($open->isEmpty()) {
                if ($ticket && $ticket->status->isOpen()) {
                    UpdateTicket::make()->handle($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
                }
                continue;
            }

            $description = __('The nightly stock check found stock values the system itself made wrong (:cause). Each line is an SKO still affected; the amounts are on the Suspicious stock values list:', ['cause' => explode(':', $key, 3)[2] ?? $key])
                ."\n\n".$open->map(fn (StockValueFinding $finding) => '- '.$finding->orgStock->code.': '.($finding->explanation ?? $this->ruleText($finding)))->implode("\n");

            if ($ticket && $ticket->status === TicketStatusEnum::CANCELLED) {
                continue;
            }

            if (!$ticket || !$ticket->status->isOpen()) {
                $ticket = StoreTicket::make()->action($organisation->group, [
                    'type'            => TicketTypeEnum::PREMONITION->value,
                    'subject'         => mb_substr(__('Stock check: :count SKOs valued wrong in :organisation (:cause)', ['count' => $open->count(), 'organisation' => $organisation->code, 'cause' => explode(':', $key, 3)[2] ?? $key]), 0, 255),
                    'description'     => $description,
                    'kind'            => 'bug',
                    'module'          => TicketModuleEnum::INVENTORY->value,
                    'organisation_id' => $organisation->id,
                    'tags'            => ['stock-value-check'],
                ]);
            } elseif ($ticket->description !== $description) {
                UpdateTicket::make()->handle($ticket, ['description' => $description]);
            }

            StockValueFinding::where('ticket_key', $key)->where('state', StockValueFinding::STATE_OPEN)->update(['ticket_id' => $ticket->id]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'stock_values:check {organisation? : Organisation slug, every organisation with stock if omitted} {--route : Raise warehouse tasks and PRE tickets for new findings}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = $command->argument('organisation')
            ? Organisation::where('slug', $command->argument('organisation'))->get()
            : Organisation::whereIn('id', OrgStock::where('quantity_in_locations', '>', 0)->select('organisation_id')->distinct())
                ->orWhereIn('id', StockValueFinding::where('state', StockValueFinding::STATE_OPEN)->select('organisation_id')->distinct())
                ->get();

        foreach ($organisations as $organisation) {
            $result = $this->handle($organisation, (bool)$command->option('route'));
            $command->info(sprintf('%s: %d found (%d new), %d resolved, %d routed', $organisation->slug, $result['found'], $result['new'], $result['resolved'], $result['routed']));
        }

        return 0;
    }
}
