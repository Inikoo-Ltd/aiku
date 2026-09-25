<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrder;

use App\Enums\EnumHelperTrait;

enum PurchaseOrderJourneyStageEnum: string
{
    use EnumHelperTrait;

    case PO_CREATED = 'po_created';
    case SPEC_SAMPLE = 'spec_sample';
    case DEPOSIT_PAID = 'deposit_paid';
    case PRODUCTION = 'production';
    case QC = 'qc';
    case CLEAN_HANDOVER = 'clean_handover';
    case DISPATCHED = 'dispatched';
    case IN_TRANSIT = 'in_transit';
    case WAREHOUSE_RECEIVED = 'warehouse_received';
    case PRODUCTS_ONLINE = 'products_online';

    public static function labels(): array
    {
        return [
            'po_created'         => __('PO created'),
            'spec_sample'        => __('Spec / Sample'),
            'deposit_paid'       => __('Deposit paid'),
            'production'         => __('Production'),
            'qc'                 => __('QC'),
            'clean_handover'     => __('Clean handover'),
            'dispatched'         => __('Dispatched'),
            'in_transit'         => __('In transit'),
            'warehouse_received' => __('Warehouse received'),
            'products_online'    => __('Products online'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            'po_created'         => __('Done when the PO is sent to the supplier'),
            'spec_sample'        => __('New products only: specification and sample approved'),
            'deposit_paid'       => __('Deposit paid to the supplier'),
            'production'         => __('Goods produced'),
            'qc'                 => __('Quality check passed'),
            'clean_handover'     => __('Goods handed over complete, checked and with their paperwork'),
            'dispatched'         => __('Goods left the supplier'),
            'in_transit'         => __('Done when the goods arrive at our warehouse'),
            'warehouse_received' => __('Goods checked and placed in their locations'),
            'products_online'    => __('Every product from the PO on sale on the website'),
        ];
    }

    public function markColumn(): ?string
    {
        return match ($this) {
            self::SPEC_SAMPLE    => 'sample_approved_at',
            self::DEPOSIT_PAID   => 'deposit_paid_at',
            self::PRODUCTION     => 'produced_at',
            self::QC             => 'qc_passed_at',
            self::CLEAN_HANDOVER => 'handed_over_at',
            default              => null,
        };
    }

    /**
     * Days a stage gets after the previous one is done, per journey (agent, supplier or partner).
     */
    public function defaultDays(string $journey): int
    {
        return match ($this) {
            self::PO_CREATED         => $journey === 'agent' ? 7 : 3,
            self::SPEC_SAMPLE        => $journey === 'agent' ? 21 : 14,
            self::DEPOSIT_PAID       => 7,
            self::PRODUCTION         => $journey === 'agent' ? 35 : 14,
            self::QC, self::CLEAN_HANDOVER => 5,
            self::DISPATCHED         => match ($journey) {
                'agent'   => 5,
                'partner' => 21,
                default   => 14,
            },
            self::IN_TRANSIT         => match ($journey) {
                'agent'   => 35,
                'partner' => 4,
                default   => 7,
            },
            self::WAREHOUSE_RECEIVED => 3,
            self::PRODUCTS_ONLINE    => 7,
        };
    }
}
