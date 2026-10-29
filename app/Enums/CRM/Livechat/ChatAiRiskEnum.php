<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What a drafted reply does, from harmless to the ones that cost money if wrong. Listed from the
 * lowest risk: a draft that does several things takes the highest.
 */
enum ChatAiRiskEnum: string
{
    use EnumHelperTrait;

    case INFORMATION = 'information';
    case ACCOUNT_FACTS = 'account_facts';
    case PROMISE = 'promise';
    case ORDER_CHANGE = 'order_change';
    case MONEY = 'money';

    /**
     * What each one means, for the routine and Jev. English, because it is read by a model.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'information'   => 'only general information from our pages, policies and product data: how-to, links, documents',
            'account_facts' => 'facts about this customer\'s own account or orders: status, tracking, invoice, balance',
            'promise'       => 'says we will do or check something: send, chase, arrange, call back, look into it',
            'order_change'  => 'changes, cancels or holds an order',
            'money'         => 'a refund, credit, discount, charge or price exception',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::INFORMATION   => __('Information'),
            self::ACCOUNT_FACTS => __('Account facts'),
            self::PROMISE       => __('Promise'),
            self::ORDER_CHANGE  => __('Order change'),
            self::MONEY         => __('Money'),
        };
    }
}
