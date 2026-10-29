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
 * from the agent's goods receipts and warehouse stock, so only agent orders have it, and the last three come from
 * each of its stock deliveries, because one order can ship in several deliveries.
 */
enum PurchaseOrderStageEnum: string
{
    use EnumHelperTrait;

    case SUBMITTED = 'submitted';
    case DEPOSIT_PAID = 'deposit_paid';
    case IN_PRODUCTION = 'in_production';
    case IN_WAREHOUSE = 'in_warehouse';
    case READY = 'ready';
    case SHIPPED = 'shipped';
    case ARRIVED = 'arrived';
    case RECEIVED = 'received';

    /**
     * Dates a person records on the purchase order, with the stage each belongs to. The deposit is recorded as a
     * payment on the order, never marked.
     */
    public const array MARKABLE_FIELDS = [
        'sample_approved_at'     => self::IN_PRODUCTION,
        'approved_ready_at'      => self::IN_PRODUCTION,
        'produced_at'            => self::READY,
        'handed_over_at'         => self::SHIPPED,
        'compliance_complete_at' => self::ARRIVED,
        'production_started_at' => self::IN_PRODUCTION,
        'qc_passed_at'          => self::READY,
    ];

    /**
     * Dates that plan ahead rather than record what happened: they may be in the future.
     */
    public const array PLANNED_FIELDS = [
        'approved_ready_at',
    ];

    public const array AGENT_MARKABLE_FIELDS = [
        'production_started_at',
        'produced_at',
        'handed_over_at',
    ];

    public static function ofField(string $field): ?self
    {
        return self::MARKABLE_FIELDS[$field] ?? collect(self::cases())->first(fn (self $stage) => $stage->column() === $field);
    }

    /**
     * Open purchase orders whose goods the supplier is making: production recorded, quality check not passed yet
     * and nothing ready to ship.
     */
    public static function inProductionSql(): string
    {
        return "(purchase_orders.state in ('submitted', 'confirmed')
            and (purchase_orders.production_started_at is not null or purchase_orders.sample_approved_at is not null)
            and purchase_orders.qc_passed_at is null
            and purchase_orders.delivery_state in ('in_process', 'confirmed'))";
    }

    public const array PROGRESS_BUCKETS = ['draft', 'submitted', 'confirmed', 'at_factory', 'dispatched', 'arrived'];

    /**
     * Where an open purchase order is, as one bucket: being drafted, sent and not confirmed, confirmed, at the
     * factory (deposit, production, quality check or hand over recorded), on the way, or arrived.
     */
    public static function progressSql(): string
    {
        return "case
            when purchase_orders.state = 'in_process' then 'draft'
            when purchase_orders.delivery_state in ('received', 'checked', 'placed') or purchase_orders.online_at is not null then 'arrived'
            when purchase_orders.delivery_state = 'dispatched' then 'dispatched'
            when purchase_orders.delivery_state = 'ready_to_ship' or purchase_orders.deposit_paid_at is not null or purchase_orders.production_started_at is not null
                or purchase_orders.qc_passed_at is not null or purchase_orders.handed_over_at is not null then 'at_factory'
            when purchase_orders.state = 'confirmed' then 'confirmed'
            else 'submitted'
        end";
    }

    /**
     * Where an accepted purchase order is at the supplier, furthest step recorded wins: nothing yet, deposit paid,
     * being made, or made (produced, QC passed, handed over or its shipment ready). Only meaningful for orders in the
     * confirmed and at_factory progress buckets.
     */
    public static function factoryStageSql(): string
    {
        return "case
            when purchase_orders.produced_at is not null or purchase_orders.qc_passed_at is not null or purchase_orders.handed_over_at is not null
                or purchase_orders.delivery_state = 'ready_to_ship' then 'made'
            when purchase_orders.production_started_at is not null or purchase_orders.sample_approved_at is not null then 'in_production'
            when purchase_orders.deposit_paid_at is not null then 'deposit_paid'
            else 'not_started'
        end";
    }

    public static function factoryStageIcons(): array
    {
        return [
            'not_started'   => 'fal fa-hourglass-start',
            'deposit_paid'  => 'fal fa-hand-holding-usd',
            'in_production' => 'fal fa-cogs',
            'made'          => 'fal fa-clipboard-check',
        ];
    }

    public static function factoryStageLabels(): array
    {
        return [
            'not_started'   => __('Not started'),
            'deposit_paid'  => __('Deposit paid'),
            'in_production' => __('In production'),
            'made'          => __('Made, QC passed'),
        ];
    }

    public static function progressLabels(): array
    {
        return [
            'draft'      => __('Draft'),
            'submitted'  => __('Sent, not confirmed'),
            'confirmed'  => __('Confirmed'),
            'at_factory' => __('Deposit, QC or handed over'),
            'dispatched' => __('Dispatched'),
            'arrived'    => __('Arrived'),
        ];
    }

    /**
     * Open purchase orders whose expected date has gone by with no container booked for them.
     */
    public static function pastEtaSql(): string
    {
        return "(purchase_orders.state in ('submitted', 'confirmed')
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
            'submitted'     => __('PO submitted'),
            'deposit_paid'  => __('Deposit paid'),
            'in_production' => __('In production'),
            'in_warehouse'  => __('In warehouse'),
            'ready'         => __('Ready & QC passed'),
            'shipped'       => __('Shipped'),
            'arrived'       => __('Arrived & cleared'),
            'received'      => __('Received at warehouse'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            'submitted'     => __('The order was sent to the supplier or agent'),
            'deposit_paid'  => __('The supplier has the deposit'),
            'in_production' => __('Sample approved and the goods are being made'),
            'in_warehouse'  => __('Goods arrived at the agent warehouse, checked and packed'),
            'ready'         => __('Goods made and their quality check approved'),
            'shipped'       => __('Goods left the supplier'),
            'arrived'       => __('Goods arrived at the port and cleared customs'),
            'received'      => __('Goods received at our warehouse'),
        ];
    }

    public static function fieldLabels(): array
    {
        return [
            'submitted_at'           => __('Submitted'),
            'confirmed_at'           => __('Accepted'),
            'deposit_paid_at'        => __('Deposit paid'),
            'sample_approved_at'     => __('Sample approved'),
            'production_started_at'  => __('Production started'),
            'proposed_ready_at'      => __('Proposed ready date'),
            'approved_ready_at'      => __('Approved ready date'),
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
            self::SHIPPED       => 'dispatched_at',
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
            self::SUBMITTED     => ['confirmed_at'],
            self::DEPOSIT_PAID  => [],
            self::IN_PRODUCTION => ['sample_approved_at', 'proposed_ready_at', 'approved_ready_at'],
            self::IN_WAREHOUSE  => ['warehouse_arrived_at', 'warehouse_checked_at', 'warehouse_packed_at'],
            self::READY         => ['produced_at', 'balance_paid_at'],
            self::SHIPPED       => ['handed_over_at'],
            self::ARRIVED       => ['customs_released_at', 'compliance_complete_at'],
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
        return in_array($this, [self::SHIPPED, self::ARRIVED, self::RECEIVED]);
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }
}
