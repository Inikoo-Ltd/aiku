<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\AgentPayment;

use App\Enums\EnumHelperTrait;

/**
 * A line of an agent's ledger for one client organisation: money the client sends the agent, or money the agent
 * spends from it on the client's behalf.
 */
enum AgentPaymentTypeEnum: string
{
    use EnumHelperTrait;

    case TRANSFER_RECEIVED = 'transfer_received';
    case SUPPLIER_REFUND = 'supplier_refund';
    case SUPPLIER_PAYMENT = 'supplier_payment';
    case SUPPLIER_DEPOSIT = 'supplier_deposit';
    case SUPPLIER_BALANCE = 'supplier_balance';
    case PACKAGING = 'packaging';
    case CONTAINER_SUPPLIES = 'container_supplies';
    case INSPECTION = 'inspection';
    case SAMPLES = 'samples';
    case MOULD_OR_LOGO = 'mould_or_logo';
    case CONTAINER_LOADING = 'container_loading';
    case INLAND_CHARGE = 'inland_charge';
    case AGENT_FEE = 'agent_fee';
    case OTHER_EXPENSE = 'other_expense';

    public static function labels(): array
    {
        return [
            'transfer_received' => __('Transfer received'),
            'supplier_refund'   => __('Refund from supplier'),
            'supplier_payment'  => __('Supplier payment'),
            'supplier_deposit'  => __('Supplier deposit'),
            'supplier_balance'  => __('Supplier balance'),
            'packaging'         => __('Product packaging'),
            'container_supplies' => __('Container supplies'),
            'inspection'        => __('Inspection'),
            'samples'           => __('Samples'),
            'mould_or_logo'     => __('Mould or logo'),
            'container_loading' => __('Container loading'),
            'inland_charge'     => __('Inland charge'),
            'agent_fee'         => __('Agent fee'),
            'other_expense'     => __('Other expense'),
        ];
    }

    public function isMoneyIn(): bool
    {
        return in_array($this, [self::TRANSFER_RECEIVED, self::SUPPLIER_REFUND], true);
    }

    public function isSupplierPayment(): bool
    {
        return in_array($this, [self::SUPPLIER_PAYMENT, self::SUPPLIER_DEPOSIT, self::SUPPLIER_BALANCE], true);
    }

    public function isCharge(): bool
    {
        return !$this->isMoneyIn() && !$this->isSupplierPayment();
    }

    /**
     * @return list<string>
     */
    public static function moneyInValues(): array
    {
        return array_values(array_map(fn (self $type) => $type->value, array_filter(self::cases(), fn (self $type) => $type->isMoneyIn())));
    }

    /**
     * @return list<string>
     */
    public static function supplierPaymentValues(): array
    {
        return array_values(array_map(fn (self $type) => $type->value, array_filter(self::cases(), fn (self $type) => $type->isSupplierPayment())));
    }
}
