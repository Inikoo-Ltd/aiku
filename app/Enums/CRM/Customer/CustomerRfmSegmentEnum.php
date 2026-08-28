<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\Customer;

use App\Enums\EnumHelperTrait;

enum CustomerRfmSegmentEnum: string
{
    use EnumHelperTrait;

    case NEW_CUSTOMER       = 'new-customer';
    case ACTIVE             = 'active';
    case AT_RISK            = 'at-risk';
    case INACTIVE           = 'inactive';
    case LOST_CUSTOMER      = 'lost-customer';

    case ONE_TIME_BUYER     = 'one-time-buyer';
    case OCCASIONAL_SHOPPER = 'occasional-shopper';
    case FREQUENT_BUYER     = 'frequent-buyer';
    case BRAND_ADVOCATE     = 'brand-advocate';

    case LOW_VALUE          = 'low-value';
    case MEDIUM_VALUE       = 'medium-value';
    case HIGH_VALUE         = 'high-value';
    case GOLD_REWARD        = 'gold-reward';
    case TOP_100            = 'top-100';
    case TOP_10             = 'top-10';

    public const TYPE_RECENCY   = 'recency';
    public const TYPE_FREQUENCY = 'frequency';
    public const TYPE_MONETARY  = 'monetary';

    public const RECENT_DAYS   = 30;
    public const AT_RISK_DAYS  = 90;
    public const INACTIVE_DAYS = 180;

    public const OCCASIONAL_SHOPPER_MAX_INVOICES = 4;
    public const FREQUENT_BUYER_MAX_INVOICES     = 9;

    public const TOP_SPENDERS_SIZE = 10;

    public static function types(): array
    {
        return [self::TYPE_RECENCY, self::TYPE_FREQUENCY, self::TYPE_MONETARY];
    }

    public function type(): string
    {
        return match ($this) {
            self::NEW_CUSTOMER, self::ACTIVE, self::AT_RISK, self::INACTIVE, self::LOST_CUSTOMER => self::TYPE_RECENCY,
            self::ONE_TIME_BUYER, self::OCCASIONAL_SHOPPER, self::FREQUENT_BUYER, self::BRAND_ADVOCATE => self::TYPE_FREQUENCY,
            default => self::TYPE_MONETARY,
        };
    }

    public function tagName(): string
    {
        return self::tagNames()[$this->value];
    }

    public static function tagNames(): array
    {
        return [
            'new-customer'       => 'New Customer',
            'active'             => 'Active',
            'at-risk'            => 'At Risk',
            'inactive'           => 'Inactive',
            'lost-customer'      => 'Lost Customer',
            'one-time-buyer'     => 'One-Time Buyer',
            'occasional-shopper' => 'Occasional Shopper',
            'frequent-buyer'     => 'Frequent Buyer',
            'brand-advocate'     => 'Brand Advocate',
            'low-value'          => 'Low Value',
            'medium-value'       => 'Medium Value',
            'high-value'         => 'High Value',
            'gold-reward'        => 'Gold Reward',
            'top-100'            => 'Top 100',
            'top-10'             => 'Top 10',
        ];
    }

    public static function tooltips(): array
    {
        return [
            'new-customer'       => __('First purchase within the last 30 days of the selected period.'),
            'active'             => __('Last purchase within the last 30 days. Highly engaged customer.'),
            'at-risk'            => __('Last purchase was 31-90 days ago. May need re-engagement.'),
            'inactive'           => __('Last purchase was 91-180 days ago. High churn risk.'),
            'lost-customer'      => __('Last purchase was more than 180 days ago. Considered churned.'),
            'one-time-buyer'     => __('Placed exactly 1 order in the selected period.'),
            'occasional-shopper' => __('Placed 2-4 orders in the selected period.'),
            'frequent-buyer'     => __('Placed 5-9 orders in the selected period.'),
            'brand-advocate'     => __('Placed 10 or more orders in the selected period. Highly loyal customer.'),
            'low-value'          => __('Total spend is in the bottom 50% of all customers in this shop.'),
            'medium-value'       => __('Total spend is between the 50th and 80th percentile of all customers.'),
            'high-value'         => __('Total spend is between the 80th and 95th percentile of all customers.'),
            'gold-reward'        => __('Total spend is between the 95th and 99th percentile of all customers.'),
            'top-100'            => __('Total spend is above the 99th percentile, outside the ten highest spenders.'),
            'top-10'             => __('The ten highest spenders of this shop in the selected period.'),
        ];
    }

    public static function typeTitles(): array
    {
        return [
            self::TYPE_RECENCY   => __('Recency Segments'),
            self::TYPE_FREQUENCY => __('Frequency Segments'),
            self::TYPE_MONETARY  => __('Monetary Segments'),
        ];
    }

    public static function typeDescriptions(): array
    {
        return [
            self::TYPE_RECENCY   => __('Based on when the last transaction was made, measured at the end of the selected period'),
            self::TYPE_FREQUENCY => __('Based on the number of invoices in the selected period'),
            self::TYPE_MONETARY  => __('Based on total spend in the selected period, ranked against all customers in this shop'),
        ];
    }

    public static function ofType(string $type): array
    {
        return array_values(array_filter(self::cases(), fn (self $segment) => $segment->type() === $type));
    }

    public static function tagNamesOfType(string $type): array
    {
        return array_map(fn (self $segment) => $segment->tagName(), self::ofType($type));
    }
}
