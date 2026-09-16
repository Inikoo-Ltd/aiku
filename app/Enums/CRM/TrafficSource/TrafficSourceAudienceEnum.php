<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\TrafficSource;

use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\EnumHelperTrait;

/**
 * Who an ad reached, judged by what the person was at the moment of the click.
 *
 * The distinction the spend reports cannot make on their own. A thousand clicks that all came from
 * customers already buying from us is a thousand clicks of reaching people who were coming anyway,
 * and it reads identically to a thousand clicks of genuine acquisition in every figure we publish
 * today.
 *
 * Built on CustomerStateEnum rather than a parallel vocabulary, so a customer counted as active here
 * is active in exactly the sense the rest of Aiku means it.
 */
enum TrafficSourceAudienceEnum: string
{
    use EnumHelperTrait;

    case ACTIVE_CUSTOMER  = 'active_customer';
    case LAPSED_CUSTOMER  = 'lapsed_customer';
    case REGISTERED       = 'registered';
    case NEW_CUSTOMER     = 'new_customer';
    case GUEST_REGISTERED = 'guest_registered';
    case ANONYMOUS        = 'anonymous';

    public static function labels(): array
    {
        return [
            self::ACTIVE_CUSTOMER->value  => __('Active customer'),
            self::LAPSED_CUSTOMER->value  => __('Customer won back'),
            self::REGISTERED->value       => __('Registered, never bought'),
            self::NEW_CUSTOMER->value     => __('New customer'),
            self::GUEST_REGISTERED->value => __('Guest who registered'),
            self::ANONYMOUS->value        => __('Never identified'),
        ];
    }

    /**
     * What the figure means for the money, which is the only reason anyone opens this.
     */
    public static function descriptions(): array
    {
        return [
            self::ACTIVE_CUSTOMER->value  => __('Already buying from you when they clicked. You paid to reach someone who was coming anyway.'),
            self::LAPSED_CUSTOMER->value  => __('Had stopped buying when they clicked. This is the ad doing work nothing else was doing.'),
            self::REGISTERED->value       => __('Had an account but had never ordered when they clicked.'),
            self::NEW_CUSTOMER->value     => __('Nobody we knew when they clicked, and a buying customer afterwards. This is acquisition.'),
            self::GUEST_REGISTERED->value => __('Nobody we knew when they clicked, registered afterwards, has not ordered yet.'),
            self::ANONYMOUS->value        => __('Never signed in, so we cannot say who they were.'),
        ];
    }

    /**
     * Which bucket a person who was already signed in when they clicked belongs to.
     *
     * The order test is not decoration. RegisterCustomer sets the state to active the moment somebody
     * signs up, so on this data two thirds of active customers have never bought anything, and reading
     * the state alone would report the majority of an audience as established buyers on the strength
     * of them having filled in a form once.
     */
    public static function whenKnown(?string $state, bool $hadOrdered): self
    {
        if (!$hadOrdered) {
            return self::REGISTERED;
        }

        return match ($state) {
            CustomerStateEnum::LOSING->value, CustomerStateEnum::LOST->value => self::LAPSED_CUSTOMER,
            default                                                         => self::ACTIVE_CUSTOMER,
        };
    }

    /**
     * Which bucket a person nobody recognised at click time belongs to, judged by what they became.
     */
    public static function whenAcquired(bool $hasOrdered): self
    {
        return $hasOrdered ? self::NEW_CUSTOMER : self::GUEST_REGISTERED;
    }

    /** Spend on these reached somebody who was not already a customer of ours. */
    public function isAcquisition(): bool
    {
        return in_array($this, [self::NEW_CUSTOMER, self::GUEST_REGISTERED], true);
    }

    public function colour(): string
    {
        return match ($this) {
            self::ACTIVE_CUSTOMER  => '#6b7280',
            self::LAPSED_CUSTOMER  => '#006300',
            self::REGISTERED       => '#9ca3af',
            self::NEW_CUSTOMER     => '#166534',
            self::GUEST_REGISTERED => '#a15c00',
            self::ANONYMOUS        => '#d1d5db',
        };
    }
}
