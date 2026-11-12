<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\User;

use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;

enum UserNotificationEnum: string
{
    case TICKET_RESOLVED = 'ticket_resolved';
    case TICKET_COMMENT = 'ticket_comment';
    case TICKET_MENTION = 'ticket_mention';
    case TICKET_NEEDS_REPLY = 'ticket_needs_reply';
    case PURCHASE_ORDER_STAGE_BY_AGENT = 'purchase_order_stage_by_agent';
    case PURCHASE_ORDER_PRICE_ANSWERED = 'purchase_order_price_answered';
    case PURCHASE_ORDERS_STUCK = 'purchase_orders_stuck';
    case PURCHASE_ORDER_COMPLIANCE_DUE = 'purchase_order_compliance_due';
    case PURCHASE_ORDER_MINIMUM_POKE = 'purchase_order_minimum_poke';
    case PURCHASE_ORDER_BALANCE_DUE = 'purchase_order_balance_due';
    case MONEY_REQUEST_CREATED = 'money_request_created';
    case MONEY_REQUEST_APPROVED = 'money_request_approved';
    case MONEY_REQUEST_PAID = 'money_request_paid';
    case MONEY_REQUEST_UNAPPROVED = 'money_request_unapproved';
    case MONEY_REQUEST_REJECTED = 'money_request_rejected';
    case MONEY_REQUEST_CANCELLED = 'money_request_cancelled';
    case SUPPLIER_PRODUCT_AVAILABILITY = 'supplier_product_availability';
    case PRODUCT_DEVELOPMENT_TASK = 'product_development_task';
    case PRODUCT_DEVELOPMENT_MENTION = 'product_development_mention';
    case PRODUCT_DEVELOPMENT_FILES = 'product_development_files';
    case PRODUCT_DEVELOPMENT_DIGEST = 'product_development_digest';
    case GOOGLE_ADS_CLICK_CAPTURE_DROP = 'google_ads_click_capture_drop';
    case MANUFACTURE_PAYROLL_CHANGED = 'manufacture_payroll_changed';
    case MANUFACTURE_OVERTIME_REMINDER = 'manufacture_overtime_reminder';
    case SALES_LEAD = 'sales_lead';
    case RESTOCK_QUESTION = 'restock_question';

    public const array PRODUCT_DEVELOPMENT_EVENTS = [
        self::PRODUCT_DEVELOPMENT_TASK,
        self::PRODUCT_DEVELOPMENT_MENTION,
        self::PRODUCT_DEVELOPMENT_FILES,
        self::PRODUCT_DEVELOPMENT_DIGEST,
    ];

    public const array PROCUREMENT_EVENTS = [
        self::PURCHASE_ORDER_STAGE_BY_AGENT,
        self::PURCHASE_ORDER_PRICE_ANSWERED,
        self::PURCHASE_ORDERS_STUCK,
        self::PURCHASE_ORDER_COMPLIANCE_DUE,
        self::PURCHASE_ORDER_MINIMUM_POKE,
        self::PURCHASE_ORDER_BALANCE_DUE,
        self::MONEY_REQUEST_CREATED,
        self::MONEY_REQUEST_APPROVED,
        self::MONEY_REQUEST_PAID,
        self::MONEY_REQUEST_UNAPPROVED,
        self::MONEY_REQUEST_REJECTED,
        self::MONEY_REQUEST_CANCELLED,
        self::SUPPLIER_PRODUCT_AVAILABILITY,
    ];

    public const array CHANNELS = ['email', 'slack', 'browser'];

