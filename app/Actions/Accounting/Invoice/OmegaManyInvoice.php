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

class OmegaManyInvoice extends OrgAction
{
    public function handle(Organisation $organisation, array $modelData, ?Shop $shop = null): Response
    {
        $filter   = Arr::pull($modelData, 'filter', 'all');
        $filename = 'omega-invoice-'.$filter.'.txt';

        $query = Invoice::where('organisation_id', $organisation->id);

        if ($filter != 'all') {
            [$start, $end] = explode('-', $filter);

            $start = trim($start).' 00:00:00';
            $end   = trim($end).' 23:59:59';
            $start = Carbon::createFromFormat('Ymd H:i:s', $start)->format('Y-m-d H:i:s');
            $end   = Carbon::createFromFormat('Ymd H:i:s', $end)->format('Y-m-d H:i:s');

            $query->whereBetween('date', [$start, $end]);
        }

        $type   = Arr::pull($modelData, 'type', 'invoice');
        $bucket = Arr::pull($modelData, 'bucket', 'all');

        $query = $query->where('type', $type);

        if ($type != 'refund' && $bucket != 'all') {
            $query->where('pay_status', InvoicePayStatusEnum::from($bucket));
        }

        if ($shop && $shop->id) {
            $query->where('shop_id', $shop->id);
        }


        set_time_limit(0);
        ini_set('max_execution_time', 0);

        return response()->stream(
            function () use ($query) {
                $page    = 1;
                $perPage = 100;
                do {
                    $chunk = $query->forPage($page, $perPage)->get();
                    foreach ($chunk as $invoice) {
                        echo OmegaInvoice::run($invoice);
                        ob_flush();
                        flush();
                    }
                    $page++;
                } while ($chunk->isNotEmpty());
            },
            200,
            [
                'Content-Type'        => 'text/plain',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Accel-Buffering'   => 'no'
            ]
        );
    }


    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);
        $modelData = $this->validatedData;

        return $this->handle(organisation: $organisation, shop: $shop, modelData: $modelData);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        $modelData = $this->validatedData;

        return $this->handle(organisation: $organisation, shop: null, modelData: $modelData);
    }

    public function rules(): array
    {
        return [
            'filter' => 'string',
            'bucket' => 'string',
            'type'   => 'string',
        ];
    }

}
