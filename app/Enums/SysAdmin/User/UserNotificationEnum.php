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
    case PURCHASE_ORDER_DEPOSIT_REQUESTED = 'purchase_order_deposit_requested';
    case MONEY_REQUEST_CREATED = 'money_request_created';
    case MONEY_REQUEST_APPROVED = 'money_request_approved';
    case MONEY_REQUEST_PAID = 'money_request_paid';
    case MONEY_REQUEST_REJECTED = 'money_request_rejected';
    case MONEY_REQUEST_CANCELLED = 'money_request_cancelled';
    case MONEY_REQUEST_PAYMENT_UNDONE = 'money_request_payment_undone';
    case SUPPLIER_PRODUCT_AVAILABILITY = 'supplier_product_availability';
    case SUPPLIER_MATERIAL_PLANNING = 'supplier_material_planning';
    case SUPPLIER_PLANNING_OVERDUE = 'supplier_planning_overdue';
    case SUPPLIER_COMPLIANCE_DOCUMENT = 'supplier_compliance_document';
    case SUPPLIER_COMPLIANCE_OVERRIDDEN = 'supplier_compliance_overridden';
    case PARTNER_LINE_CANNOT_BE_MADE = 'partner_line_cannot_be_made';
    case PRODUCT_DEVELOPMENT_TASK = 'product_development_task';
    case PRODUCT_DEVELOPMENT_MENTION = 'product_development_mention';
    case PRODUCT_DEVELOPMENT_FILES = 'product_development_files';
    case PRODUCT_DEVELOPMENT_DIGEST = 'product_development_digest';
    case GOOGLE_ADS_CLICK_CAPTURE_DROP = 'google_ads_click_capture_drop';
    case MANUFACTURE_PAYROLL_CHANGED = 'manufacture_payroll_changed';
    case MANUFACTURE_OVERTIME_REMINDER = 'manufacture_overtime_reminder';
    case PRODUCTION_SHORTFALL = 'production_shortfall';
    case SALES_LEAD = 'sales_lead';
    case RESTOCK_QUESTION = 'restock_question';
    case ASSISTANT_QUESTION = 'assistant_question';
    case DELIVERY_FEEDBACK = 'delivery_feedback';
    case SLACK_MENTION = 'slack_mention';
    case E_INVOICE_PROBLEM = 'e_invoice_problem';
    case IMPLAUSIBLE_STOCK_COUNT = 'implausible_stock_count';

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
        self::PURCHASE_ORDER_DEPOSIT_REQUESTED,
        self::MONEY_REQUEST_CREATED,
        self::MONEY_REQUEST_APPROVED,
        self::MONEY_REQUEST_PAID,
        self::MONEY_REQUEST_REJECTED,
        self::MONEY_REQUEST_CANCELLED,
        self::MONEY_REQUEST_PAYMENT_UNDONE,
        self::SUPPLIER_PRODUCT_AVAILABILITY,
        self::SUPPLIER_MATERIAL_PLANNING,
        self::SUPPLIER_PLANNING_OVERDUE,
        self::SUPPLIER_COMPLIANCE_DOCUMENT,
        self::SUPPLIER_COMPLIANCE_OVERRIDDEN,
        self::PARTNER_LINE_CANNOT_BE_MADE,
        self::DELIVERY_FEEDBACK,
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
            self::PURCHASE_ORDER_BALANCE_DUE    => __('A purchase order passed its QC and its balance can be paid, or its agent reminds me to pay it'),
            self::PURCHASE_ORDER_DEPOSIT_REQUESTED => __('A supplier asks for a deposit on a purchase order I buy or my organisation buys or handles as agent'),
            self::MONEY_REQUEST_CREATED         => __('An agent asks for money on a money request I approve, pay or am copied on'),
            self::MONEY_REQUEST_APPROVED        => __('The client approves a money request I asked for or am copied on'),
            self::MONEY_REQUEST_PAID            => __('A money request I asked for or am copied on is paid'),
            self::MONEY_REQUEST_REJECTED        => __('The client declines a money request I asked for or am copied on'),
            self::MONEY_REQUEST_CANCELLED       => __('An agent withdraws a money request I approve, pay or am copied on'),
            self::MONEY_REQUEST_PAYMENT_UNDONE  => __('The client undoes a payment set from its bank on a money request I asked for or am copied on'),
            self::SUPPLIER_PRODUCT_AVAILABILITY => __('An agent changes the availability of a product my organisation buys'),
            self::SUPPLIER_MATERIAL_PLANNING    => __('A supplier sends material planning notes or documents for a product my organisation buys'),
            self::SUPPLIER_PLANNING_OVERDUE     => __('A supplier material planning note is still not answered after the days set in the supplier page settings'),
            self::SUPPLIER_COMPLIANCE_DOCUMENT  => __('A supplier sends a compliance document for a product my organisation buys, or that I review as compliance'),
            self::SUPPLIER_COMPLIANCE_OVERRIDDEN => __('The compliance team changes my decision on a supplier compliance document'),
            self::PARTNER_LINE_CANNOT_BE_MADE   => __('A partner\'s production cannot make a line I sent to it, so it leaves my Orders list'),
            self::PRODUCT_DEVELOPMENT_TASK      => __('A product development gives me a task or reminds me of one'),
            self::PRODUCT_DEVELOPMENT_MENTION   => __('Someone @mentions me on a product development'),
            self::PRODUCT_DEVELOPMENT_FILES     => __('A file I need is uploaded to one of my product developments'),
            self::PRODUCT_DEVELOPMENT_DIGEST    => __('The daily summary of the product developments I own'),
            self::GOOGLE_ADS_CLICK_CAPTURE_DROP => __('Aiku recorded fewer than 85% of yesterday\'s Google Ads clicks of a shop I market'),
            self::MANUFACTURE_PAYROLL_CHANGED   => __('The production manager changes a week already locked for payroll'),
            self::MANUFACTURE_OVERTIME_REMINDER => __('On Monday, the overtime of last week nobody has approved or rejected yet, before payroll locks'),
            self::PRODUCTION_SHORTFALL          => __('An order needs products made that are not in stock, and they wait on the To Produce board'),
            self::PURCHASE_ORDER_MINIMUM_POKE   => __('Another organisation asks me to add to my draft to reach a supplier minimum (email and Slack come grouped every 15 minutes)'),
            self::SALES_LEAD                    => __('A customer of my organisation asks the AI assistant for a call about a big order, and when nobody has taken it yet'),
            self::RESTOCK_QUESTION              => __('A customer of my organisation asks the AI assistant when a product will be back in stock and we have no reliable date, and when nobody has answered yet'),
            self::ASSISTANT_QUESTION            => __('A customer of my organisation asks the AI assistant something only our team can answer, and I am the person or the backup who answers'),
            self::DELIVERY_FEEDBACK             => __('Someone gives feedback on a delivery I bought'),
            self::SLACK_MENTION                 => __('Someone @mentions me in a Slack channel (off until you pick a channel; the notification inside Aiku comes with it)'),
            self::E_INVOICE_PROBLEM             => __('An invoice of my organisation could not be sent as an e-invoice, or the customer\'s system refused it'),
            self::IMPLAUSIBLE_STOCK_COUNT       => __('A stock count changes the stock of an SKO by an implausible amount in an organisation whose stock I can see (off until you pick a channel; the notification inside Aiku comes with it)'),
        };
    }

    /**
     * The channels to send on now: the chosen ones, without the interrupting Slack and push while
     * the user is in focus mode (email and the bell still arrive, they wait to be read).
     *
     * @return array<int, string>
     */
    public function deliverTo(User $user): array
    {
        $channels = $this->channelsFor($user);

        return $user->isFocusing() ? array_values(array_diff($channels, ['slack', 'browser'])) : $channels;
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

        if ($this === self::TICKET_COMMENT || $this === self::SLACK_MENTION || $this === self::IMPLAUSIBLE_STOCK_COUNT) {
            return [];
        }

        if ($this === self::GOOGLE_ADS_CLICK_CAPTURE_DROP || $this === self::MANUFACTURE_PAYROLL_CHANGED || $this === self::MANUFACTURE_OVERTIME_REMINDER || $this === self::E_INVOICE_PROBLEM || in_array($this, self::PROCUREMENT_EVENTS, true)) {
            return ['email'];
        }

        if ($this === self::PRODUCTION_SHORTFALL) {
            return ['email', 'browser'];
        }

        if ($this === self::SALES_LEAD || $this === self::RESTOCK_QUESTION || $this === self::ASSISTANT_QUESTION) {
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
            self::PURCHASE_ORDER_DEPOSIT_REQUESTED => 'fal fa-coins',
            self::MONEY_REQUEST_CREATED         => 'fal fa-money-check-alt',
            self::MONEY_REQUEST_APPROVED        => 'fal fa-thumbs-up',
            self::MONEY_REQUEST_PAID            => 'fal fa-hand-holding-usd',
            self::MONEY_REQUEST_REJECTED        => 'fal fa-ban',
            self::MONEY_REQUEST_CANCELLED       => 'fal fa-times-circle',
            self::MONEY_REQUEST_PAYMENT_UNDONE  => 'fal fa-undo',
            self::SUPPLIER_PRODUCT_AVAILABILITY => 'fal fa-box-check',
            self::SUPPLIER_MATERIAL_PLANNING    => 'fal fa-tree',
            self::SUPPLIER_PLANNING_OVERDUE     => 'fal fa-hourglass-end',
            self::SUPPLIER_COMPLIANCE_DOCUMENT  => 'fal fa-clipboard-check',
            self::SUPPLIER_COMPLIANCE_OVERRIDDEN => 'fal fa-undo',
            self::PARTNER_LINE_CANNOT_BE_MADE   => 'fal fa-industry-alt',
            self::PURCHASE_ORDER_MINIMUM_POKE   => 'fal fa-hand-point-right',
            self::PRODUCT_DEVELOPMENT_TASK      => 'fal fa-lightbulb-on',
            self::PRODUCT_DEVELOPMENT_MENTION   => 'fal fa-at',
            self::PRODUCT_DEVELOPMENT_FILES     => 'fal fa-paperclip',
            self::PRODUCT_DEVELOPMENT_DIGEST    => 'fal fa-list',
            self::GOOGLE_ADS_CLICK_CAPTURE_DROP => 'fal fa-chart-line-down',
            self::MANUFACTURE_PAYROLL_CHANGED   => 'fal fa-lock',
            self::MANUFACTURE_OVERTIME_REMINDER => 'fal fa-user-clock',
            self::PRODUCTION_SHORTFALL          => 'fal fa-industry-alt',
            self::SALES_LEAD                    => 'fal fa-phone-alt',
            self::RESTOCK_QUESTION              => 'fal fa-box-open',
            self::ASSISTANT_QUESTION            => 'fal fa-head-side-brain',
            self::DELIVERY_FEEDBACK             => 'fal fa-star',
            self::SLACK_MENTION                 => 'fal fa-at',
            self::E_INVOICE_PROBLEM             => 'fal fa-file-invoice',
            self::IMPLAUSIBLE_STOCK_COUNT       => 'fal fa-exclamation-triangle',
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
