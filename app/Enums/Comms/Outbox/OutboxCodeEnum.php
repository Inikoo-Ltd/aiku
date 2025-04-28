<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Outbox;

use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunTypeEnum;
use App\Enums\Comms\PostRoom\PostRoomCodeEnum;
use App\Enums\EnumHelperTrait;

enum OutboxCodeEnum: string
{
    use EnumHelperTrait;

    case BASKET_LOW_STOCK = 'basket_low_stock';
    case BASKET_PUSH = 'basket_push';
    case NEW_CUSTOMER_PUSH = 'new_customer_push';
    case NEW_CUSTOMER = 'new_customer';
    case DELIVERY_NOTE_DISPATCHED = 'delivery_note_dispatched';
    case DELIVERY_NOTE_UNDISPATCHED = 'delivery_note_undispatched';
    case INVOICE_DELETED = 'invoice_deleted';
    case NEW_ORDER = 'new_order';
    case ABANDONED_CART = 'abandoned_cart';
    case DELIVERY_CONFIRMATION = 'delivery_confirmation';
    case SEND_INVOICE_TO_CUSTOMER = 'send_invoice_to_customer';
    case REORDER_REMINDER = 'reorder_reminder';
    case MARKETING = 'marketing';
    case NEWSLETTER = 'newsletter';
    case OOS_NOTIFICATION = 'oos_notification';
    case ORDER_CONFIRMATION = 'order_confirmation';
    case PASSWORD_REMINDER = 'password_reminder';
    case REGISTRATION = 'registration';
    case REGISTRATION_APPROVED = 'registration_approved';
    case REGISTRATION_REJECTED = 'registration_rejected';
    case RENTAL_AGREEMENT = 'rental_agreement';
    case PALLET_DELIVERY_PROCESSED = 'pallet_delivery_processed';
    case PALLET_RETURN_DISPATCHED = 'pallet_return_dispatched';
    case NEW_PALLET_RETURN_FROM_CUSTOMER = 'new_pallet_return_from_customer';
    case NEW_PALLET_DELIVERY_FROM_CUSTOMER = 'new_pallet_delivery_from_customer';
    case PALLET_DELIVERY_DELETED = 'pallet_delivery_deleted';
    case PALLET_RETURN_DELETED = 'pallet_return_deleted';
    case PALLET_DELETED = 'pallet_deleted';
    case INVITE = 'invite';
    case TEST = 'test';
    case NEW_CONTACTER = 'new_contacter';


    public function type(): OutboxTypeEnum
    {
        return match ($this) {
            OutboxCodeEnum::NEWSLETTER => OutboxTypeEnum::NEWSLETTER,
            OutboxCodeEnum::MARKETING,
            OutboxCodeEnum::ABANDONED_CART


            => OutboxTypeEnum::MARKETING,
            OutboxCodeEnum::INVITE => OutboxTypeEnum::COLD_EMAIL,


            OutboxCodeEnum::REGISTRATION,
            OutboxCodeEnum::REGISTRATION_APPROVED,
            OutboxCodeEnum::REGISTRATION_REJECTED,
            OutboxCodeEnum::PASSWORD_REMINDER,
            OutboxCodeEnum::DELIVERY_CONFIRMATION,
            OutboxCodeEnum::OOS_NOTIFICATION,
            OutboxCodeEnum::ORDER_CONFIRMATION,
            OutboxCodeEnum::RENTAL_AGREEMENT,
            OutboxCodeEnum::PALLET_DELIVERY_PROCESSED,
            OutboxCodeEnum::PALLET_RETURN_DISPATCHED,
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
            OutboxCodeEnum::NEW_CONTACTER
            => OutboxTypeEnum::CUSTOMER_NOTIFICATION,

            OutboxCodeEnum::BASKET_LOW_STOCK,


            OutboxCodeEnum::REORDER_REMINDER => OutboxTypeEnum::MARKETING_NOTIFICATION,
            OutboxCodeEnum::TEST => OutboxTypeEnum::TEST,

            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER,
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER,
            OutboxCodeEnum::PALLET_DELIVERY_DELETED,
            OutboxCodeEnum::PALLET_RETURN_DELETED,
            OutboxCodeEnum::PALLET_DELETED,
            OutboxCodeEnum::NEW_CUSTOMER,
            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED,
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            OutboxCodeEnum::INVOICE_DELETED,
            OutboxCodeEnum::NEW_ORDER
            => OutboxTypeEnum::USER_NOTIFICATION,
            OutboxCodeEnum::BASKET_PUSH, OutboxCodeEnum::NEW_CUSTOMER_PUSH
            => OutboxTypeEnum::PUSH,
        };
    }

