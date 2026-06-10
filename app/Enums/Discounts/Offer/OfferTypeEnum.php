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
    case VOUCHER_AND_AMOUNT = "Voucher AND Amount"; // Old one from aurora to delete
    case PRODUCT_QUANTITY_ORDERED = "Product Quantity Ordered";
    case CATEGORY_FOR_EVERY_QUANTITY_ORDERED = "Category For Every Quantity Ordered";
    case DISCRETIONARY = "Discretionary";
    case AMOUNT_AND_ORDER_INTERVAL = "Amount AND Order Interval";
    case AMOUNT = "Amount";
    case CATEGORY_FOR_EVERY_QUANTITY_ANY_PRODUCT_ORDERED = "Category For Every Quantity Any Product Ordered";


    case VOL_GR_GIFT = "VolGr Gift";

    case VOUCHER = "Voucher"; // Old one from aurora to delete

    case VOUCHER_ANY_ORDER = "Voucher Any Order";
    case VOUCHER_AMOUNT_ORDERED = "Voucher Amount Ordered";

    case GR_AMNESTY = "GR Amnesty";
    case PRODUCT_FOR_EVERY_QUANTITY_ORDERED = "Product For Every Quantity Ordered";
    case EVERY_ORDER = "Every Order";
    case VOUCHER_AND_ORDER_NUMBER = "Voucher AND Order Number"; // Old one from aurora to delete
    case PRODUCT_AMOUNT_ORDERED = "Product Amount Ordered";

    case SHOP_ORDERED = "Shop Ordered";
    case DEPARTMENT_ORDERED = "Department Ordered";
    case SUB_DEPARTMENT_ORDERED = "Subdepartment Ordered";
    case CATEGORY_ORDERED = "Category Ordered";

    case SHOP_QUANTITY_ORDERED = "Shop Quantity Ordered";
    case DEPARTMENT_QUANTITY_ORDERED = "Department Quantity Ordered";
    case SUB_DEPARTMENT_QUANTITY_ORDERED = "Subdepartment Quantity Ordered";
    case CATEGORY_QUANTITY_ORDERED = "Category Quantity Ordered";

    case SHOP_AMOUNT_ORDERED = "Shop Amount Ordered";
    case DEPARTMENT_AMOUNT_ORDERED = "Department Amount Ordered";
    case SUB_DEPARTMENT_AMOUNT_ORDERED = "Subdepartment Amount Ordered";
    case CATEGORY_AMOUNT_ORDERED = "Category Amount Ordered";

    case PRODUCT_IN_CATEGORY_CARTON = "Product In Category Carton";

    case AMOUNT_AND_ORDER_NUMBER = "Amount AND Order Number";
    case ORDER_NUMBER = "Order Number";
    case CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL = "Category Quantity Ordered Order Interval";
    case GIFT = "Gift";

    public function label(): string
    {
        return $this->value;
    }

    public function icons(): array
    {
        return $this->icon();
    }

    public function icon(): array
    {
        return match ($this) {
            self::FAMILY_FOR_EVERY_QUANTITY_ORDERED,
            self::FAMILY_QUANTITY_ORDERED,
            self::CATEGORY_FOR_EVERY_QUANTITY_ORDERED,
            self::CATEGORY_QUANTITY_ORDERED,
            self::DEPARTMENT_QUANTITY_ORDERED,
            self::SUB_DEPARTMENT_QUANTITY_ORDERED,
            self::SHOP_QUANTITY_ORDERED
            => $this->quantityIcon(),

            self::PRODUCT_QUANTITY_ORDERED,
            self::PRODUCT_FOR_EVERY_QUANTITY_ORDERED,
            self::PRODUCT_AMOUNT_ORDERED,
            => $this->productIcon(),

            self::ORDER_INTERVAL,
            self::AMOUNT_AND_ORDER_INTERVAL,
            self::CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL
            => $this->intervalIcon(),

            self::VOUCHER,
            self::VOUCHER_AND_AMOUNT,
            self::VOUCHER_AND_ORDER_NUMBER,

            self::VOUCHER_ANY_ORDER,
            self::VOUCHER_AMOUNT_ORDERED,

            => $this->voucherIcon(),

            self::AMOUNT,
            self::ORDER_TOTAL_NET_AMOUNT_AND_ORDER_NUMBER,
            self::DEPARTMENT_AMOUNT_ORDERED,
            self::SUB_DEPARTMENT_AMOUNT_ORDERED,
            self::CATEGORY_AMOUNT_ORDERED,
            self::SHOP_AMOUNT_ORDERED


            => $this->amountIcon(),

            self::AMOUNT_AND_ORDER_NUMBER,
            self::ORDER_NUMBER,
            self::EVERY_ORDER
            => $this->orderIcon(),

            self::CATEGORY_ORDERED,
            self::SHOP_ORDERED,
            self::DEPARTMENT_ORDERED,
            self::SUB_DEPARTMENT_ORDERED,
            self::PRODUCT_IN_CATEGORY_CARTON,
            self::CATEGORY_FOR_EVERY_QUANTITY_ANY_PRODUCT_ORDERED
            => $this->catalogueIcon(),

            self::GIFT,
            self::VOL_GR_GIFT
            => $this->giftIcon(),

            self::GR_AMNESTY
            => $this->amnestyIcon(),

            self::DISCRETIONARY
            => $this->defaultIcon(),
        };
    }

    private function quantityIcon(): array
    {
        return $this->iconData('fal fa-abacus');
    }

    private function productIcon(): array
    {
        return $this->iconData('fal fa-clipboard-list');
    }

    private function intervalIcon(): array
    {
        return $this->iconData('fal fa-stopwatch');
    }

    private function voucherIcon(): array
    {
        return $this->iconData('fal fa-ticket');
    }

    private function amountIcon(): array
    {
        return $this->iconData('fal fa-money-bill-wave');
    }

    private function orderIcon(): array
    {
        return $this->iconData('fal fa-shopping-cart');
    }

    private function catalogueIcon(): array
    {
        return $this->iconData('fal fa-books');
    }

    private function giftIcon(): array
    {
        return $this->iconData('fal fa-gift');
    }

    private function amnestyIcon(): array
    {
        return $this->iconData('fal fa-candle-holder');
    }

    private function defaultIcon(): array
    {
        return $this->iconData('fal fa-badge-percent');
    }

    private function iconData(string $icon, string $class = ''): array
    {
        return [
            'icon'    => $icon,
            'tooltip' => $this->label(),
            'class'   => $class,
        ];
    }
}
