<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OmegaManyInvoice extends OrgAction
{
    public array $invoices = [];
    public function handle(): string
    {
        $invoices = $this->invoices;

        $text = '';
        foreach ($invoices as $invoice) {
            $text .= OmegaInvoice::run($invoice) . "\n";
        }

        return $text;
    }


    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        $modelData = $this->validatedData;

        $omegaText = $this->handle();

        $filter  = Arr::pull($modelData, 'filter', []);

        $filename = 'omega-invoice-'. $filter .'.txt';

        return response($omegaText, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        $modelData = $this->validatedData;

        $omegaText = $this->handle();

        $filter  = Arr::pull($modelData, 'filter', []);


        $filename = 'omega-invoice-'. $filter .'.txt';

        return response($omegaText, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function rules(): array
    {
        return [
            'filter' => 'required|string',
            'bucket' => 'string',
            'type'   => 'required|string',
        ];
    }

    public function afterValidator(): void
    {

        $filter = $this->get("filter");

        if (!str_contains($filter, '-')) {
            throw ValidationException::withMessages(
                [
                    'message' => [
                        'filter' => 'The filter must be in the format YYYYMMDD-YYYYMMDD',
                    ]
                ]
            );
        }

        [$start, $end] = explode('-', $filter);

        $start = trim($start).' 00:00:00';
        $end   = trim($end).' 23:59:59';
        $start = Carbon::createFromFormat('Ymd H:i:s', $start)->format('Y-m-d H:i:s');
        $end   = Carbon::createFromFormat('Ymd H:i:s', $end)->format('Y-m-d H:i:s');

        $type = $this->get("type", 'invoice');
        $invoices = [];
        $query = Invoice::where('organisation_id', $this->organisation->id)
            ->whereBetween('date', [$start, $end])
            ->where('type', $type);

        if ($type != 'refund' && $this->get('bucket') !== 'all') {
            $query->where('pay_status', InvoicePayStatusEnum::from($this->get('bucket')));
        }

        if (isset($this->shop) && $this->shop->id) {
            $query->where('shop_id', $this->shop->id);
        }

        $query->chunk(100, function ($chunk) use (&$invoices) {
            foreach ($chunk as $invoice) {
                $invoices[] = $invoice;
            }
        });

        if (count($invoices) > 3000) {
            throw ValidationException::withMessages(
                [
                    'message' => [
                        'amount' => 'The number of invoices is too large, please reduce the date range',
                    ]
                ]
            );
        }

        $this->invoices = $invoices;

    }

}
