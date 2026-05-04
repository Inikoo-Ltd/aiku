<?php

namespace App\Enums\Discounts\Offer;

use App\Enums\EnumHelperTrait;

enum OfferTypeEnum: string
{
    use EnumHelperTrait;

    case FAMILY_FOR_EVERY_QUANTITY_ORDERED = "Family For Every Quantity Ordered";
    case ORDER_INTERVAL = "Order Interval";
    case ORDER_TOTAL_NET_AMOUNT_AND_ORDER_NUMBER = "Order Total Net Amount AND Order Number";
    case FAMILY_QUANTITY_ORDERED = "Family Quantity Ordered";
    case VOUCHER_AND_AMOUNT = "Voucher AND Amount";
    case PRODUCT_QUANTITY_ORDERED = "Product Quantity Ordered";
    case CATEGORY_FOR_EVERY_QUANTITY_ORDERED = "Category For Every Quantity Ordered";
    case DISCRETIONARY = "Discretionary";
    case AMOUNT_AND_ORDER_INTERVAL = "Amount AND Order Interval";
    case AMOUNT = "Amount";
    case CATEGORY_FOR_EVERY_QUANTITY_ANY_PRODUCT_ORDERED = "Category For Every Quantity Any Product Ordered";
    case DEPARTMENT_QUANTITY_ORDERED = "Department Quantity Ordered";
    case CATEGORY_QUANTITY_ORDERED = "Category Quantity Ordered";
    case VOLGR_GIFT = "VolGr Gift";
    case VOUCHER = "Voucher";
    case GR_AMNESTY = "GR Amnesty";
    case PRODUCT_FOR_EVERY_QUANTITY_ORDERED = "Product For Every Quantity Ordered";
    case EVERY_ORDER = "Every Order";
    case VOUCHER_AND_ORDER_NUMBER = "Voucher AND Order Number";
    case PRODUCT_AMOUNT_ORDERED = "Product Amount Ordered";
    case CATEGORY_ORDERED = "Category Ordered";
    case PRODUCT_IN_CATEGORY_CARTON = "Product In Category Carton";
    case CATEGORY_AMOUNT_ORDERED = "Category Amount Ordered";
    case AMOUNT_AND_ORDER_NUMBER = "Amount AND Order Number";
    case ORDER_NUMBER = "Order Number";
    case CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL = "Category Quantity Ordered Order Interval";

    public function label(): string
    {
        return match($this) {
            self::FAMILY_FOR_EVERY_QUANTITY_ORDERED                 => "Family For Every Quantity Ordered",
            self::ORDER_INTERVAL                                    => "Order Interval",
            self::ORDER_TOTAL_NET_AMOUNT_AND_ORDER_NUMBER           => "Order Total Net Amount AND Order Number",
            self::FAMILY_QUANTITY_ORDERED                           => "Family Quantity Ordered",
            self::VOUCHER_AND_AMOUNT                                => "Voucher AND Amount",
            self::PRODUCT_QUANTITY_ORDERED                          => "Product Quantity Ordered",
            self::CATEGORY_FOR_EVERY_QUANTITY_ORDERED               => "Category For Every Quantity Ordered",
            self::DISCRETIONARY                                     => "Discretionary",
            self::AMOUNT_AND_ORDER_INTERVAL                         => "Amount AND Order Interval",
            self::AMOUNT                                            => "Amount",
            self::CATEGORY_FOR_EVERY_QUANTITY_ANY_PRODUCT_ORDERED   => "Category For Every Quantity Any Product Ordered",
            self::DEPARTMENT_QUANTITY_ORDERED                       => "Department Quantity Ordered",
            self::CATEGORY_QUANTITY_ORDERED                         => "Category Quantity Ordered",
            self::VOLGR_GIFT                                        => "VolGr Gift",
            self::VOUCHER                                           => "Voucher",
            self::GR_AMNESTY                                        => "GR Amnesty",
            self::PRODUCT_FOR_EVERY_QUANTITY_ORDERED                => "Product For Every Quantity Ordered",
            self::EVERY_ORDER                                       => "Every Order",
            self::VOUCHER_AND_ORDER_NUMBER                          => "Voucher AND Order Number",
            self::PRODUCT_AMOUNT_ORDERED                            => "Product Amount Ordered",
            self::CATEGORY_ORDERED                                  => "Category Ordered",
            self::PRODUCT_IN_CATEGORY_CARTON                        => "Product In Category Carton",
            self::CATEGORY_AMOUNT_ORDERED                           => "Category Amount Ordered",
            self::AMOUNT_AND_ORDER_NUMBER                           => "Amount AND Order Number",
            self::ORDER_NUMBER                                      => "Order Number",
            self::CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL          => "Category Quantity Ordered Order Interval",
        };
    }

