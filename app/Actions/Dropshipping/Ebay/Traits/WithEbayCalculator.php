<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 25 Nov 2025 09:24:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Traits;

trait WithEbayCalculator
{
    public const int MAX_FEE_CAP = 250; // £250 per item
    public const float TRANSACTION_FEE = 0.30;
    public const float VAT_RATE = 0.20; // 20%

    // Seller types
    public const string SELLER_PRIVATE = 'private';
    public const string SELLER_BUSINESS = 'business';

    // Seller status
    public const float TOP_RATED_DISCOUNT = 0.10; // 10% discount

    // International fee regions
    public const string REGION_EUROZONE = 'eurozone';
    public const string REGION_OTHER = 'other';

    /**
     * Calculate eBay fees
     */
    public function calculate(array $params): array
    {
        $sellingPrice = $params['selling_price'];
        $itemCost = $params['item_cost'] ?? 0;
        $shippingCost = $params['shipping_cost'] ?? 0;
        $sellerType = $params['seller_type'] ?? self::SELLER_PRIVATE;
        $category = $params['category'] ?? 'other';
        $subcategory = $params['subcategory'] ?? null;
        $isTopRated = $params['is_top_rated'] ?? false;
        $isVatRegistered = $params['is_vat_registered'] ?? false;
        $promotedListingRate = $params['promoted_listing_rate'] ?? 0;
        $charityDonation = $params['charity_donation'] ?? 0;
        $internationalRegion = $params['international_region'] ?? null;
        $promoType = $params['promo_type'] ?? null;

        $totalSalePrice = $sellingPrice + $shippingCost;

        // Calculate final value fee
        $finalValueFee = $this->calculateFinalValueFee(
            $sellingPrice,
            $sellerType,
            $category,
            $subcategory,
            $promoType
        );

        // Apply top rated seller discount (only on variable fee, not transaction fee)
        if ($isTopRated && $sellerType === self::SELLER_BUSINESS) {
            $variableFee = $finalValueFee - self::TRANSACTION_FEE;
            $variableFee = $variableFee * (1 - self::TOP_RATED_DISCOUNT);
            $finalValueFee = $variableFee + self::TRANSACTION_FEE;
        }

        // Apply fee cap
        $finalValueFee = min($finalValueFee, self::MAX_FEE_CAP);

        // Calculate VAT on fees (business sellers only)
        $feeVat = 0;
        if ($sellerType === self::SELLER_BUSINESS) {
            $feeVat = $finalValueFee * self::VAT_RATE;
        }

        // Calculate international fee
        $internationalFee = 0;
        if ($internationalRegion) {
            $internationalFee = $this->calculateInternationalFee(
                $totalSalePrice,
                $sellerType,
                $internationalRegion
            );
        }

        // Calculate promoted listing fee
        $promotedListingFee = 0;
        if ($promotedListingRate > 0) {
            $promotedListingFee = $sellingPrice * ($promotedListingRate / 100);
        }

        // Calculate charity donation amount
        $charityAmount = 0;
        if ($charityDonation > 0) {
            $charityAmount = $sellingPrice * ($charityDonation / 100);
        }

        // Total eBay fees
        $totalEbayFees = $finalValueFee + $feeVat + $internationalFee + $promotedListingFee;

        // Calculate profit
        $profit = $totalSalePrice - $itemCost - $totalEbayFees - $charityAmount;

        // Calculate VAT on selling price (for VAT registered sellers)
        $sellingPriceVat = 0;
        $itemCostVatReclaim = 0;
        if ($isVatRegistered && $sellerType === self::SELLER_BUSINESS) {
            $sellingPriceVat = $sellingPrice * self::VAT_RATE;
            $itemCostVatReclaim = $itemCost * self::VAT_RATE;
            $profit = $profit - $sellingPriceVat + $itemCostVatReclaim + $feeVat;
        }

        return [
            'selling_price' => round($sellingPrice, 2),
            'shipping_cost' => round($shippingCost, 2),
            'total_sale_price' => round($totalSalePrice, 2),
            'item_cost' => round($itemCost, 2),
            'final_value_fee' => round($finalValueFee, 2),
            'fee_vat' => round($feeVat, 2),
            'international_fee' => round($internationalFee, 2),
            'promoted_listing_fee' => round($promotedListingFee, 2),
            'charity_donation' => round($charityAmount, 2),
            'total_ebay_fees' => round($totalEbayFees, 2),
            'selling_price_vat' => round($sellingPriceVat, 2),
            'item_cost_vat_reclaim' => round($itemCostVatReclaim, 2),
            'profit' => round($profit, 2),
            'profit_margin' => $totalSalePrice > 0 ? round(($profit / $totalSalePrice) * 100, 2) : 0,
        ];
    }

