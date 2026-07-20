<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Http\Resources\Catalogue\OffersResource;
use App\Http\Resources\Catalogue\TagsResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Models\CRM\Customer;
use App\Models\Discounts\Offer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;

class GetCustomerShowcase
{
    use AsObject;

    public function handle(Customer $customer): array
    {
        $tagRoute = [
            'index_tag' => [
                'name'       => 'grp.json.customer.tags.index',
                'parameters' => [
                    'customer' => $customer,
                ]
            ],
            'store_tag' => [
                'name'       => 'grp.models.customer.tags.store',
                'parameters' => [
                    'customer' => $customer->id,
                ]
            ],
            'update_tag' => [
                'name'       => 'grp.models.customer.tags.update',
                'parameters' => [
                    'customer' => $customer->id,
                ],
                'method'    => 'patch'
            ],
            'delete_tag' => [
                'name'       => 'grp.models.customer.tags.delete',
                'parameters' => [
                    'customer' => $customer->id,
                ],
                'method'    => 'delete'
            ],
            'attach_tag' => [
                'name'       => 'grp.models.customer.tags.attach',
                'parameters' => [
                    'customer' => $customer->id,
                ],
                'method'    => 'post'
            ],
            'detach_tag' => [
                'name'       => 'grp.models.customer.tags.detach',
                'parameters' => [
                    'customer' => $customer->id,
                ],
                'method'    => 'delete'
            ],
        ];

        $webUser = $customer->webUsers()->first();
        $webUserRoute = null;
        if ($webUser) {
            $webUserRoute = [
                'name'       => 'grp.org.shops.show.crm.customers.show.web_users.edit',
                'parameters' => [
                    'organisation' => $customer->organisation->slug,
                    'shop'         => $customer->shop->slug,
                    'customer'     => $customer->slug,
                    'webUser'      => $webUser->slug
                ]
            ];
        }

        $lastOrder = $customer->orders()->latest('submitted_at')->first();

        $ordersRoute = $customer->shop->type !== ShopTypeEnum::EXTERNAL ? [
            'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
            'parameters' => [
                $customer->organisation->slug,
                $customer->shop->slug,
                $customer->slug,
            ],
        ] : null;

        $firstAllowance = DB::table('offer_allowances')
            ->selectRaw("DISTINCT ON (offer_id) offer_id, type AS allowance_type, class AS allowance_class, target_type AS allowance_target_type, (data->>'percentage_off')::numeric AS allowance_percentage_off, (data->>'category_id')::integer AS allowance_category_id")
            ->where('type', '!=', 'unknown')
            ->orderByRaw('offer_id, id');

        $customerOffers = Offer::query()
            ->where('offers.trigger_id', $customer->id)
            ->where('offers.trigger_type', 'Customer')
            ->leftJoinSub($firstAllowance, 'fa', 'fa.offer_id', '=', 'offers.id')
            ->leftJoin('product_categories', DB::raw('product_categories.id'), '=', DB::raw('fa.allowance_category_id'))
            ->select([
                'offers.id',
                'offers.slug',
                'offers.code',
                'offers.name',
                'offers.type',
                'offers.state',
                'offers.start_at',
                'offers.end_at',
                'offers.trigger_data',
                'fa.allowance_type',
                'fa.allowance_class',
                'fa.allowance_target_type',
                'fa.allowance_percentage_off',
                'product_categories.name as allowance_category_name',
            ])
            ->orderByRaw("CASE offers.state WHEN 'active' THEN 0 WHEN 'in_process' THEN 1 ELSE 2 END, offers.id")
            ->limit(10)
            ->get();

        return [
            'customer' => CustomerResource::make($customer)->getArray(),
            'address_management' => GetCustomerAddressManagement::run(customer:$customer),
            'require_approval' => Arr::get($customer->shop->settings, 'registration.require_approval', false),
            'approveRoute'       => [
                'name'       => 'grp.models.customer.approve',
                'parameters' => [
                    'customer' => $customer->id
                ]
            ],
            'internal_note' => [
                "label"       => __("Private"),
                "note"        => $customer->internal_notes ?? '',
                "information" => __("This note is only visible to staff members. Staff can communicate with each other about the customer."),
                "editable"    => true,
                "bgColor"     => "#FF7DBD",
                "field"       => "internal_notes"
            ],
            'update_route' => [
                'name'       => 'grp.models.customer.update',
                'parameters' => [
                    'customer' => $customer->id
                ]
            ],
            'store_note_route' => [
                'name'       => 'grp.models.customer.note.store',
                'parameters' => [
                    'customer' => $customer->id
                ],
                'method'     => 'post'
            ],
            'shop'              => [
                'id' => $customer->shop->id,
                'name' => $customer->shop->name,
                'slug' => $customer->shop->slug,
                'type' => $customer->shop->type,
            ],
            'stats' => $customer->stats,
            'last_order' => $lastOrder ? [
                'reference'    => $lastOrder->reference,
                'slug'         => $lastOrder->slug,
                'state'        => $lastOrder->state->value,
                'submitted_at' => $lastOrder->submitted_at?->toIso8601String(),
            ] : null,
            'currency'  => CurrencyResource::make($customer->shop->currency)->toArray(request()),
            'balance'  => $customer->shop->type !== ShopTypeEnum::EXTERNAL ? [
                'route_store'    => [
                    'name'       => 'grp.models.customer.credit-transaction.store',
                    'parameters' => [
                        'customer'     => $customer->id
                    ]
                ],
                'route_increase'    => [
                    'name'       => 'grp.models.credit_transaction.increase',
                    'parameters' => [
                        'customer'     => $customer->id
                    ]
                ],
                'route_decrease'    => [
                    'name'       => 'grp.models.credit_transaction.decrease',
                    'parameters' => [
                        'customer'     => $customer->id
                    ]
                ],
                'increase_reasons_options' => CreditTransactionReasonEnum::getIncreaseReasons(),
                'decrease_reasons_options' => CreditTransactionReasonEnum::getDecreaseReasons(),

                'type_options' => CreditTransactionTypeEnum::getOptions()
            ] : null,
            'orders_route' => $ordersRoute,
            'editWebUser' => $webUserRoute,
            'tag_routes' => $tagRoute,
            'tags_selected_id' => $customer->tags->pluck('id')->toArray(),
            'tags'             => TagsResource::collection($customer->tags)->toArray(request()),
            'offers'           => OffersResource::collection($customerOffers)->toArray(request()),
            'upcoming_transaction_route' => [
                'store' => [
                    'name'       => 'grp.models.customer.upcoming_transactions.store',
                    'parameters' => [
                        'customer' => $customer->id
                    ]
                ],
                'index' => [
                    'name' => 'grp.org.shops.show.crm.customers.show.upcoming_transactions.index',
                    'parameters' => [
                        'organisation' => $customer->organisation->slug,
                        'shop'         => $customer->shop->slug,
                        'customer'     => $customer->slug
                    ]
                ]
            ]
        ];
    }
}
