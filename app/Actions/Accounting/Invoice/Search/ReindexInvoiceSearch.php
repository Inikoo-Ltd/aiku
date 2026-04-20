<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:46:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\Search;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Accounting\Invoice;

class ReindexInvoiceSearch
{
    use WithHydrateCommand;
    private string $model;
    public string $commandSignature = 'search:invoices {organisations?*} {--s|slugs=} {--S|shop=}';

    public function __construct()
    {
        $this->model = Invoice::class;
    }

    public function handle(Invoice $invoice): void
    {

    }

}