    /**
     * Calculate final value fee based on seller type and category
     */
    private function calculateFinalValueFee(
        float   $price,
        string  $sellerType,
        string  $category,
        ?string $subcategory,
        ?string $promoType
    ): float {
        // Handle promotional types
        if ($promoType) {
            return $this->calculatePromoFee($price, $promoType);
        }

        if ($sellerType === self::SELLER_PRIVATE) {
            return $this->calculatePrivateFee($price, $category, $subcategory);
        }

        return $this->calculateBusinessFee($price, $category, $subcategory);
    }

    /**
     * Calculate private seller fee
     */
    private function calculatePrivateFee(float $price, string $category, ?string $subcategory): float
    {
        $fee = self::TRANSACTION_FEE;

        // Special case: Trainers
        if ($this->isTrainersCategory($category, $subcategory)) {
            if ($price <= 100) {
                $fee += $price * 0.128;
            } else {
                $fee += 100 * 0.128 + ($price - 100) * 0.05;
            }
        } else {
            // Standard rate
            if ($price <= 5000) {
                $fee += $price * 0.128;
            } else {
                $fee += 5000 * 0.128 + ($price - 5000) * 0.03;
            }
        }

        return $fee;
    }

    /**
     * Calculate business seller fee
     */
    private function calculateBusinessFee(float $price, string $category, ?string $subcategory): float
    {
        $fee = self::TRANSACTION_FEE;

        // Special transaction fee for low-value items in certain categories
        if ($price <= 10 && in_array($category, ['art', 'collectables', 'antiques', 'coins', 'dolls_bears', 'pottery', 'sports_memorabilia', 'stamps'])) {
            $fee = 0.10;
        }

        $variableFee = $this->getBusinessCategoryFee($price, $category, $subcategory);

        return $fee + $variableFee;
    }

