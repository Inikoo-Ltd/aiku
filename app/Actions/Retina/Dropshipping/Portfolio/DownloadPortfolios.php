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
use App\Models\Dropshipping\Platform;
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
     */
    public function handle(Customer $customer, Platform $platform, string $type): BinaryFileResponse|Response
    {
        $filename = 'portofolio_' . now()->format('Ymd');

        if ($type == 'portfolio_json') {
            $filename .= '_json.txt';

            return response()->streamDownload(function () use ($customer, $platform) {
                $jsonData = PortfoliosJsonExport::make()->handle($customer, $platform);
                echo json_encode($jsonData, JSON_PRETTY_PRINT);
            }, $filename, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'max-age=0',
            ]);
        } elseif ($type == 'portfolio_images') {
            $filename .= '_images.zip';

            [$zipPath, $zipFilePathRelative] = PortfoliosZipExport::make()->handle($customer, $platform);
            return response()->streamDownload(function () use ($zipPath, $zipFilePathRelative) {
                readfile($zipPath);
                Storage::disk('local')->delete($zipFilePathRelative);
            }, $filename, [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'max-age=0',
            ]);
        }

        $filename .= '.csv';
        return Excel::download(new PortfoliosCsvExport($customer, $platform), $filename, null, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'max-age=0',
        ]);
    }
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */

    public function asController(Platform $platform, ActionRequest $request): BinaryFileResponse|Response
    {
        // dd("test", $platform);
        $customer = $request->user()->customer;

        $type = $request->query('type', 'portfolio_csv');

        if (!in_array($type, ['portfolio_csv', 'portfolio_json', 'portfolio_images'])) {
            abort(404);
        }

        $this->initialisation($request);

        return $this->handle($customer, $platform, $type);
    }
}
