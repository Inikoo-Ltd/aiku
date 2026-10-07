<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferHasCustomer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreCustomerListVoucher extends OrgAction
{
    public const string CODE_TYPE_SHARED = 'shared';
    public const string CODE_TYPE_UNIQUE = 'unique';

    private const string UNIQUE_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const int UNIQUE_CODE_LENGTH      = 6;
    private const int INSERT_CHUNK_SIZE       = 1000;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Offer
    {
        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::VOUCHERS)->first();
        if (!$offerCampaign) {
            abort(404);
        }

        $segments    = Arr::only($modelData, ['states', 'ordered_once', 'ordered_once_from_months', 'ordered_once_to_months']);
        $customerIds = GetVoucherCustomerListQuery::run($shop, $segments)->pluck('customers.id');

        if ($customerIds->isEmpty()) {
            throw ValidationException::withMessages([
                'states' => __('No customers match the selected groups.'),
            ]);
        }

        $hasUniqueCodes = Arr::get($modelData, 'code_type') === self::CODE_TYPE_UNIQUE;
        $minimumAmount  = (float) Arr::get($modelData, 'offer_amount', 0);

        $offerData = [
            'code'         => $hasUniqueCodes ? Arr::get($modelData, 'code_prefix') : Arr::get($modelData, 'voucher'),
            'voucher'      => $hasUniqueCodes ? null : Str::lower(Arr::get($modelData, 'voucher')),
            'name'         => Arr::get($modelData, 'name'),
            'type'         => $minimumAmount > 0 ? OfferTypeEnum::VOUCHER_AMOUNT_ORDERED : OfferTypeEnum::VOUCHER_ANY_ORDER,
            'start_at'     => Arr::get($modelData, 'start_at'),
            'end_at'       => Arr::get($modelData, 'end_at'),
            'duration'     => 'interval',
            'trigger_type' => 'Shop',
            'trigger_id'   => $shop->id,
            'trigger_data' => [
                'item_amount' => $minimumAmount,
            ],
            'settings'     => [
                'can_customer_reuse'         => false,
                'show_on_customer_dashboard' => (bool) Arr::get($modelData, 'show_on_customer_dashboard', true),
                'has_customer_list'          => true,
                'unique_customer_codes'      => $hasUniqueCodes,
                'customer_list_segments'     => $segments,
            ],
            'allowance_type' => Arr::get($modelData, 'allowance_type') === 'gifts' ? 'gift' : Arr::get($modelData, 'allowance_type'),
            'allowances'     => $this->getAllowances($shop, $modelData),
        ];

        $offer = StoreOffer::run($offerCampaign, $offerData);

        $this->storeCustomerList($offer, $customerIds, $hasUniqueCodes ? Str::upper(Arr::get($modelData, 'code_prefix')) : null);

        ActivateOffer::run($offer, 30);

        return $offer;
    }

    private function getAllowances(Shop $shop, array $modelData): array
    {
        return match (Arr::get($modelData, 'allowance_type')) {
            'percentage_off' => [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER->value,
                    'target_id'   => $shop->id,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF->value,
                    'data'        => ['percentage_off' => Arr::get($modelData, 'percentage_off') / 100],
                ]
            ],
            'amount_off' => [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER->value,
                    'target_id'   => $shop->id,
                    'type'        => OfferAllowanceType::AMOUNT_OFF->value,
                    'data'        => ['amount_off' => (float) Arr::get($modelData, 'amount_off')],
                ]
            ],
            'discounted_shipping' => [
                [
                    'class'       => OfferAllowanceClass::SHIPPING->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type'        => OfferAllowanceType::SHIPPING->value,
                ]
            ],
            default => collect(Arr::get($modelData, 'gifts', []))
                ->map(fn (array $gift) => [
                    'class'       => OfferAllowanceClass::GIFT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type'        => OfferAllowanceType::GIFT->value,
                    'data'        => [
                        'product_id' => (int) $gift['product_id'],
                        'quantity'   => (int) $gift['quantity'],
                    ],
                ])
                ->all(),
        };
    }

    private function storeCustomerList(Offer $offer, Collection $customerIds, ?string $codePrefix): void
    {
        foreach ($customerIds->chunk(self::INSERT_CHUNK_SIZE) as $chunk) {
            $codes = $codePrefix ? $this->generateUniqueCodes($offer->shop_id, $codePrefix, $chunk->count()) : [];

            $rows = $chunk->values()->map(fn (int $customerId, int $index) => [
                'shop_id'     => $offer->shop_id,
                'offer_id'    => $offer->id,
                'customer_id' => $customerId,
                'code'        => $codes[$index] ?? null,
                'voucher'     => isset($codes[$index]) ? Str::lower($codes[$index]) : null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            OfferHasCustomer::insert($rows->all());
        }
    }

    /**
     * @return array<int, string>
     */
    private function generateUniqueCodes(int $shopId, string $prefix, int $quantity): array
    {
        $codes = [];

        while (count($codes) < $quantity) {
            $candidates = collect(range(1, $quantity - count($codes)))
                ->map(fn () => $prefix.'-'.$this->randomCode())
                ->unique()
                ->reject(fn (string $code) => in_array($code, $codes, true));

            $lowerCandidates = $candidates->map(fn (string $code) => Str::lower($code))->all();

            $taken = DB::table('offer_has_customers')
                ->where('shop_id', $shopId)
                ->whereIn('voucher', $lowerCandidates)
                ->pluck('voucher')
                ->merge(
                    DB::table('offers')
                        ->where('shop_id', $shopId)
                        ->whereIn('voucher', $lowerCandidates)
                        ->pluck('voucher')
                )
                ->flip();

            foreach ($candidates as $candidate) {
                if (!$taken->has(Str::lower($candidate))) {
                    $codes[] = $candidate;
                }
            }
        }

        return array_slice($codes, 0, $quantity);
    }

    private function randomCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::UNIQUE_CODE_LENGTH; $i++) {
            $code .= self::UNIQUE_CODE_ALPHABET[random_int(0, strlen(self::UNIQUE_CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    public function rules(): array
    {
        return [
            'name'                       => ['required', 'string', 'max:255'],
            'code_type'                  => ['required', Rule::in([self::CODE_TYPE_SHARED, self::CODE_TYPE_UNIQUE])],
            'voucher'                    => [
                'nullable',
                'required_if:code_type,'.self::CODE_TYPE_SHARED,
                'alpha_dash',
                'max:16',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->get('code_type') === self::CODE_TYPE_SHARED && $this->isVoucherTaken((string) $value)) {
                        $fail(__('Voucher code already exists.'));
                    }
                },
            ],
            'code_prefix'                => [
                'nullable',
                'required_if:code_type,'.self::CODE_TYPE_UNIQUE,
                'alpha_num',
                'max:10',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->get('code_type') === self::CODE_TYPE_UNIQUE && $this->isVoucherTaken((string) $value)) {
                        $fail(__('This prefix is already used as a voucher code.'));
                    }
                },
            ],
            'states'                     => ['sometimes', 'array'],
            'states.*'                   => ['string', Rule::in(GetVoucherCustomerListQuery::STATES)],
            'ordered_once'               => ['sometimes', 'boolean'],
            'ordered_once_from_months'   => ['nullable', 'required_if_accepted:ordered_once', 'integer', 'min:0', 'max:120'],
            'ordered_once_to_months'     => ['nullable', 'required_if_accepted:ordered_once', 'integer', 'min:0', 'max:120'],
            'offer_amount'               => ['required', 'numeric', 'min:0'],
            'start_at'                   => ['required', 'date', 'before_or_equal:end_at'],
            'end_at'                     => ['required', 'date'],
            'show_on_customer_dashboard' => ['sometimes', 'boolean'],
            'allowance_type'             => ['required', Rule::in(['percentage_off', 'amount_off', 'discounted_shipping', 'gifts'])],
            'percentage_off'             => ['nullable', 'required_if:allowance_type,percentage_off', 'numeric', 'gt:0', 'lt:100'],
            'amount_off'                 => [
                'nullable',
                'required_if:allowance_type,amount_off',
                'numeric',
                'gt:0',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->get('allowance_type') != 'amount_off') {
                        return;
                    }
                    $minPurchase = (float) $this->get('offer_amount', 0);
                    if ($minPurchase <= 0) {
                        $fail(__('Amount off vouchers require a minimum purchase amount.'));

                        return;
                    }
                    $maxAmountOff = round($minPurchase * StoreVoucherOffers::MAX_AMOUNT_OFF_RATIO, 2);
                    if ((float) $value > $maxAmountOff) {
                        $fail(__('The amount off cannot exceed :percentage of the minimum purchase amount (max :max).', [
                            'percentage' => percentage(StoreVoucherOffers::MAX_AMOUNT_OFF_RATIO, 1),
                            'max'        => $maxAmountOff,
                        ]));
                    }
                },
            ],
            'gifts'                      => ['nullable', 'required_if:allowance_type,gifts', 'array', 'min:1'],
            'gifts.*.product_id'         => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'gifts.*.quantity'           => ['required', 'integer', 'min:1'],
        ];
    }

    private function isVoucherTaken(string $code): bool
    {
        $voucher = Str::lower($code);

        return DB::table('offers')->where('shop_id', $this->shop->id)->where('voucher', $voucher)->whereNull('deleted_at')->exists()
            || DB::table('offer_has_customers')->where('shop_id', $this->shop->id)->where('voucher', $voucher)->exists();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("discounts.{$this->shop->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData): Offer
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
