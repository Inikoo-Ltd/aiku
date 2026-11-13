<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrder;

use App\Enums\EnumHelperTrait;

/**
 * The stages a supplier purchase order goes through. Most are recorded on the purchase order, In warehouse comes
 * from the agent's goods receipts and warehouse stock, so only agent orders have it, and Arrived and Received come
 * from each of its stock deliveries, because one order can ship in several deliveries. Handed over (Clean Hand Over)
 * is the agent's: for an agent sending on the next container it is done once every item is in a container, for one
 * sending to a forwarder once the agent marks it or every Ready to Roll check is done, and a dispatched delivery
 * completes it either way.
 */
enum PurchaseOrderStageEnum: string
{
    use EnumHelperTrait;

    case SUBMITTED = 'submitted';
    case DEPOSIT_PAID = 'deposit_paid';
    case IN_PRODUCTION = 'in_production';
    case IN_WAREHOUSE = 'in_warehouse';
    case READY = 'ready';
    case HANDED_OVER = 'handed_over';
    case ARRIVED = 'arrived';
    case RECEIVED = 'received';

    /**
     * Dates a person records on the purchase order, with the stage each belongs to. The deposit is recorded as a
     * payment on the order, never marked.
     */
    public const array MARKABLE_FIELDS = [
        'sample_approved_at'     => self::IN_PRODUCTION,
        'approved_ready_at'      => self::DEPOSIT_PAID,
        'produced_at'            => self::READY,
        'handed_over_at'         => self::HANDED_OVER,
        'compliance_complete_at' => self::READY,
        'production_started_at'  => self::IN_PRODUCTION,
        'qc_passed_at'           => self::READY,
    ];

    /**
     * What a person may skip, with the stage it belongs to, so an order in strict stage order never gets stuck. Shipped
     * direct, QC skipped and Handed over without checks pass their stage; Sample skipped and Compliance skipped stand in
     * for that step wherever it is asked for.
     */
    public const array SKIPS = [
        'sample'       => self::IN_PRODUCTION,
        'in_warehouse' => self::IN_WAREHOUSE,
        'qc'           => self::READY,
        'compliance'   => self::READY,
        'handed_over'  => self::HANDED_OVER,
    ];

    /**
     * Agents skip only QC, on their own orders, so Ready to Roll never waits on the buyer to record a check that was not done.
     */
    public const array AGENT_SKIPPABLE = ['qc'];

    public const array STAGE_PASSING_SKIPS = [
        'in_warehouse' => self::IN_WAREHOUSE,
        'qc'           => self::READY,
        'handed_over'  => self::HANDED_OVER,
    ];

    public static function skipLabels(): array
    {
        return [
            'sample'       => __('Sample skipped'),
            'in_warehouse' => __('Shipped direct'),
            'qc'           => __('QC skipped'),
            'compliance'   => __('Compliance skipped'),
            'handed_over'  => __('Handed over without checks'),
        ];
    }

    public static function skipDescriptions(): array
    {
        return [
            'sample'       => __('No sample is approved for this order: the later checks stop asking for it.'),
            'in_warehouse' => __('The goods do not go through the agent\'s warehouse: the order moves on to Ready to Roll.'),
            'qc'           => __('No quality check is done for this order: Ready to Roll counts as passed.'),
            'compliance'   => __('No compliance check is done for this order before it ships.'),
            'handed_over'  => __('The goods are handed over before every Ready to Roll check is done.'),
        ];
    }

    /**
     * Dates that plan ahead rather than record what happened: they may be in the future.
     */
    public const array PLANNED_FIELDS = [
        'approved_ready_at',
    ];

    /**
     * Stages 1 to 5 belong to the agent: it marks its own sample, production, quality check and hand over. It also
     * completes compliance when the order has it, as compliance is optional and holds nothing up.
     */
    public const array AGENT_MARKABLE_FIELDS = [
        'production_started_at',
        'produced_at',
        'compliance_complete_at',
        'handed_over_at',
    ];

    public const array READY_CHECK_FIELDS = [
        'balance'    => 'balance_paid_at',
        'qc'         => 'qc_passed_at',
        'packing'    => 'produced_at',
        'compliance' => 'compliance_complete_at',
    ];

    public static function agentMayMark(string $field): bool
    {
        return in_array($field, self::AGENT_MARKABLE_FIELDS);
    }

    public static function readyCheckLabels(): array
    {
        return [
            'balance'    => __('Balance'),
            'qc'         => __('QC'),
            'packing'    => __('Packing'),
            'compliance' => __('Compliance'),
        ];
    }

    public static function ofField(string $field): ?self
    {
        return self::MARKABLE_FIELDS[$field] ?? collect(self::cases())->first(fn (self $stage) => $stage->column() === $field);
    }

