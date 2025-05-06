<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 08:23:57 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Exports\Ordering\OrderTransactionTemplateExport;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadOrderTransactionsTemplate
{
    use AsAction;
    use WithAttributes;

    public function handle(): BinaryFileResponse
    {
        return Excel::download(new OrderTransactionTemplateExport(), 'order_transaction_templates.xlsx');
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, Order $order): BinaryFileResponse
    {
        return $this->handle();
    }
}