    public function label(): string
    {
        return match ($this) {
            OutboxCodeEnum::BASKET_LOW_STOCK => 'Low stock in basket',
            OutboxCodeEnum::BASKET_PUSH => 'Basket push',
            OutboxCodeEnum::NEW_CUSTOMER_PUSH => 'New customer push',
            OutboxCodeEnum::NEW_CUSTOMER => 'New registration',
            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED => 'Delivery note dispatched',
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED => 'Delivery note undispatched',
            OutboxCodeEnum::INVOICE_DELETED => 'Invoice deleted',
            OutboxCodeEnum::NEW_ORDER => 'New order',
            OutboxCodeEnum::ABANDONED_CART => 'Abandoned cart',
            OutboxCodeEnum::DELIVERY_CONFIRMATION => 'Delivery confirmation',
            OutboxCodeEnum::REORDER_REMINDER => 'Reorder reminder',
            OutboxCodeEnum::MARKETING => 'Marketing',
            OutboxCodeEnum::NEWSLETTER => 'Newsletter',
            OutboxCodeEnum::OOS_NOTIFICATION => 'Out of stock notification',
            OutboxCodeEnum::ORDER_CONFIRMATION => 'Order confirmation',
            OutboxCodeEnum::PASSWORD_REMINDER => 'Password reminder',
            OutboxCodeEnum::REGISTRATION => 'Registration',
            OutboxCodeEnum::REGISTRATION_APPROVED => 'Registration approved',
            OutboxCodeEnum::REGISTRATION_REJECTED => 'Registration rejected',
            OutboxCodeEnum::TEST => __('Test'),
            OutboxCodeEnum::RENTAL_AGREEMENT => 'Rental agreement',
            OutboxCodeEnum::PALLET_DELIVERY_PROCESSED => 'Pallet delivery processed',
            OutboxCodeEnum::PALLET_RETURN_DISPATCHED => 'Pallet return dispatched',
            OutboxCodeEnum::INVITE => 'Invite',
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER => 'Send invoice to customer',
            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER => 'New pallet return from customer',
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER => 'New pallet delivery from customer',
            OutboxCodeEnum::PALLET_DELIVERY_DELETED => 'Pallet delivery deleted',
            OutboxCodeEnum::PALLET_RETURN_DELETED => 'Pallet return deleted',
            OutboxCodeEnum::PALLET_DELETED => 'Pallet deleted',
        };
    }


    public function layout(): string
    {
        return match ($this) {
            OutboxCodeEnum::BASKET_LOW_STOCK => 'Low stock in basket',
            OutboxCodeEnum::BASKET_PUSH => 'Basket push',
            OutboxCodeEnum::NEW_CUSTOMER_PUSH => 'New customer push',
            OutboxCodeEnum::NEW_CUSTOMER => 'New customer',
            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED => 'Delivery note dispatched',
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED => 'Delivery note undispatched',
            OutboxCodeEnum::INVOICE_DELETED => 'Invoice deleted',
            OutboxCodeEnum::NEW_ORDER => 'New order',
            OutboxCodeEnum::ABANDONED_CART => 'Abandoned cart',
            OutboxCodeEnum::DELIVERY_CONFIRMATION => 'Delivery conformation',
            OutboxCodeEnum::REORDER_REMINDER => 'Reorder reminder',
            OutboxCodeEnum::MARKETING => 'Deals',
            OutboxCodeEnum::NEWSLETTER => 'Newsletter',
            OutboxCodeEnum::OOS_NOTIFICATION => 'Out of stock notification',
            OutboxCodeEnum::ORDER_CONFIRMATION => 'Order confirmation',
            OutboxCodeEnum::PASSWORD_REMINDER => 'Password reminder',
            OutboxCodeEnum::REGISTRATION => 'Registration',
            OutboxCodeEnum::REGISTRATION_APPROVED => 'Registration approved',
            OutboxCodeEnum::REGISTRATION_REJECTED => 'Registration rejected',
            OutboxCodeEnum::TEST => __('Test'),
            OutboxCodeEnum::RENTAL_AGREEMENT => 'Rental agreement',
            OutboxCodeEnum::PALLET_DELIVERY_PROCESSED => 'Pallet delivery processed',
            OutboxCodeEnum::PALLET_RETURN_DISPATCHED => 'Pallet return dispatched',
            OutboxCodeEnum::INVITE => 'Invite',
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER => 'Send invoice to customer',
            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER => 'New pallet return from customer',
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER => 'New pallet delivery from customer',
            OutboxCodeEnum::PALLET_DELIVERY_DELETED => 'Pallet delivery deleted',
            OutboxCodeEnum::PALLET_RETURN_DELETED => 'Pallet return deleted',
            OutboxCodeEnum::PALLET_DELETED => 'Pallet deleted',
        };
    }