    public function icons(): array
    {
        return match($this) {
            self::FAMILY_FOR_EVERY_QUANTITY_ORDERED                 => [
                'icon'      => 'fal fa-abacus',
                'tooltip'   => 'Family For Every Quantity Ordered',
                'class'     => '',
            ],
            self::ORDER_INTERVAL                                    => [
                'icon'      => 'fal fa-stopwatch',
                'tooltip'   => 'Order Interval',
                'class'     => '',
            ],
            self::ORDER_TOTAL_NET_AMOUNT_AND_ORDER_NUMBER           => [
                'icon'      => 'fal fa-calculator',
                'tooltip'   => 'Order Total Net Amount AND Order Number',
                'class'     => '',
            ],
            self::FAMILY_QUANTITY_ORDERED                           => [
                'icon'      => 'fad fa-abacus',
                'tooltip'   => 'Family Quantity Ordered',
                'class'     => '',
            ],
            self::VOUCHER_AND_AMOUNT                                => [
                'icon'      => 'fad fa-ticket',
                'tooltip'   => 'Voucher AND Amount',
                'class'     => '',
            ],
            self::PRODUCT_QUANTITY_ORDERED                          => [
                'icon'      => 'fal fa-clipboard-list',
                'tooltip'   => 'Product Quantity Ordered',
                'class'     => '',
            ],
            self::CATEGORY_FOR_EVERY_QUANTITY_ORDERED               => [
                'icon'      => 'fal fa-abacus',
                'tooltip'   => 'Category For Every Quantity Ordered',
                'class'     => '',
            ],
            self::DISCRETIONARY                                     => [
                'icon'      => 'fal fa',
                'tooltip'   => 'Discretionary',
                'class'     => '',
            ],
            self::AMOUNT_AND_ORDER_INTERVAL                         => [
                'icon'      => 'fal fa-hourglass',
                'tooltip'   => 'Amount AND Order Interval',
                'class'     => '',
            ],
            self::AMOUNT                                            => [
                'icon'      => 'fal fa-sigma',
                'tooltip'   => 'Amount',
                'class'     => '',
            ],
            self::CATEGORY_FOR_EVERY_QUANTITY_ANY_PRODUCT_ORDERED   => [
                'icon'      => 'fal fa',
                'tooltip'   => 'Category For Every Quantity Any Product Ordered',
                'class'     => '',
            ],
            self::DEPARTMENT_QUANTITY_ORDERED                       => [
                'icon'      => 'fad fa-abacus',
                'tooltip'   => 'Department Quantity Ordered',
                'class'     => '',
            ],
            self::CATEGORY_QUANTITY_ORDERED                         => [
                'icon'      => 'fad fa-abacus',
                'tooltip'   => 'Category Quantity Ordered',
                'class'     => '',
            ],
            self::VOLGR_GIFT                                        => [
                'icon'      => 'fal fa',
                'tooltip'   => 'VolGr Gift',
                'class'     => '',
            ],
            self::VOUCHER                                           => [
                'icon'      => 'fal fa',
                'tooltip'   => 'Voucher',
                'class'     => '',
            ],
            self::GR_AMNESTY                                        => [
                'icon'      => 'fal fa',
                'tooltip'   => 'GR Amnesty',
                'class'     => '',
            ],
            self::PRODUCT_FOR_EVERY_QUANTITY_ORDERED                => [
                'icon'      => 'fal fa-clipboard-list',
                'tooltip'   => 'Product For Every Quantity Ordered',
                'class'     => '',
            ],
            self::EVERY_ORDER                                       => [
                'icon'      => 'fal fa-basket',
                'tooltip'   => 'Every Order',
                'class'     => '',
            ],
            self::VOUCHER_AND_ORDER_NUMBER                          => [
                'icon'      => 'fal fa-ticket',
                'tooltip'   => 'Voucher AND Order Number',
                'class'     => '',
            ],
            self::PRODUCT_AMOUNT_ORDERED                            => [
                'icon'      => 'fal fa-clipboard-list',
                'tooltip'   => 'Product Amount Ordered',
                'class'     => '',
            ],
            self::CATEGORY_ORDERED                                  => [
                'icon'      => 'fal fa',
                'tooltip'   => 'Category Ordered',
                'class'     => '',
            ],
            self::PRODUCT_IN_CATEGORY_CARTON                        => [
                'icon'      => 'fal fa-box',
                'tooltip'   => 'Product In Category Carton',
                'class'     => '',
            ],
            self::CATEGORY_AMOUNT_ORDERED                           => [
                'icon'      => 'fal fa',
                'tooltip'   => 'Category Amount Ordered',
                'class'     => '',
            ],
            self::AMOUNT_AND_ORDER_NUMBER                           => [
                'icon'      => 'fad fa-shopping-cart',
                'tooltip'   => 'Amount AND Order Number',
                'class'     => '',
            ],
            self::ORDER_NUMBER                                      => [
                'icon'      => 'fal fa-shopping-cart',
                'tooltip'   => 'Order Number',
                'class'     => '',
            ],
            self::CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL          => [
                'icon'      => 'fal fa-stopwatch-20',
                'tooltip'   => 'Category Quantity Ordered Order Interval',
                'class'     => '',
            ],
        };
    }
}
