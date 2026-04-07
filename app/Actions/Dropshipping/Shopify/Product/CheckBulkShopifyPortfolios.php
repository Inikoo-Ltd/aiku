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
use Sentry;

class CheckBulkShopifyPortfolios extends OrgAction
{
    use AsAction;

    public string $jobQueue = 'shopify';

    private array $tableData = [];


    public function handle($collections, ?Command $command = null): void
    {

        $count = 0;
        foreach ($collections as $portfolioData) {
            $portfolio = Portfolio::find($portfolioData->id);
            if ($portfolio) {
                try {
                    $count++;

                    $portfolio = CheckShopifyPortfolio::run($portfolio);
                    if ($count === 100) {
                        $count = 0;

                        sleep(5);
                    }
                } catch (\Exception $e) {
                    Sentry::captureException($e);
                }


                if ($command) {
                    $this->tableData[] = [
                        'slug'                          => $portfolio->reference ?? $portfolio->id,
                        'sku'                           => $portfolio->sku,
                        'status'                        => $portfolio->status ? 'Open' : 'Closed',
                        'has_valid_platform_product_id' => $portfolio->has_valid_platform_product_id ? 'Yes' : 'No',
                        'exist_in_platform'             => $portfolio->exist_in_platform ? 'Yes' : 'No',
                        'platform_status'               => $portfolio->platform_status ? 'Yes' : 'No',
                        'possible_matches'              => $portfolio->platform_possible_matches['number_matches'] ?? 0
                    ];
                }
            }
        }
    }
}
