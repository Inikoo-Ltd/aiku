<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Http\Resources\Catalogue\TagsResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;

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
            'stats' => $customer->stats,
            'currency'  => CurrencyResource::make($customer->shop->currency)->toArray(request()),
            'balance'  => [
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
            ],
            'editWebUser' => $webUserRoute,
            'tag_routes' => $tagRoute,
            'tags_selected_id' => $customer->tags->pluck('id')->toArray(),
            'tags' =>  TagsResource::collection($customer->tags)->toArray(request()),
        ];
    }
}
