<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDelivery;

use App\Enums\EnumHelperTrait;

/**
 * Tax is import VAT, recovered in the VAT return: it is recorded and paid but never part of the landed cost.
 */
enum StockDeliveryServiceInvoiceTypeEnum: string
{
    use EnumHelperTrait;

    case FREIGHT = 'freight';
    case LOCAL_TRANSPORT = 'local_transport';
    case DUTY = 'duty';
    case ANTI_DUMPING = 'anti_dumping';
    case IMPORT_LEVY = 'import_levy';
    case TAX = 'tax';
    case CUSTOMS_CLEARANCE = 'customs_clearance';
    case PORT_CHARGES = 'port_charges';
    case STORAGE = 'storage';
    case INSPECTION = 'inspection';
    case DOCUMENTATION = 'documentation';
    case INSURANCE = 'insurance';
    case FINE = 'fine';
    case OTHER = 'other';
    case NOT_IN_DELIVERY = 'not_in_delivery';

    public static function labels(): array
    {
        return [
            'freight'           => __('Freight'),
            'local_transport'   => __('Local transport (port to warehouse)'),
            'duty'              => __('Customs duty'),
            'anti_dumping'      => __('Anti-dumping duty'),
            'import_levy'       => __('Other import taxes (plastic, excise)'),
            'tax'               => __('Import VAT'),
            'customs_clearance' => __('Customs clearance'),
            'port_charges'      => __('Port and terminal charges'),
            'storage'           => __('Storage and demurrage'),
            'inspection'        => __('Inspection and certificates'),
            'documentation'     => __('Documentation fees'),
            'insurance'         => __('Insurance'),
            'fine'              => __('Fines'),
            'other'             => __('Other'),
            'not_in_delivery'   => __('Invoiced but not in the delivery'),
        ];
    }

    /**
     * What a forwarder, broker or haulier can bill. Goods a supplier invoiced that are not in the delivery are an
     * extra expense of their own, never the type of a service invoice.
     *
     * @return array<int, self>
     */
    public static function billTypes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type !== self::NOT_IN_DELIVERY));
    }

    /**
     * @return array<int, array{label: string, value: string}> the types an extra expense can be
     */
    public static function extraOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $type) => $type->costType() === StockDeliveryCostTypeEnum::EXTRA)
            ->map(fn (self $type) => ['label' => self::labels()[$type->value], 'value' => $type->value])
            ->values()
            ->all();
    }

    /**
     * The checklist row a bill of this type fills: freight is shared by weight, duties by customs line, the rest by
     * value. Anti-dumping duty is a row of its own, because it is charged on some tariff codes only. Import VAT is
     * recovered and fills none.
     */
    public function costType(): ?StockDeliveryCostTypeEnum
    {
        return match ($this) {
            self::FREIGHT, self::LOCAL_TRANSPORT => StockDeliveryCostTypeEnum::SHIPPING,
            self::DUTY, self::IMPORT_LEVY        => StockDeliveryCostTypeEnum::DUTY,
            self::TAX                            => null,
            default                              => StockDeliveryCostTypeEnum::EXTRA,
        };
    }

    /**
     * Where an extra row of this kind lands on the delivery lines: anti-dumping is a duty, everything else an extra.
     */
    public function lineCostField(): string
    {
        return $this === self::ANTI_DUMPING ? 'cost_duties' : 'cost_extra';
    }

    /**
     * A row of this kind goes only to the lines whose tariff codes the user picked, never to the whole delivery.
     */
    public function appliesByTariffCode(): bool
    {
        return $this === self::ANTI_DUMPING;
    }
}
