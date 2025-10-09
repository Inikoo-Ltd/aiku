<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 30 Jun 2025 13:53:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Magento\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\MagentoUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetRetinaOrdersFromMagento extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'orders:magento {magentoUser}';

    public function handle(MagentoUser $magentoUser): void
    {
        DB::transaction(function () use ($magentoUser) {
            $existingOrderKeys = $magentoUser
                ->customerSalesChannel
                ->orders()
                ->pluck('platform_order_id')
                ->toArray();

            $searchCriteria = [
                'filterGroups' => [
                    [
                        'filters' => [
                            [
                                'field' => 'created_at',
                                'value' => date('Y-m-d'),
                                'conditionType' => 'gteq'
                            ]
                        ]
                    ]
                ],
                'pageSize' => 100,
                'currentPage' => 1
            ];

            $response = $magentoUser->getOrders([
                'searchCriteria' => $searchCriteria
            ]);

            foreach ($response['items'] as $order) {
                if (in_array(Arr::get($order, 'entity_id'), $existingOrderKeys)) {
                    continue;
                }

                $shippingAddress = Arr::get($order, 'extension_attributes.shipping_assignments.0.shipping.address', []);

                if (!empty($shippingAddress)) {
                    StoreOrderFromMagento::run($magentoUser, $order);
                } else {
                    \Sentry::captureMessage('The order doesnt have shipping, order: id ' . Arr::get($order, 'entity_id'));
                }
            }
        });
    }

    public function asController(MagentoUser $magentoUser, ActionRequest $request): void
    {
        $this->initialisation($magentoUser->organisation, $request);

        $this->handle($magentoUser);
    }

    public function asCommand(Command $command): void
    {
        $magentoUser = MagentoUser::find($command->argument('magentoUser'));

        $this->handle($magentoUser);
    }
}