    /**
     * Open purchase orders whose expected date has gone by with no container booked for them.
     */
    public static function pastEtaSql(): string
    {
        return '(purchase_orders.state in ('.PurchaseOrderStateEnum::openSql().")
            and purchase_orders.estimated_received_at is not null
            and purchase_orders.estimated_received_at < current_date
            and not exists (
                select 1 from purchase_order_stock_delivery
                join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                where purchase_order_stock_delivery.purchase_order_id = purchase_orders.id
                    and stock_deliveries.deleted_at is null
                    and stock_deliveries.state not in ('cancelled', 'not_received')
            ))";
    }

    /**
     * Sent purchase orders the supplier has not confirmed after the given days, leaving out those already past
     * their expected date, which are chased for that.
     */
    public static function unconfirmedSql(int $days): string
    {
        return "(purchase_orders.state = 'submitted'
            and purchase_orders.submitted_at < now() - interval '{$days} days'
            and not ".self::pastEtaSql().')';
    }

    public static function labels(): array
    {
        return [
            'submitted'     => __('PO Submitted'),
            'deposit_paid'  => __('Agreement'),
            'in_production' => __('Making It Happen'),
            'in_warehouse'  => __('In warehouse'),
            'ready'         => __('Ready to Roll'),
            'handed_over'   => __('Clean Hand Over'),
            'arrived'       => __('Homeward Bound'),
            'received'      => __('Counted & Costed'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            'submitted'     => __('Day Zero'),
            'deposit_paid'  => __('Terms and deposit agreed'),
            'in_production' => __('Production'),
            'in_warehouse'  => __('Goods arrived at the agent warehouse, checked and packed'),
            'ready'         => __('Final checks'),
            'handed_over'   => __('CHO'),
            'arrived'       => __('Transit & arrival'),
            'received'      => __('Booked into stock'),
        ];
    }

    /**
     * A short line shown under the stage's name on the purchase order.
     */
    public static function captions(): array
    {
        return [
            'deposit_paid'  => __('Terms agreed with the supplier'),
            'in_production' => __('In production at the supplier'),
            'handed_over'   => __('Available to ship'),
        ];
    }

    public static function fieldLabels(): array
    {
        return [
            'submitted_at'           => __('Submitted'),
            'confirmed_at'           => __('Agreed'),
            'deposit_paid_at'        => __('Deposit paid'),
            'sample_approved_at'     => __('Sample approved'),
            'production_started_at'  => __('Production started'),
            'proposed_ready_at'      => __('Proposed ready date'),
            'approved_ready_at'      => __('Proposed ready date'),
            'produced_at'            => __('Production done'),
            'qc_passed_at'           => __('QC approved'),
            'warehouse_arrived_at'   => __('Arrived in warehouse'),
            'warehouse_checked_at'   => __('Checked'),
            'warehouse_packed_at'    => __('Packed'),
            'balance_paid_at'        => __('Balance paid'),
            'handed_over_at'         => __('Handed over'),
            'dispatched_at'          => __('Dispatched'),
            'arrived_at'             => __('Port arrival'),
            'customs_released_at'    => __('Customs released'),
            'compliance_complete_at' => __('Compliance complete'),
            'received_at'            => __('Received'),
            'checked_at'             => __('Checked'),
            'placed_at'              => __('Placed'),
            'online_at'              => __('Online on website'),
        ];
    }

    /**
     * The date that says the order reached this stage.
     */
    public function column(): string
    {
        return match ($this) {
            self::SUBMITTED     => 'submitted_at',
            self::DEPOSIT_PAID  => 'deposit_paid_at',
            self::IN_PRODUCTION => 'production_started_at',
            self::IN_WAREHOUSE  => 'warehouse_arrived_at',
            self::READY         => 'qc_passed_at',
            self::HANDED_OVER   => 'handed_over_at',
            self::ARRIVED       => 'arrived_at',
            self::RECEIVED      => 'received_at',
        };
    }

    /**
     * Other dates recorded along the way, shown under the stage.
     */
    public function steps(): array
    {
        return match ($this) {
            self::SUBMITTED     => [],
            self::DEPOSIT_PAID  => ['approved_ready_at'],
            self::IN_PRODUCTION => ['sample_approved_at'],
            self::IN_WAREHOUSE  => ['warehouse_arrived_at', 'warehouse_checked_at', 'warehouse_packed_at'],
            self::READY         => ['produced_at', 'balance_paid_at', 'compliance_complete_at'],
            self::HANDED_OVER   => [],
            self::ARRIVED       => ['dispatched_at', 'arrived_at', 'customs_released_at'],
            self::RECEIVED      => ['checked_at', 'placed_at', 'online_at'],
        };
    }

    /**
     * What a date cannot be recorded without, a date or an uploaded document; recording it anyway needs a reason.
     */
    public const array FIELD_GATES = [
        'sample_approved_at' => [PurchaseOrderAttachmentScopeEnum::SAMPLE_PHOTO],
        'qc_passed_at'       => ['sample_approved_at', PurchaseOrderAttachmentScopeEnum::PRODUCTION_PHOTO, PurchaseOrderAttachmentScopeEnum::QC_REPORT],
    ];

    /**
     * Documents uploaded at each stage, shown and uploaded from the stage.
     */
    public function evidence(): array
    {
        return match ($this) {
            self::IN_PRODUCTION => [PurchaseOrderAttachmentScopeEnum::SAMPLE_PHOTO, PurchaseOrderAttachmentScopeEnum::PRODUCTION_PHOTO],
            self::READY         => [PurchaseOrderAttachmentScopeEnum::QC_REPORT],
            default             => [],
        };
    }

    public function isDeliveryStage(): bool
    {
        return in_array($this, [self::ARRIVED, self::RECEIVED]);
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }
}
