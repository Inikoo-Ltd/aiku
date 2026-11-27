<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Chat\ChatSession\GetChatClaimCase;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerClaim\CustomerClaimLine;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the customer's claim form shows for one of their orders (HELP-4869): the lines still left
 * to claim, the reasons a customer may give, which outcomes each line allows and which reasons
 * need photos. The same form is used in Merlin, the account and the website chat. Customers claim
 * whole items only, and lines already in a claim waiting for review are not offered again.
 */
class GetCustomerClaimForm
{
    use AsAction;

    public const array CUSTOMER_REASONS = [
        DeliveryNoteItemReplacementReasonEnum::DAMAGED_IN_TRANSIT,
        DeliveryNoteItemReplacementReasonEnum::MISSING_FROM_PARCEL,
        DeliveryNoteItemReplacementReasonEnum::WRONG_ITEM_SENT,
        DeliveryNoteItemReplacementReasonEnum::FAULTY_PRODUCT,
        DeliveryNoteItemReplacementReasonEnum::LOST_IN_TRANSIT,
        DeliveryNoteItemReplacementReasonEnum::POOR_PACKAGING,
    ];

    public const array PHOTO_REASONS = [
        DeliveryNoteItemReplacementReasonEnum::DAMAGED_IN_TRANSIT,
        DeliveryNoteItemReplacementReasonEnum::WRONG_ITEM_SENT,
        DeliveryNoteItemReplacementReasonEnum::FAULTY_PRODUCT,
        DeliveryNoteItemReplacementReasonEnum::POOR_PACKAGING,
    ];

    public const array LISTED_REASONS = [
        DeliveryNoteItemReplacementReasonEnum::DAMAGED_IN_TRANSIT,
        DeliveryNoteItemReplacementReasonEnum::MISSING_FROM_PARCEL,
        DeliveryNoteItemReplacementReasonEnum::WRONG_ITEM_SENT,
        DeliveryNoteItemReplacementReasonEnum::FAULTY_PRODUCT,
    ];

    public const int MAX_PHOTOS_PER_LINE = 4;

    /**
     * Reasons in the customer's words. Lost parcels are claimed for the whole parcel, and poor
     * packaging is ours to judge, so neither is listed per item.
     */
    public static function reasonLabel(DeliveryNoteItemReplacementReasonEnum $reason, ?string $locale): string
    {
        return match ($reason) {
            DeliveryNoteItemReplacementReasonEnum::DAMAGED_IN_TRANSIT => __('Arrived broken or damaged', [], $locale),
            DeliveryNoteItemReplacementReasonEnum::MISSING_FROM_PARCEL => __('Missing from the parcel', [], $locale),
            DeliveryNoteItemReplacementReasonEnum::WRONG_ITEM_SENT    => __('Wrong item sent', [], $locale),
            DeliveryNoteItemReplacementReasonEnum::FAULTY_PRODUCT     => __('Faulty or does not work', [], $locale),
            DeliveryNoteItemReplacementReasonEnum::LOST_IN_TRANSIT    => __('The parcel never arrived', [], $locale),
            default                                                   => __($reason->label(), [], $locale),
        };
    }

    /**
     * @return array<string, mixed>|null null when the order is not one of the customer's dispatched orders
     */
    public function handle(Customer $customer, Order $order): ?array
    {
        if ($order->customer_id !== $customer->id || !in_array($order->state, [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED], true)) {
            return null;
        }

        $case         = GetChatClaimCase::make()->orderCase($order);
        $transactions = Transaction::whereIn('id', array_column($case['products'], 'transaction_id'))->where('model_type', 'Product')->get(['id', 'model_id'])->pluck('model_id', 'id');
        $products     = Product::whereIn('id', $transactions->values())->get(['id', 'available_quantity'])->keyBy('id');
        $locale       = $customer->shop->language?->code;

        $inReview     = CustomerClaimLine::query()
            ->whereHas('claim', fn ($claims) => $claims->where('order_id', $order->id)->where('state', CustomerClaimStateEnum::IN_REVIEW))
            ->selectRaw('transaction_id, sum(quantity) as quantity')
            ->groupBy('transaction_id')
            ->pluck('quantity', 'transaction_id');

        $lines = collect($case['products'])
            ->map(fn (array $line) => array_merge($line, ['claimable' => floor($line['claimable'] - (float) ($inReview[$line['transaction_id']] ?? 0) + 0.0005)]))
            ->filter(fn (array $line) => $line['claimable'] >= 1)
            ->map(fn (array $line) => [
                'transaction_id' => $line['transaction_id'],
                'code'           => $line['code'],
                'name'           => $line['name'],
                'claimable'      => (int) $line['claimable'],
                'unit_value'     => $line['dispatched'] > 0 ? round(($case['invoiced_net'][$line['transaction_id']] ?? 0) / $line['dispatched'] * ($case['tax_ratio'] ?? 1), 2) : 0.0,
                'can_replace'    => (float) ($products->get($transactions->get($line['transaction_id']))?->available_quantity ?? 0) >= $line['claimable'],
            ])
            ->values()
            ->all();

        return [
            'order'           => ['id' => $order->id, 'reference' => $order->reference, 'dispatched_at' => $order->dispatched_at?->toIso8601String()],
            'too_old'         => self::tooOld($order),
            'max_age_days'    => GetCustomerClaimSettings::for($order->shop)['claim_days'],
            'lines'           => $lines,
            'reasons'         => array_map(fn (DeliveryNoteItemReplacementReasonEnum $reason) => [
                'value'        => $reason->value,
                'label'        => self::reasonLabel($reason, $locale),
                'needs_photos' => in_array($reason, self::PHOTO_REASONS, true),
            ], self::LISTED_REASONS),
            'outcomes'        => self::outcomesFor($order, !empty($case['invoiced'])),
            'currency'        => $case['currency'],
            'max_photos'      => self::MAX_PHOTOS_PER_LINE,
            'is_dropshipping' => (bool) $order->customer_client_id,
        ];
    }

    /**
     * Customers claim for the number of days after dispatch the shop set (claim_days).
     */
    public static function tooOld(Order $order): bool
    {
        return (bool) $order->dispatched_at?->lt(now()->subDays(GetCustomerClaimSettings::for($order->shop)['claim_days']));
    }

    /**
     * Customers claim for themselves only where an organisation admin switched it on for the shop.
     */
    public static function enabledFor(Shop $shop): bool
    {
        return GetCustomerClaimSettings::for($shop)['self_service'];
    }

    /**
     * The "Report a problem" button on the order in the customer's account. The dispatch email links
     * to the order with ?claim=1, which opens the form straight away.
     *
     * @return array{order_id: int, open: bool}|null
     */
    public static function accountButton(Order $order, bool $open = false): ?array
    {
        if (!in_array($order->state, [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED], true) || !self::enabledFor($order->shop)) {
            return null;
        }

        return ['order_id' => $order->id, 'open' => $open];
    }

    /**
     * Refund to balance needs an invoice; the next order exists only for a customer who receives
     * their orders themselves, so never for a dropshipping order sent to their end customer.
     *
     * @return array<int, string>
     */
    public static function outcomesFor(Order $order, bool $hasInvoice = true): array
    {
        return array_values(array_filter([
            $hasInvoice ? 'refund_to_balance' : null,
            'replacement',
            $order->customer_client_id ? null : 'next_order',
        ]));
    }
}