    public function scope(): array
    {
        return match ($this) {
            OutboxCodeEnum::TEST => ['Organisation'],
            OutboxCodeEnum::PASSWORD_REMINDER,
            OutboxCodeEnum::BASKET_LOW_STOCK,
            OutboxCodeEnum::BASKET_PUSH,
            OutboxCodeEnum::NEW_CUSTOMER_PUSH,
            OutboxCodeEnum::ABANDONED_CART,
            OutboxCodeEnum::REORDER_REMINDER,
            OutboxCodeEnum::REGISTRATION,
            OutboxCodeEnum::REGISTRATION_APPROVED,
            OutboxCodeEnum::REGISTRATION_REJECTED,
            => ['Website'],
            OutboxCodeEnum::NEW_CUSTOMER,
            OutboxCodeEnum::INVOICE_DELETED,
            OutboxCodeEnum::NEWSLETTER,
            OutboxCodeEnum::MARKETING,
            OutboxCodeEnum::INVITE,
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
            => ['Fulfilment', 'Shop'],

            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER,
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER,
            OutboxCodeEnum::PALLET_DELIVERY_DELETED,
            OutboxCodeEnum::PALLET_RETURN_DELETED,
            OutboxCodeEnum::PALLET_DELETED,
            OutboxCodeEnum::RENTAL_AGREEMENT,
            OutboxCodeEnum::PALLET_DELIVERY_PROCESSED,
            OutboxCodeEnum::PALLET_RETURN_DISPATCHED => ['Fulfilment'],
            default => ['Shop']
        };
    }


    public function shopTypes(): array
    {
        return match ($this) {
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
            OutboxCodeEnum::INVOICE_DELETED,
            OutboxCodeEnum::MARKETING,
            OutboxCodeEnum::NEWSLETTER,
            OutboxCodeEnum::PASSWORD_REMINDER => ['b2b', 'b2c', 'dropshipping', 'fulfilment'],

            OutboxCodeEnum::REGISTRATION,
            OutboxCodeEnum::REGISTRATION_APPROVED,
            OutboxCodeEnum::INVITE,
            OutboxCodeEnum::REGISTRATION_REJECTED => ['b2b', 'dropshipping', 'fulfilment'],
            OutboxCodeEnum::NEW_CUSTOMER,
            OutboxCodeEnum::NEW_ORDER,
            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED,
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            OutboxCodeEnum::DELIVERY_CONFIRMATION,
            OutboxCodeEnum::ORDER_CONFIRMATION => ['b2b', 'b2c', 'dropshipping'],
            OutboxCodeEnum::OOS_NOTIFICATION => ['b2b', 'dropshipping'],
            OutboxCodeEnum::BASKET_LOW_STOCK,
            OutboxCodeEnum::ABANDONED_CART,
            OutboxCodeEnum::REORDER_REMINDER,
            OutboxCodeEnum::BASKET_PUSH,
            OutboxCodeEnum::NEW_CUSTOMER_PUSH
            => ['b2b'],

            default => []
        };
    }


    public function defaultState(): OutboxStateEnum
    {
        return match ($this) {
            OutboxCodeEnum::MARKETING,
            OutboxCodeEnum::NEWSLETTER,
            OutboxCodeEnum::INVITE,
            OutboxCodeEnum::TEST,
            => OutboxStateEnum::ACTIVE,
            default => OutboxStateEnum::IN_PROCESS
        };
    }

    public function defaultBuilder(): ?OutboxBuilderEnum
    {
        return match ($this) {
            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER,
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER,
            OutboxCodeEnum::PALLET_DELIVERY_DELETED,
            OutboxCodeEnum::PALLET_RETURN_DELETED,
            OutboxCodeEnum::PALLET_DELETED,
            OutboxCodeEnum::NEW_CUSTOMER,
            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED,
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            OutboxCodeEnum::INVOICE_DELETED,
            OutboxCodeEnum::NEW_ORDER
            => OutboxBuilderEnum::BLADE,
            default => null
        };
    }

