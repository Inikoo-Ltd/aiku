<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Actions\RetinaAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadProduct extends RetinaAction
{
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function handle(Shop|ProductCategory $parent, string $type): BinaryFileResponse|Response
    {
        $filename =  'portfolio' . '_' . now()->format('Ymd');

        if ($type == 'portfolio_images') {
            $filename .= '_images.zip';
            return response()->streamDownload(function () use ($parent) {
                ProductZipExport::make()->handle($parent);
            }, $filename);
        } elseif ($type == 'portfolio_xlsx') {
            // $filename .= '.xlsx';
            // return Excel::download(new PortfoliosCsvOrExcelExport($customer, $shop), $filename, null, [
            //     'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            //     'Cache-Control' => 'max-age=0',
            // ]);
        }

        // $filename .= '.csv';
        // return Excel::download(new PortfoliosCsvOrExcelExport($customer, $shop), $filename, null, [
        //     'Content-Type' => 'text/csv',
        //     'Cache-Control' => 'max-age=0',
        // ]);
    }
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */

    public function asController($shop, ActionRequest $request): BinaryFileResponse|Response
    {
        $customer = $request->user()->customer;

        $type = $request->query('type', 'portfolio_csv');

        if (!in_array($type, ['portfolio_csv', 'portfolio_xlsx', 'portfolio_json', 'portfolio_images'])) {
            abort(404);
        }

        $this->initialisation($request);

        return $this->handle($customer, $shop, $type);
    }
}
