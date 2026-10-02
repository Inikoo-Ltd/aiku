<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Bali Office, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\Shopify\FulfilmentService\AdoptShopifyFulfilmentService;
use App\Actions\Dropshipping\Shopify\FulfilmentService\DeleteFulfilmentService;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A reconnect (ClaimShopifyUser/AdoptShopifyFulfilmentService) only cleans up the store it
 * reconnects to. A shop that never reconnects again keeps every earlier aiku location forever,
 * and Shopify can still route an order to one: its callback points at a shopify_user we long
 * since soft-deleted, so the request never reaches us. This sweeps shops with a history of
 * disconnects, lists every aiku fulfilment service still on the store and removes the ones that
 * are not the live login, skipping any that still has an order open there so nothing in flight
 * is lost or double shipped.
 */
class RemoveStaleShopifyFulfilmentLocations
{
    use AsAction;
    use WithShopifyApi;

    public string $jobQueue = 'shopify';

    private const int MAX_ORDER_PAGES = 10;

    /**
     * @return array{0: bool, 1: string, 2: array<int, array{id: string, name: string, action: string, reason: string|null}>}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, bool $dryRun = true): array
    {
        /** @var ShopifyUser|null $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser) {
            return [false, 'No live Shopify user on this channel', []];
        }

        /** With no current service to compare against, every aiku service would look stale, the live one among them. */
        if (!$shopifyUser->shopify_fulfilment_service_id) {
            return [false, 'The live login has no fulfilment service of its own yet, nothing can be told apart as stale', []];
        }

        [$status, $shop] = CheckShopifyChannel::make()->getShopifyShopData($customerSalesChannel);
        if ($status !== 'ok') {
            return [false, 'Cannot read the store: '.json_encode($shop), []];
        }

        $ours = collect(Arr::get($shop, 'fulfillmentServices', []))
            ->filter(fn (array $service) => str_starts_with(Arr::get($service, 'serviceName', ''), 'aiku-'))
            ->values();

        $report = [];

        /**
         * The live service itself can be broken the same way: a reconnect that reused the channel
         * kept the service's name, but every reconnect gets a fresh login, so the callback can
         * still carry a retired login's id. That service must not be deleted, only re-pointed.
         */
        $current          = $ours->firstWhere('id', $shopifyUser->shopify_fulfilment_service_id);
        $expectedCallback = 'https://'.config('app.domain').'/webhooks/shopify/'.$shopifyUser->id;
        if ($current && Arr::get($current, 'callbackUrl') !== $expectedCallback) {
            if ($dryRun) {
                $report[] = ['id' => $current['id'], 'name' => Arr::get($current, 'serviceName', 'Unknown'), 'action' => 'would re-point', 'reason' => 'Its callback holds a retired login'];
            } else {
                [$retargeted, $error] = AdoptShopifyFulfilmentService::make()->retarget($customerSalesChannel, $current['id']);
                $report[]             = ['id' => $current['id'], 'name' => Arr::get($current, 'serviceName', 'Unknown'), 'action' => $retargeted ? 're-pointed' : 'failed', 'reason' => $retargeted ? null : $error];
            }
        }

        $stale = $ours->filter(fn (array $service) => $service['id'] !== $shopifyUser->shopify_fulfilment_service_id)->values();

        if ($stale->isEmpty() && $report === []) {
            return [true, 'No stale aiku fulfilment service on this store', []];
        }

        foreach ($stale as $service) {
            $report[] = $this->process($customerSalesChannel, $shopifyUser, $service, $dryRun);
        }

