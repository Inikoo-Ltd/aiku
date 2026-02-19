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
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Exports\Marketing\DataFeedsMapping;
use App\Helpers\NaturalLanguage;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadPortfoliosCSV extends RetinaAction
{
    use DataFeedsMapping;

    public function handle(
        CustomerSalesChannel $customerSalesChannel,
        string $exportType = 'portfolio_csv',
        mixed $columns = null,
        mixed $productStates = null,
        array $productAvailibility = []
    ): BinaryFileResponse|Response|string {

        $filename = 'portfolio_data_feed_' . $customerSalesChannel->customer->slug . '_' . now()->format('Ymd') . '.csv';

        $isExtendedProperties = $exportType === 'portfolio_csv_extended_properties';
        $headers = $isExtendedProperties ? $this->headingsExtendedProperties($columns) : $this->headings();
        if (!$isExtendedProperties) {
            $referenceHeader = 'Product user reference';
            array_splice($headers, 2, 0, [$referenceHeader]);
        }

        $csvData[] = $headers;

        $normalizedProductStates = $this->normalizeProductStates($productStates);

        $portfolios = DB::table('portfolios')
            ->select('products.*', 'product_categories.name as family_name', 'portfolios.reference')
            ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.family_id', '=', 'product_categories.id')
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('portfolios.status', true)
            ->when(count($normalizedProductStates) > 0, function ($query) use ($normalizedProductStates) {
                $query->whereIn('products.state', $normalizedProductStates);
            });

        if (in_array('exclude_not_for_sale', $productAvailibility)) {
            $portfolios
                ->where('products.is_for_sale', true);
        }

        if (in_array('exclude_out_of_stocks', $productAvailibility)) {
            $portfolios
                ->where('products.available_quantity', '>', 0);
        }

        $portfolios
            ->orderBy('portfolios.id')
            ->chunk(100, function ($products) use (&$csvData, $isExtendedProperties, $columns) {
                foreach ($products as $row) {
                    if ($isExtendedProperties) {
                        $csvData[] = $this->mapExtendedProperties($row, $columns);
                    } else {
                        $mappedData = $this->map($row);
                        array_splice($mappedData, 2, 0, [$row->reference ?? '']);
                        $csvData[] = $mappedData;
                    }
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

        if ($exportType === 'csv_content') {
            $content = file_get_contents($tempFile);
            unlink($tempFile);
            return $content;
        }

        return response()->download($tempFile, $filename, [
            'Content-Type'  => 'text/csv',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend();
    }

    public function headingsExtendedProperties(mixed $columns = null): array
    {
        $headings = $this->extendedPropertiesHeadingMap();
        $keys = $this->filterExtendedColumns($columns, array_keys($headings));

        return array_map(fn ($key) => $headings[$key], $keys);
    }

    public function mapExtendedProperties($row, mixed $columns = null): array
    {
        $values = $this->extendedPropertiesValueMap($row);
        $keys = $this->filterExtendedColumns($columns, array_keys($values));

        return array_map(fn ($key) => $values[$key], $keys);
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

        $exportType = (string)$request->get('type', 'portfolio_csv');

        $columns = $request->get('columns');
        $productStates = $request->get('product_states');
        $productAvailibility = $request->get('product_availibility') ?? [];

        return $this->handle($customerSalesChannel, $exportType, $columns, $productStates, $productAvailibility);
    }

    private function extendedPropertiesHeadingMap(): array
    {
        return [
            'product_code' => 'Product code',
            'product_user_reference' => 'Product user reference',
            'product_name' => 'Product name',
            'materials_ingredients' => 'Materials/Ingredients',
            'unit_dimensions' => 'Unit dimensions',
            'unit_net_weight' => 'Unit net weight (kg)',
            'package_weight_shipping' => 'Package weight (shipping)',
            'country_of_origin' => 'Country of origin',
            'tariff_code' => 'Tariff code',
            'duty_rate' => 'Duty rate',
            'hts_us' => 'HTS US',
            'data_updated' => 'Data updated',
        ];
    }

    private function extendedPropertiesValueMap(object $row): array
    {
        $dimensions = NaturalLanguage::make()->dimensions($row->marketing_dimensions);

        return [
            'product_code' => $row->code,
            'product_user_reference' => $row->reference ?? '',
            'product_name' => $row->name,
            'materials_ingredients' => $row->marketing_ingredients ?? '',
            'unit_dimensions' => $dimensions,
            'unit_net_weight' => $row->marketing_weight / 1000,
            'package_weight_shipping' => $row->gross_weight / 1000,
            'country_of_origin' => $row->country_of_origin ?? '',
            'tariff_code' => $row->tariff_code ?? '',
            'duty_rate' => $row->duty_rate ?? '',
            'hts_us' => $row->hts_us ?? '',
            'data_updated' => $row->updated_at,
        ];
    }

    private function filterExtendedColumns(mixed $columns, array $available): array
    {
        $requested = $this->normalizeColumns($columns);

        if (count($requested) === 0) {
            return $available;
        }

        return array_values(array_intersect($available, $requested));
    }

    private function normalizeColumns(mixed $columns): array
    {
        if (is_string($columns)) {
            $columns = array_filter(array_map('trim', explode(',', $columns)));
        }

        if (!is_array($columns)) {
            return [];
        }

        $columns = array_filter($columns, fn ($value) => is_string($value) && $value !== '');

        return array_values(array_unique($columns));
    }

    public function normalizeProductStates(mixed $productStates): array
    {
        if (is_string($productStates)) {
            $productStates = array_filter(array_map('trim', explode(',', $productStates)));
        }

        if (!is_array($productStates)) {
            return [];
        }

        $productStates = array_filter($productStates, fn ($value) => is_string($value) && $value !== '');
        $productStates = array_values(array_unique($productStates));

        return array_values(array_intersect(ProductStateEnum::values(), $productStates));
    }
}