    /**
     * Get business category fee rates
     */
    private function getBusinessCategoryFee(float $price, string $category, ?string $subcategory): float
    {
        $categoryRates = [
            'art_nfts' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.05]],
            'art' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'cameras_photography_premium' => [
                ['limit' => 1000, 'rate' => 0.069],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.03]
            ],
            'cameras_photography' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'clothing_baby' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'clothing_trainers' => $this->getTrainersBusinessFee($price),
            'clothing' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'computers_premium' => [
                ['limit' => 1000, 'rate' => 0.069],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.03]
            ],
            'computers' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'films_nfts' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.05]],
            'films' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'garden' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'health_hair' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.119]],
            'health_smoking' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.129]],
            'health' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'home_appliances' => [
                ['limit' => 400, 'rate' => 0.069],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.03]
            ],
            'home_power_strips' => [
                ['limit' => 250, 'rate' => 0.099],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.079]
            ],
            'home_bath' => [
                ['limit' => 500, 'rate' => 0.109],
                ['limit' => 1000, 'rate' => 0.079],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.03]
            ],
            'home' => [
                ['limit' => 500, 'rate' => 0.119],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.079]
            ],
            'jewellery_watches' => [
                ['limit' => 750, 'rate' => 0.129],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.02]
            ],
            'jewellery' => [
                ['limit' => 1000, 'rate' => 0.149],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.04]
            ],
            'mobile_phones' => [
                ['limit' => 400, 'rate' => 0.069],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.02]
            ],
            'mobile' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'music_nfts' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.05]],
            'music' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'sound_vision_premium' => [
                ['limit' => 1000, 'rate' => 0.069],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.03]
            ],
            'sound_vision' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'sports_memorabilia_nfts' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.05]],
            'sports_memorabilia' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'toys_nfts' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.05]],
            'toys_tents' => [
                ['limit' => 250, 'rate' => 0.109],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.079]
            ],
            'toys' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'vehicle_parts_gps' => [
                ['limit' => 400, 'rate' => 0.069],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.03]
            ],
            'vehicle_parts_tyres' => [
                ['limit' => 250, 'rate' => 0.079],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.03]
            ],
            'vehicle_parts' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.089]],
            'video_games_consoles' => [
                ['limit' => 400, 'rate' => 0.069],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.02]
            ],
            'video_games' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'holidays' => [
                ['limit' => 650, 'rate' => 0.079],
                ['limit' => PHP_FLOAT_MAX, 'rate' => 0.02]
            ],
            'collectables' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'antiques' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'musical_instruments' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'sporting_goods' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.109]],
            'books' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.099]],
            'business' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.119]],
            'crafts' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.129]],
            'event_tickets' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.129]],
            'pet_supplies' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.129]],
            'wholesale' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.129]],
            'other' => [['limit' => PHP_FLOAT_MAX, 'rate' => 0.129]],
        ];

        $rates = $categoryRates[$category] ?? $categoryRates['other'];

        // Handle array return from trainers
        if (is_float($rates)) {
            return $rates;
        }

        return $this->calculateTieredFee($price, $rates);
    }

    /**
     * Calculate tiered fee structure
     */
    private function calculateTieredFee(float $price, array $tiers): float
    {
        $fee = 0;
        $remaining = $price;
        $previousLimit = 0;

        foreach ($tiers as $tier) {
            $limit = $tier['limit'];
            $rate = $tier['rate'];

            if ($remaining <= 0) {
                break;
            }

            if ($limit === PHP_FLOAT_MAX) {
                $fee += $remaining * $rate;
                break;
            }

            $tierAmount = min($remaining, $limit - $previousLimit);
            $fee += $tierAmount * $rate;
            $remaining -= $tierAmount;
            $previousLimit = $limit;
        }

        return $fee;
    }

    /**
     * Get trainers business fee (special handling)
     */
    private function getTrainersBusinessFee(float $price): float
    {
        if ($price < 100) {
            return $price * 0.119;
        } else {
            return 100 * 0.119 + ($price - 100) * 0.05;
        }
    }

    /**
     * Check if category is trainers
     */
    private function isTrainersCategory(string $category, ?string $subcategory): bool
    {
        return in_array($category, ['clothing_trainers', 'trainers']);
    }

    /**
     * Calculate promotional fee
     */
    private function calculatePromoFee(float $price, string $promoType): float
    {
        switch ($promoType) {
            case '80_percent_off':
                $variableFee = ($price * 0.128) * 0.20; // 80% off, pay 20%
                return self::TRANSACTION_FEE + $variableFee;

            case '50_percent_off':
                $variableFee = ($price * 0.128) * 0.50; // 50% off
                return self::TRANSACTION_FEE + $variableFee;

            case 'max_3':
                return min($price * 0.128, 3); // No transaction fee for this promo

            case '2_percent':
                if ($price >= 30) {
                    return self::TRANSACTION_FEE + ($price * 0.02);
                }
                return self::TRANSACTION_FEE + ($price * 0.128);

            case '15_percent_flat':
                return 0.05 + ($price * 0.15);

            case '5_percent_flat':
                if ($price <= 250) {
                    return 0.30 + ($price * 0.05);
                } else {
                    return 0.30 + (250 * 0.05) + (($price - 250) * 0.02);
                }

                // no break
            default:
                return self::TRANSACTION_FEE + ($price * 0.128);
        }
    }

    /**
     * Calculate international fee
     */
    private function calculateInternationalFee(
        float  $totalSalePrice,
        string $sellerType,
        string $region
    ): float {
        $rates = [
            self::SELLER_PRIVATE => [
                self::REGION_EUROZONE => 0.03,
                self::REGION_OTHER => 0.03,
            ],
            self::SELLER_BUSINESS => [
                self::REGION_EUROZONE => 0.005,
                self::REGION_OTHER => 0.020,
            ],
        ];

        $rate = $rates[$sellerType][$region] ?? 0;
        return $totalSalePrice * $rate;
    }
}