        return [true, count($report).' stale aiku fulfilment service(s) found', $report];
    }

    /**
     * @param  array<string, mixed>  $service
     * @return array{id: string, name: string, action: string, reason: string|null}
     */
    private function process(CustomerSalesChannel $customerSalesChannel, ShopifyUser $shopifyUser, array $service, bool $dryRun): array
    {
        $id          = $service['id'];
        $name        = Arr::get($service, 'serviceName', 'Unknown');
        $locationId  = Arr::get($service, 'location.id');

        $openOrders = $locationId ? $this->openOrderCount($shopifyUser, $locationId) : null;

        if ($openOrders === null) {
            return ['id' => $id, 'name' => $name, 'action' => 'skipped', 'reason' => 'Could not check for open fulfilment orders, left for manual review'];
        }

        if ($openOrders > 0) {
            return ['id' => $id, 'name' => $name, 'action' => 'skipped', 'reason' => "$openOrders order(s) still open at this location, left for manual review"];
        }

        if ($dryRun) {
            return ['id' => $id, 'name' => $name, 'action' => 'would delete', 'reason' => null];
        }

        [$deleted, $error] = DeleteFulfilmentService::run($customerSalesChannel, $id, 'DELETE');

        return ['id' => $id, 'name' => $name, 'action' => $deleted ? 'deleted' : 'failed', 'reason' => $deleted ? null : (is_string($error) ? $error : json_encode($error))];
    }

    /**
     * Counts fulfilment orders Shopify still shows as open or in progress at a location,
     * regardless of our own requestStatus: the whole point is to catch ones whose request
     * never reached us (an UNSUBMITTED one, since its callback points at a dead shopify_user)
     * as well as ones already accepted, so only Shopify's own status can be trusted here.
     *
     * Returns null when it could not read the store, so the caller skips rather than deletes.
     */
    private function openOrderCount(ShopifyUser $shopifyUser, string $locationId): ?int
    {
        $query = <<<'QUERY'
        query staleLocationOrders($query: String!, $after: String) {
          orders(first: 25, after: $after, query: $query) {
            edges {
              node {
                fulfillmentOrders(first: 10) {
                  edges {
                    node {
                      status
                      assignedLocation { location { id } }
                    }
                  }
                }
              }
            }
            pageInfo { hasNextPage endCursor }
          }
        }
        QUERY;

        $variables = ['query' => 'fulfillment_status:unfulfilled OR fulfillment_status:partial', 'after' => null];
        $count     = 0;

        for ($page = 0; $page < self::MAX_ORDER_PAGES; $page++) {
            [$ok, $response] = $this->doPost($shopifyUser, $query, $variables);
            if (!$ok) {
                return null;
            }

            $body = $response['body']->toArray();
            if (!empty($body['errors'])) {
                return null;
            }

            foreach (Arr::get($body, 'data.orders.edges', []) as $edge) {
                foreach (Arr::get($edge, 'node.fulfillmentOrders.edges', []) as $fulfilmentOrderEdge) {
                    $node = Arr::get($fulfilmentOrderEdge, 'node');
                    if (Arr::get($node, 'assignedLocation.location.id') === $locationId
                        && in_array(Arr::get($node, 'status'), ['OPEN', 'IN_PROGRESS'], true)) {
                        $count++;
                    }
                }
            }

            if (!Arr::get($body, 'data.orders.pageInfo.hasNextPage')) {
                break;
            }

            $variables['after'] = Arr::get($body, 'data.orders.pageInfo.endCursor');
        }

        return $count;
    }

    public function getCommandSignature(): string
    {
        return 'shopify:remove-stale-fulfilment-locations {customerSalesChannel? : slug of a single channel; omitted = every open Shopify channel with a soft-deleted login} {--live : actually remove instead of a dry run}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = !$command->option('live');

        if ($slug = $command->argument('customerSalesChannel')) {
            $channels = CustomerSalesChannel::where('slug', $slug)->get();
        } else {
            $customerIds = ShopifyUser::onlyTrashed()
                ->whereNotNull('shopify_location_id')
                ->whereNotNull('customer_id')
                ->pluck('customer_id')
                ->unique();

            $channels = CustomerSalesChannel::whereIn('customer_id', $customerIds)
                ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                ->whereHasMorph('user', ShopifyUser::class)
                ->get();
        }

        $command->info(($dryRun ? 'DRY RUN — ' : 'LIVE — ').'checking '.$channels->count().' channel(s).');

        $toReview = [];
        $acted    = 0;

        foreach ($channels as $channel) {
            [$ok, $message, $report] = $this->handle($channel, $dryRun);

            if (!$ok || $report === []) {
                $command->line(($ok ? 'ok   ' : 'FAIL ').$channel->slug.': '.$message);

                continue;
            }

            $command->line($channel->slug.': '.$message);
            foreach ($report as $row) {
                $acted++;
                $command->line('    '.$row['action'].' '.$row['name'].' ('.$row['id'].')'.($row['reason'] ? ' - '.$row['reason'] : ''));

                if (in_array($row['action'], ['skipped', 'failed'], true)) {
                    $toReview[] = $channel->slug.': '.$row['name'].' - '.$row['reason'];
                }
            }
        }

        $command->info("\n$acted stale service(s) across ".$channels->count().' channel(s) checked.');

        if ($toReview !== []) {
            $command->warn(count($toReview).' need manual review:');
            foreach ($toReview as $line) {
                $command->warn('  '.$line);
            }
        }

        return 0;
    }
}
