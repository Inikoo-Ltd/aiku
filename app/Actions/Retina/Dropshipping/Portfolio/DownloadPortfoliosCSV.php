<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Exports\Marketing\DataFeedsMapping;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadPortfoliosCSV extends RetinaAction
{
    use DataFeedsMapping;

    public function handle(CustomerSalesChannel $customerSalesChannel): BinaryFileResponse|Response
    {
        $filename = 'portfolio'.'_'.now()->format('Ymd').'.csv';

        $headers = $this->headings();

        $referenceHeader = 'Product user reference';

        array_splice($headers, 2, 0, [$referenceHeader]);

        $csvData[] = $headers;

        DB::table('portfolios')
            ->select('products.*', 'product_categories.name as family_name', 'portfolios.reference')
            ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.family_id', '=', 'product_categories.id')
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('portfolios.status', true)
            ->orderBy('portfolios.id')
            ->chunk(100, function ($products) use (&$csvData) {
                foreach ($products as $row) {
                    $mappedData = $this->map($row);
                    array_splice($mappedData, 2, 0, [$row->reference ?? '']);

                    $csvData[] = $mappedData;
                }
            });


        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'csv');
        $file     = fopen($tempFile, 'w');

        // Write CSV data to the file
        foreach ($csvData as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        // Return the file as a download response
        return response()->download($tempFile, $filename, [
            'Content-Type'  => 'text/csv',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend();
    }


    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): BinaryFileResponse|Response
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }
}
