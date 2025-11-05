<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairWebhookShopifyUsers
{
    use AsAction;
    use WithActionUpdate;
    use WithShopifyApi;

    public function handle(ShopifyUser $shopifyUser): array
    {
        $success = false;
        $webhooksData = [];

        if ($shopifyUser->getShopifyClient()) {

            $webhooksData = Arr::get($shopifyUser->settings, 'webhooks');

            if (!empty($webhooksData)) {
                foreach ($webhooksData as $webhook) {
                    $webhookId = $webhook['id'];

                    $mutation = <<<'MUTATION'
                    mutation webhookSubscriptionDelete(\$id: ID!) {
                        webhookSubscriptionDelete(id: \$id) {
                            deletedWebhookSubscriptionId
                            userErrors {
                                field
                                message
                            }
                        }
                    }
                    MUTATION;

                    $variables = [
                        'id' => $webhookId
                    ];

                    list($status) = $this->doPost($shopifyUser, $mutation, $variables);
                    $success = $status;
                }
            }
        }

        return [
            'name' => $shopifyUser->name,
            'success' => $success ? 'OK' : 'NO',
            'webhooks' => json_encode($webhooksData)
        ];
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_webhooks';
    }

    public function asCommand(Command $command): void
    {
        $tableData = [];
        $counter   = 1;

        foreach (ShopifyUser::withTrashed()->orderBy('id')->get() as $shopifyUser) {
            $tableData[] = array_merge(['counter' => $counter++], $this->handle($shopifyUser, $command));
        }

        $command->table(
            ['#', 'ShopifyUser Name', 'Status', 'Webhooks'],
            array_map(function ($item) {
                return [$item['counter'], $item['name'], $item['success'], $item['webhooks']];
            }, $tableData)
        );
    }

}
