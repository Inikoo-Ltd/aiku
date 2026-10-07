<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer\Json;

use App\Actions\Discounts\Offer\GetVoucherCustomerListQuery;
use App\Actions\OrgAction;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class GetVoucherCustomerListPreview extends OrgAction
{
    private const int SAMPLE_SIZE = 10;

    /**
     * @return array{number_customers: int, number_email_subscribers: int, customers: array<int, array<string, mixed>>}
     */
    public function handle(Shop $shop, array $segments): array
    {
        $customers = GetVoucherCustomerListQuery::run($shop, $segments);

        $emailSubscribers = (clone $customers)
            ->whereNotNull('customers.email')
            ->whereExists(function (Builder $query) {
                $query->select(DB::raw(1))
                    ->from('customer_comms')
                    ->whereColumn('customer_comms.customer_id', 'customers.id')
                    ->where('customer_comms.is_subscribed_to_marketing', true);
            });

        $stateLabels = CustomerStateEnum::labels();

        return [
            'number_customers'         => (clone $customers)->count(),
            'number_email_subscribers' => $emailSubscribers->count(),
            'customers'                => $customers
                ->select(['customers.id', 'customers.slug', 'customers.reference', 'customers.name', 'customers.state', 'customers.last_invoiced_at'])
                ->orderByRaw('customers.last_invoiced_at DESC NULLS LAST')
                ->limit(self::SAMPLE_SIZE)
                ->get()
                ->map(fn ($customer) => [
                    'id'               => $customer->id,
                    'slug'             => $customer->slug,
                    'reference'        => $customer->reference,
                    'name'             => $customer->name,
                    'state'            => $stateLabels[$customer->state] ?? $customer->state,
                    'last_invoiced_at' => $customer->last_invoiced_at,
                ])
                ->all(),
        ];
    }

    public function rules(): array
    {
        return [
            'states'                   => ['sometimes', 'array'],
            'states.*'                 => ['string', Rule::in(GetVoucherCustomerListQuery::STATES)],
            'ordered_once'             => ['sometimes', 'boolean'],
            'ordered_once_from_months' => ['nullable', 'required_if_accepted:ordered_once', 'integer', 'min:0', 'max:120'],
            'ordered_once_to_months'   => ['nullable', 'required_if_accepted:ordered_once', 'integer', 'min:0', 'max:120'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