    public function modelType(): ?string
    {
        return match ($this) {
            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER,
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER,
            OutboxCodeEnum::PALLET_DELIVERY_DELETED,
            OutboxCodeEnum::PALLET_RETURN_DELETED,
            OutboxCodeEnum::PALLET_DELETED,
            OutboxCodeEnum::NEW_CUSTOMER,
            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED,
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            OutboxCodeEnum::INVOICE_DELETED,
            OutboxCodeEnum::NEW_ORDER,
            OutboxCodeEnum::DELIVERY_CONFIRMATION,
            OutboxCodeEnum::ORDER_CONFIRMATION,
            OutboxCodeEnum::PASSWORD_REMINDER,
            OutboxCodeEnum::REGISTRATION,
            OutboxCodeEnum::REGISTRATION_APPROVED,
            OutboxCodeEnum::REGISTRATION_REJECTED,
            OutboxCodeEnum::RENTAL_AGREEMENT,
            OutboxCodeEnum::PALLET_DELIVERY_PROCESSED,
            OutboxCodeEnum::PALLET_RETURN_DISPATCHED,
            OutboxCodeEnum::BASKET_LOW_STOCK,
            OutboxCodeEnum::BASKET_PUSH,
            OutboxCodeEnum::NEW_CUSTOMER_PUSH,
            OutboxCodeEnum::REORDER_REMINDER,
            OutboxCodeEnum::OOS_NOTIFICATION,
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER

            => 'EmailOngoingRun',
            OutboxCodeEnum::MARKETING,
            OutboxCodeEnum::NEWSLETTER,
            OutboxCodeEnum::INVITE,
            OutboxCodeEnum::ABANDONED_CART,
            => 'Mailshot',

            default => null
        };
    }

    public function emailOngoingRunType(): ?EmailOngoingRunTypeEnum
    {
        return match ($this) {
            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER,
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER,
            OutboxCodeEnum::PALLET_DELIVERY_DELETED,
            OutboxCodeEnum::PALLET_RETURN_DELETED,
            OutboxCodeEnum::PALLET_DELETED,
            OutboxCodeEnum::NEW_CUSTOMER,
            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED,
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            OutboxCodeEnum::INVOICE_DELETED,
            OutboxCodeEnum::NEW_ORDER,
            OutboxCodeEnum::DELIVERY_CONFIRMATION,
            OutboxCodeEnum::ORDER_CONFIRMATION,
            OutboxCodeEnum::PASSWORD_REMINDER,
            OutboxCodeEnum::REGISTRATION,
            OutboxCodeEnum::REGISTRATION_APPROVED,
            OutboxCodeEnum::REGISTRATION_REJECTED,
            OutboxCodeEnum::RENTAL_AGREEMENT,
            OutboxCodeEnum::PALLET_DELIVERY_PROCESSED,
            OutboxCodeEnum::PALLET_RETURN_DISPATCHED,
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER
            => EmailOngoingRunTypeEnum::TRANSACTIONAL,
            OutboxCodeEnum::BASKET_LOW_STOCK,
            OutboxCodeEnum::REORDER_REMINDER,
            OutboxCodeEnum::OOS_NOTIFICATION,
            => EmailOngoingRunTypeEnum::BULK,
            OutboxCodeEnum::BASKET_PUSH,
            OutboxCodeEnum::NEW_CUSTOMER_PUSH,
            => EmailOngoingRunTypeEnum::PUSH,

            default => null
        };
    }


    public function postRoomCode(): PostRoomCodeEnum
    {
        return match ($this) {
            OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER,
            OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER,
            OutboxCodeEnum::PALLET_DELIVERY_DELETED,
            OutboxCodeEnum::PALLET_RETURN_DELETED,
            OutboxCodeEnum::PALLET_DELETED,
            OutboxCodeEnum::BASKET_LOW_STOCK,

            OutboxCodeEnum::REGISTRATION,
            OutboxCodeEnum::REGISTRATION_APPROVED,
            OutboxCodeEnum::REGISTRATION_REJECTED,
            OutboxCodeEnum::PASSWORD_REMINDER,
            OutboxCodeEnum::DELIVERY_CONFIRMATION,
            OutboxCodeEnum::OOS_NOTIFICATION,
            OutboxCodeEnum::ORDER_CONFIRMATION,
            OutboxCodeEnum::RENTAL_AGREEMENT,
            OutboxCodeEnum::PALLET_DELIVERY_PROCESSED,
            OutboxCodeEnum::PALLET_RETURN_DISPATCHED,
            OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER
            => PostRoomCodeEnum::CUSTOMER_NOTIFICATION,

            OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED,
            OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            OutboxCodeEnum::INVOICE_DELETED,
            OutboxCodeEnum::NEW_ORDER,
            OutboxCodeEnum::NEW_CUSTOMER
            => PostRoomCodeEnum::USER_NOTIFICATION,

            OutboxCodeEnum::INVITE
            => PostRoomCodeEnum::COLD_EMAIL,

            OutboxCodeEnum::MARKETING,
            OutboxCodeEnum::NEWSLETTER,
            OutboxCodeEnum::ABANDONED_CART,
            OutboxCodeEnum::REORDER_REMINDER,
            OutboxCodeEnum::BASKET_PUSH,
            OutboxCodeEnum::NEW_CUSTOMER_PUSH
            => PostRoomCodeEnum::MARKETING,

            OutboxCodeEnum::TEST,
            => PostRoomCodeEnum::TEST,
        };
    }


}
