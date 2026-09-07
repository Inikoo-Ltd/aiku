<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 01:50:00 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\Shopify\Product\CheckShopifyPortfolios;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class AdoptShopifyFulfilmentService
{
    use AsAction;

    /**
     * @param  array<int, array<string, mixed>>  $fulfilmentServices
     * @return array{adopt: array<string, mixed>|null, drop: array<string, mixed>|null}
     */
    public static function pickServices(array $fulfilmentServices, ?string $currentServiceId): array
    {
        $ours = collect($fulfilmentServices)
            ->filter(fn (array $service) => str_starts_with(Arr::get($service, 'serviceName', ''), 'aiku-'))
            ->sortBy(fn (array $service) => Arr::get($service, 'location.createdAt'))
            ->values();

        $drop  = $ours->first(fn (array $service) => $service['id'] === $currentServiceId);
        $adopt = $ours->first(fn (array $service) => $service['id'] !== $currentServiceId);

        return ['adopt' => $adopt, 'drop' => $drop];
    }

    /**
     * @return array{0: bool, 1: string}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, bool $dryRun = false): array
    {
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser) {
            return [false, 'No Shopify user'];
        }

        [$status, $shop] = CheckShopifyChannel::make()->getShopifyShopData($customerSalesChannel);
        if ($status !== 'ok') {
            return [false, 'Cannot read the store: '.json_encode($shop)];
        }

        ['adopt' => $adopt, 'drop' => $drop] = self::pickServices(Arr::get($shop, 'fulfillmentServices', []), $shopifyUser->shopify_fulfilment_service_id);

        if (!$adopt) {
            return [false, 'No earlier aiku fulfilment service on the store to adopt'];
        }

        $plan = sprintf(
            'adopt %s at %s (created %s)%s',
            $adopt['serviceName'],
            Arr::get($adopt, 'location.name'),
            Arr::get($adopt, 'location.createdAt'),
            $drop ? ', drop '.$drop['serviceName'] : ''
        );

        if ($dryRun) {
            return [true, $plan];
        }

        [$updated, $error] = $this->retarget($customerSalesChannel, $adopt['id']);
        if (!$updated) {
            return [false, $error];
        }

        if ($drop) {
            DeleteFulfilmentService::run($customerSalesChannel, $drop['id']);
        }

        $shopifyUser->update([
            'shopify_fulfilment_service_id' => $adopt['id'],
            'shopify_location_id'           => Arr::get($adopt, 'location.id'),
        ]);

        CheckShopifyChannel::run($customerSalesChannel);
        CheckShopifyPortfolios::dispatch($customerSalesChannel);

        return [true, $plan];
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function retarget(CustomerSalesChannel $customerSalesChannel, string $fulfilmentServiceId): array
    {
        $shopifyUser = $customerSalesChannel->user;
        $client      = $shopifyUser->getShopifyClient();
        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        $mutation = <<<'MUTATION'
        mutation fulfillmentServiceUpdate($id: ID!, $name: String, $callbackUrl: URL) {
          fulfillmentServiceUpdate(id: $id, name: $name, callbackUrl: $callbackUrl) {
            fulfillmentService { id serviceName callbackUrl }
            userErrors { field message }
          }
        }
        MUTATION;

        $response = $client->request('POST', '/admin/api/2025-07/graphql.json', [
            'json' => [
                'query'     => $mutation,
                'variables' => [
                    'id'          => $fulfilmentServiceId,
                    'name'        => GetFulfilmentServiceName::run($customerSalesChannel),
                    'callbackUrl' => 'https://'.config('app.domain').'/webhooks/shopify/'.$shopifyUser->id,
                ],
            ],
        ]);

        if (!empty($response['errors']) || !isset($response['body'])) {
            return [false, 'Error in API response: '.json_encode($response['errors'] ?? [])];
        }

        $body       = $response['body']->toArray();
        $userErrors = Arr::get($body, 'data.fulfillmentServiceUpdate.userErrors', []);
        if (!empty($userErrors)) {
            return [false, 'User errors: '.json_encode($userErrors)];
        }

        return [true, ''];
    }

    public function getCommandSignature(): string
    {
        return 'shopify:adopt-location {customerSalesChannel : slug of the open channel} {--dry-run}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        [$ok, $message] = $this->handle($customerSalesChannel, (bool) $command->option('dry-run'));
        $ok ? $command->info($message) : $command->error($message);

        return $ok ? 0 : 1;
    }
}
