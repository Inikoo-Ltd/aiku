<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckShopifyPortfolios extends OrgAction
{
    use AsAction;

    public string $jobQueue = 'shopify';

    private array $tableData = [];


    public function handle(Group|Organisation|Shop|Customer|CustomerSalesChannel|Portfolio|null $parent = null, ?Command $command = null): void
    {
        $shopifyPlatform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        $query = DB::table('portfolios')->select('id')->where('platform_id', $shopifyPlatform->id);


        if ($parent instanceof Shop) {
            $query->where('shop_id', $parent->id);
        } elseif ($parent instanceof Customer) {
            $query->where('customer_id', $parent->id);
        } elseif ($parent instanceof CustomerSalesChannel) {
            $query->where('customer_sales_channel_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $query->where('organisation_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $query->where('group_id', $parent->id);
        } elseif ($parent instanceof Portfolio) {
            $query->where('portfolios.id', $parent->id);
        }

        foreach ($query->get()->chunk(200) as $portfolios) {
            CheckBulkShopifyPortfolios::dispatch($portfolios)->delay(5);
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:check_portfolios {parent_type} {parent_slug}';
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        CheckShopifyPortfolios::dispatch($customerSalesChannel);

        $request->session()->flash('modal', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('We already run the sync in background please wait.'),
        ]);
    }

    public function asCommand(Command $command): void
    {
        $parentType = $command->argument('parent_type');
        $parentSlug = $command->argument('parent_slug');

        $parent = match (strtolower($parentType)) {
            'grp' => Group::where('slug', $parentSlug)->firstOrFail(),
            'org' => Organisation::where('slug', $parentSlug)->firstOrFail(),
            'shp' => Shop::where('slug', $parentSlug)->firstOrFail(),
            'cus' => Customer::where('slug', $parentSlug)->firstOrFail(),
            'csc' => CustomerSalesChannel::where('slug', $parentSlug)->firstOrFail(),
            'portfolio' => Portfolio::where('id', $parentSlug)->firstOrFail(),
            default => throw new \InvalidArgumentException("Invalid parent type: $parentType"),
        };

        $this->handle($parent, $command);


        $command->info("\nPortfolio Shopify Status:");
        $command->table(
            ['Portfolio', 'SKU', 'Status', 'Has Valid Product ID', 'Exists in Platform', 'Platform Status', 'Possible Matches'],
            $this->tableData
        );
    }

}
