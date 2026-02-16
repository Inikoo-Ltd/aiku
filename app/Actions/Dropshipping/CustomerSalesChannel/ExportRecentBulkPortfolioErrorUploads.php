<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Portfolio\ExportRecentPortfolioErrorExport;
use App\Exports\StoredItem\StoredItemExport;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Upload;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRecentBulkPortfolioErrorUploads extends OrgAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Upload $upload): BinaryFileResponse
    {
        return $this->export(new ExportRecentPortfolioErrorExport($upload), 'errors-sku-portfolios', 'xlsx');
    }
}
