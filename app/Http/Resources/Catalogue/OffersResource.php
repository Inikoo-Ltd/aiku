<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 15:23:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;

/**
 * @property int $shop_id
 * @property int $offer_campaign_id
 * @property string $slug
 * @property string $code
 * @property string $data
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $shop_slug
 * @property mixed $offer_campaign_slug
 * @property mixed $state
 * @property mixed $type
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * @property mixed $orders
 * @property mixed $invoices
 * @property mixed $sales_grp_currency_external
 * @property mixed $duration
 * @property mixed $start_at
 * @property mixed $end_at
 * @property mixed $trigger_data
 * @property string|null $allowance_type
 * @property string|null $allowance_class
 * @property string|null $allowance_target_type
 * @property float|null $allowance_percentage_off
 * @property string|null $allowance_category_name
 */
class OffersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                          => $this->id,
            'created_at'                  => $this->created_at,
            'shop_slug'                   => $this->shop_slug,
            'offer_campaign_slug'         => $this->offer_campaign_slug,
            'slug'                        => $this->slug,
            'state'                       => OfferStateEnum::stateIcon()[$this->state->value],
            'state_value'                 => $this->state,
            'code'                        => $this->code,
            'name'                        => $this->name,
            'label'                       => $this->buildOfferLabel(),
            'type_icon'                   => OfferTypeEnum::from($this->type)?->icons(),
            'type'                        => $this->type,
            'organisation_name'           => $this->organisation_name,
            'organisation_slug'           => $this->organisation_slug,
            'shop_name'                   => $this->shop_name,
            'orders'                      => $this->orders,
            'invoices'                    => $this->invoices,
            'sales_grp_currency_external' => $this->sales_grp_currency_external,
            'duration'                    => $this->duration,
            'start_at'                    => $this->start_at,
            'end_at'                      => $this->end_at,
            'is_active'                   => $this->state == OfferStateEnum::ACTIVE,
        ];
    }

    private function buildOfferLabel(): string
    {
        if ($this->allowance_type === 'percentage_off') {
            $triggerData = is_array($this->trigger_data)
                ? $this->trigger_data
                : json_decode($this->trigger_data ?? '{}', true);

            $interval   = isset($triggerData['interval']) ? (int) $triggerData['interval'] : null;
            $percentage = $this->allowance_percentage_off
                ? round((float) $this->allowance_percentage_off * 100).'%'
                : null;
            $orderNum   = $triggerData['order_number'] ?? null;
            $minAmount  = isset($triggerData['min_amount']) ? (float) $triggerData['min_amount'] : null;
            $itemAmount = isset($triggerData['item_amount']) ? (float) $triggerData['item_amount'] : null;
            $itemQty    = isset($triggerData['item_quantity']) ? (int) $triggerData['item_quantity'] : null;

            $hasUsefulInfo = $percentage || $this->allowance_category_name
                || in_array($this->allowance_target_type, ['all_products_in_order', 'order'])
                || $orderNum || ($minAmount > 0)
                || ($interval && $itemQty > 1) || (!$interval && $itemQty > 1) || (!$interval && $itemAmount > 1);

            if (!$hasUsefulInfo) {
                return $this->typeAndNameFallback();
            }

            $label = $percentage
                ? ($interval ? "Vol Disc {$percentage}" : "Disc {$percentage}")
                : ($interval ? 'Vol Disc' : 'Disc');

            if ($this->allowance_category_name) {
                $label .= " {$this->allowance_category_name}";
            } elseif (in_array($this->allowance_target_type, ['all_products_in_order', 'order'])) {
                $label .= ' Order';
            }

            if ($orderNum) {
                $label .= $orderNum == 1 ? ' 1st order' : " order #{$orderNum}";
            }
            if ($minAmount > 0) {
                $label .= " min {$minAmount}";
            }
            if ($interval && $itemQty > 1) {
                $label .= " {$itemQty}x/{$interval}d";
            } elseif (!$interval && $itemQty > 1) {
                $label .= " min qty {$itemQty}";
            } elseif (!$interval && $itemAmount > 1) {
                $label .= " min {$itemAmount}";
            }

            return $label;
        }

        if ($this->allowance_type === 'gift') {
            return $this->allowance_class === 'amnesty' ? 'GR Amnesty' : 'Gift';
        }

        if ($this->allowance_type === 'mixed') {
            return 'Mixed';
        }

        return $this->typeAndNameFallback();
    }

    private function typeAndNameFallback(): string
    {
        $typePrefix = $this->offerTypePrefix();
        $name       = $this->name ?: '';

        return $typePrefix && $name ? "[{$typePrefix}] {$name}" : ($typePrefix ?: $name ?: $this->type);
    }

    private function offerTypePrefix(): ?string
    {
        return match ($this->type) {
            'Category Ordered'                              => 'Disc',
            'Category Quantity Ordered'                     => 'Qty Disc',
            'Category Quantity Ordered Order Interval'      => 'Vol Disc',
            'Category Amount Ordered'                       => 'Amt Disc',
            'Category For Every Quantity Ordered'           => 'BOGOF',
            'Category For Every Quantity Any Product Ordered' => 'BOGOF',
            'Department Ordered'                            => 'Disc',
            'Department Quantity Ordered'                   => 'Qty Disc',
            'Department Amount Ordered'                     => 'Amt Disc',
            'Subdepartment Ordered'                         => 'Disc',
            'Subdepartment Quantity Ordered'                => 'Qty Disc',
            'Subdepartment Amount Ordered'                  => 'Amt Disc',
            'Shop Ordered'                                  => 'Shop Disc',
            'Shop Quantity Ordered'                         => 'Shop Qty Disc',
            'Shop Amount Ordered'                           => 'Shop Amt Disc',
            'Family Quantity Ordered'                       => 'Qty Disc',
            'Family For Every Quantity Ordered'             => 'BOGOF',
            'Product Quantity Ordered'                      => 'Qty Disc',
            'Product Amount Ordered'                        => 'Amt Disc',
            'Product For Every Quantity Ordered'            => 'BOGOF',
            'Product In Category Carton'                    => 'Carton Disc',
            'Customer Any Order'                            => 'Customer Disc',
            'Customer Amount Ordered'                       => 'Customer Amt Disc',
            'Amount'                                        => 'Order Disc',
            'Amount AND Order Number'                       => '1st Order Disc',
            'Amount AND Order Interval'                     => 'Order Interval Disc',
            'Order Interval'                                => 'Order Interval Disc',
            'Order Number'                                  => 'Order # Disc',
            'Order Total Net Amount AND Order Number'       => 'Order Total Disc',
            'Every Order'                                   => 'Every Order Disc',
            'Voucher'                                       => 'Voucher',
            'Voucher Any Order'                             => 'Voucher',
            'Voucher Amount Ordered'                        => 'Voucher Amt',
            'Voucher AND Amount'                            => 'Voucher Disc',
            'Voucher AND Order Number'                      => 'Voucher # Disc',
            'Gift'                                          => 'Gift',
            'Discretionary'                                 => 'Discretionary',
            'GR Amnesty'                                    => 'GR Amnesty',
            'VolGr Gift'                                    => 'Vol Gift',
            default                                         => null,
        };
    }
}
