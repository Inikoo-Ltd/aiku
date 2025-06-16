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
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadPortfolios extends RetinaAction
{
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function handle(Customer $customer, CustomerSalesChannel $customerSalesChannel, string $type): BinaryFileResponse|Response
    {
        $filename =  'portfolio' . '_' . now()->format('Ymd');

        if ($type == 'portfolio_json') {
            $filename .= '_json.txt';

            return response()->streamDownload(function () use ($customer, $customerSalesChannel) {
                $jsonData = PortfoliosJsonExport::make()->handle($customer, $customerSalesChannel);
                echo json_encode($jsonData, JSON_PRETTY_PRINT);
            }, $filename, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'max-age=0',
            ]);
        } elseif ($type == 'portfolio_images') {
            $filename .= '_images.zip';

            [$zipPath, $zipFilePathRelative] = PortfoliosZipExport::make()->handle($customer, $customerSalesChannel);
            return response()->streamDownload(function () use ($zipPath, $zipFilePathRelative) {
                readfile($zipPath);
                Storage::disk('local')->delete($zipFilePathRelative);
            }, $filename, [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'max-age=0',
            ]);
        } elseif ($type == 'portfolio_xlsx') {
            $filename .= '.xlsx';
            return Excel::download(new PortfoliosCsvOrExcelExport($customer, $customerSalesChannel), $filename, null, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]);
        }

        $filename .= '.csv';
        return Excel::download(new PortfoliosCsvOrExcelExport($customer, $customerSalesChannel), $filename, null, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'max-age=0',
        ]);
    }
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): BinaryFileResponse|Response
    {
        $customer = $request->user()->customer;

        $type = $request->query('type', 'portfolio_csv');

        if (!in_array($type, ['portfolio_csv', 'portfolio_xlsx', 'portfolio_json', 'portfolio_images'])) {
            abort(404);
        }

        $this->initialisation($request);

        return $this->handle($customer, $customerSalesChannel, $type);
    }
}
