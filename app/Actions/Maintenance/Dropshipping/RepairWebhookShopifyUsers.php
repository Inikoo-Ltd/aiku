<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\Webhook\CreateShopifyWebhooks;
use App\Actions\Dropshipping\Shopify\Webhook\DeleteWebhooksFromShopify;
use App\Actions\Dropshipping\Shopify\Webhook\IndexShopifyUserWebhooks;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Brings every connected store to the webhooks we want: PRODUCTS_UPDATE removed (Nov 2025 load,
 * it fired on our own stock pushes), PRODUCTS_DELETE added (unlinks products the merchant deleted
 * in Shopify). Stores connected after Oct 2026 get PRODUCTS_DELETE when they connect.
 * Dry run unless --apply.
 */
class RepairWebhookShopifyUsers
{
    use AsAction;
    use WithActionUpdate;
    use WithShopifyApi;

    /**
     * @return array{status: string, removed_update: int, added_delete: bool}
     */
    public function handle(ShopifyUser $shopifyUser, bool $apply = false): array
    {
        if (!$shopifyUser->getShopifyClient()) {
            return ['status' => 'no_client', 'removed_update' => 0, 'added_delete' => false];
        }

        [$listed, $webhooks] = IndexShopifyUserWebhooks::run($shopifyUser);

        if (!$listed) {
            return ['status' => 'list_failed', 'removed_update' => 0, 'added_delete' => false];
        }

        $webhooks = is_array($webhooks) ? $webhooks : [];

        $updateWebhooks = array_filter($webhooks, fn (array $webhook) => $webhook['topic'] === 'PRODUCTS_UPDATE');
        $hasDelete      = collect($webhooks)->contains(fn (array $webhook) => $webhook['topic'] === 'PRODUCTS_DELETE'
            && str_ends_with((string) $webhook['callbackUrl'], "/webhooks/shopify/{$shopifyUser->id}/products-deleted"));

        if ($apply) {
            foreach ($updateWebhooks as $webhook) {
                DeleteWebhooksFromShopify::make()->deleteWebhook($shopifyUser, $webhook['id']);
            }

            if (!$hasDelete) {
                [$created] = CreateShopifyWebhooks::run($shopifyUser, ['PRODUCTS_DELETE']);

                if (!$created) {
                    return ['status' => 'create_failed', 'removed_update' => count($updateWebhooks), 'added_delete' => false];
                }
            }
        }

        return ['status' => 'ok', 'removed_update' => count($updateWebhooks), 'added_delete' => !$hasDelete];
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_webhooks {--apply : change the stores; without it only reports}';
    }

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();

        $apply  = (bool) $command->option('apply');
        $totals = ['stores' => 0, 'ok' => 0, 'no_client' => 0, 'list_failed' => 0, 'create_failed' => 0, 'removed_update' => 0, 'added_delete' => 0];

        $shopifyUsers = ShopifyUser::whereNotNull('customer_id')
            ->whereHas('customerSalesChannel', fn ($query) => $query->where('status', CustomerSalesChannelStatusEnum::OPEN))
            ->orderBy('id')
            ->get();

        foreach ($shopifyUsers as $shopifyUser) {
            $result = $this->handle($shopifyUser, $apply);

            $totals['stores']++;
            $totals[$result['status']]++;
            $totals['removed_update'] += $result['removed_update'];
            $totals['added_delete']   += $result['added_delete'] ? 1 : 0;

            if ($result['status'] !== 'ok') {
                $command->warn("{$shopifyUser->id} {$shopifyUser->name}: {$result['status']}");
            }
        }

        $command->info(($apply ? 'APPLIED' : 'DRY RUN, nothing changed (use --apply)').': '.json_encode($totals));
    }
}
