<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 11 May 2026 19:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\Traits;

use App\Models\Accounting\Invoice;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

trait WithDeleteInvoiceUI
{
    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->set('deleted_by', $request->user()->id);
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }

    public function action(Invoice $invoice, array $modelData): Invoice
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $this->validatedData);
    }

    public function asCommand(Command $command): int
    {
        $this->asAction = true;

        try {
            /** @var Invoice $invoice */
            $invoice = Invoice::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $modelData = [];

        if ($command->option('deleted_note')) {
            $modelData['deleted_note'] = $command->option('deleted_note');
        }
        if ($command->option('deleted_by')) {
            $modelData['deleted_by'] = $command->option('deleted_by');
        }

        $this->action($invoice, $modelData);

        return 0;
    }
}