    public function label(): string
    {
        return match ($this) {
            self::TICKET_RESOLVED    => __('My ticket is resolved'),
            self::TICKET_COMMENT     => __('New comment on my ticket'),
            self::TICKET_MENTION     => __('Someone @mentions me on a ticket'),
            self::TICKET_NEEDS_REPLY => __('My ticket needs more information from me'),
            self::PURCHASE_ORDER_STAGE_BY_AGENT => __('An agent moves one of my purchase orders to another stage'),
            self::PURCHASE_ORDER_PRICE_ANSWERED => __('An organisation answers a new price or quantity I asked about'),
            self::PURCHASE_ORDERS_STUCK         => __('The weekly list of purchase orders past their expected date'),
            self::PURCHASE_ORDER_COMPLIANCE_DUE => __('Compliance documents of my purchase order are due'),
            self::PURCHASE_ORDER_BALANCE_DUE    => __('A purchase order passed its QC and its balance can be paid'),
            self::MONEY_REQUEST_CREATED         => __('An agent asks for money on a money request I approve, pay or am copied on'),
            self::MONEY_REQUEST_APPROVED        => __('The client approves a money request I asked for or am copied on'),
            self::MONEY_REQUEST_PAID            => __('A money request I asked for or am copied on is paid'),
            self::MONEY_REQUEST_UNAPPROVED      => __('The client cancels its approval of a money request I asked for'),
            self::MONEY_REQUEST_REJECTED        => __('The client rejects a money request I asked for or am copied on'),
            self::MONEY_REQUEST_CANCELLED       => __('An agent withdraws a money request I approve, pay or am copied on'),
            self::SUPPLIER_PRODUCT_AVAILABILITY => __('An agent changes the availability of a product my organisation buys'),
            self::PRODUCT_DEVELOPMENT_TASK      => __('A product development gives me a task or reminds me of one'),
            self::PRODUCT_DEVELOPMENT_MENTION   => __('Someone @mentions me on a product development'),
            self::PRODUCT_DEVELOPMENT_FILES     => __('A file I need is uploaded to one of my product developments'),
            self::PRODUCT_DEVELOPMENT_DIGEST    => __('The daily summary of the product developments I own'),
            self::GOOGLE_ADS_CLICK_CAPTURE_DROP => __('Aiku recorded fewer than 85% of yesterday\'s Google Ads clicks of a shop I market'),
            self::MANUFACTURE_PAYROLL_CHANGED   => __('The production manager changes a week already locked for payroll'),
            self::MANUFACTURE_OVERTIME_REMINDER => __('On Monday, the overtime of last week nobody has approved or rejected yet, before payroll locks'),
            self::PURCHASE_ORDER_MINIMUM_POKE   => __('Another organisation asks me to add to my draft to reach a supplier minimum (email and Slack come grouped every 15 minutes)'),
            self::SALES_LEAD                    => __('A customer of my organisation asks the AI assistant for a call about a big order, and when nobody has taken it yet'),
            self::RESTOCK_QUESTION              => __('A customer of my organisation asks the AI assistant when a product will be back in stock and we have no reliable date, and when nobody has answered yet'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function channelsFor(User $user): array
    {
        $chosen = Arr::get($user->settings, 'notifications.'.$this->value);
        if (is_array($chosen)) {
            return array_values(array_intersect($chosen, self::CHANNELS));
        }

        if ($this === self::TICKET_COMMENT) {
            return [];
        }

        if ($this === self::GOOGLE_ADS_CLICK_CAPTURE_DROP || $this === self::MANUFACTURE_PAYROLL_CHANGED || $this === self::MANUFACTURE_OVERTIME_REMINDER || in_array($this, self::PROCUREMENT_EVENTS, true)) {
            return ['email'];
        }

        if ($this === self::SALES_LEAD || $this === self::RESTOCK_QUESTION) {
            return self::CHANNELS;
        }

        if (in_array($this, self::PRODUCT_DEVELOPMENT_EVENTS, true)) {
            return ['slack', 'browser'];
        }

        return match (Arr::get($user->settings, 'ticket_notifications', 'both')) {
            'email' => ['email'],
            'slack' => ['slack'],
            'none'  => [],
            default => ['email', 'slack'],
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TICKET_RESOLVED    => 'fal fa-check-double',
            self::TICKET_COMMENT     => 'fal fa-comment-lines',
            self::TICKET_MENTION     => 'fal fa-at',
            self::TICKET_NEEDS_REPLY => 'fal fa-question-circle',
            self::PURCHASE_ORDER_STAGE_BY_AGENT => 'fal fa-truck-loading',
            self::PURCHASE_ORDER_PRICE_ANSWERED => 'fal fa-tags',
            self::PURCHASE_ORDERS_STUCK         => 'fal fa-hourglass-half',
            self::PURCHASE_ORDER_COMPLIANCE_DUE => 'fal fa-clipboard-check',
            self::PURCHASE_ORDER_BALANCE_DUE    => 'fal fa-file-invoice',
            self::MONEY_REQUEST_CREATED         => 'fal fa-money-check-alt',
            self::MONEY_REQUEST_APPROVED        => 'fal fa-thumbs-up',
            self::MONEY_REQUEST_PAID            => 'fal fa-hand-holding-usd',
            self::MONEY_REQUEST_UNAPPROVED      => 'fal fa-undo',
            self::MONEY_REQUEST_REJECTED        => 'fal fa-ban',
            self::MONEY_REQUEST_CANCELLED       => 'fal fa-times-circle',
            self::SUPPLIER_PRODUCT_AVAILABILITY => 'fal fa-box-check',
            self::PURCHASE_ORDER_MINIMUM_POKE   => 'fal fa-hand-point-right',
            self::PRODUCT_DEVELOPMENT_TASK      => 'fal fa-lightbulb-on',
            self::PRODUCT_DEVELOPMENT_MENTION   => 'fal fa-at',
            self::PRODUCT_DEVELOPMENT_FILES     => 'fal fa-paperclip',
            self::PRODUCT_DEVELOPMENT_DIGEST    => 'fal fa-list',
            self::GOOGLE_ADS_CLICK_CAPTURE_DROP => 'fal fa-chart-line-down',
            self::MANUFACTURE_PAYROLL_CHANGED   => 'fal fa-lock',
            self::MANUFACTURE_OVERTIME_REMINDER => 'fal fa-user-clock',
            self::SALES_LEAD                    => 'fal fa-phone-alt',
            self::RESTOCK_QUESTION              => 'fal fa-box-open',
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function valuesFor(User $user): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $event) => [$event->value => $event->channelsFor($user)])->all();
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $event) => ['value' => $event->value, 'label' => $event->label(), 'icon' => $event->icon()])->all();
    }
}
