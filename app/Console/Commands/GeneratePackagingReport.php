<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GeneratePackagingReport extends Command
{
    protected $signature = 'packaging:report
                            {--start-date= : Start date (Y-m-d format)}
                            {--end-date= : End date (Y-m-d format)}
                            {--output-dir=packaging-reports : Output directory}
                            {--organisation= : Organisation slug (optional)}';

    protected $description = 'Generate packaging reports for Ancient Wisdom (buy_from_uk, imports, sales_uk, exports)';

    public function handle(): int
    {
        $startDate = $this->option('start-date') ?? '2025-07-01';
        $endDate = $this->option('end-date') ?? '2025-12-31';
        $outputDir = $this->option('output-dir');
        $organisationSlug = $this->option('organisation');

        $this->info("Generating packaging reports for period: {$startDate} to {$endDate}");

        if (!Storage::exists($outputDir)) {
            Storage::makeDirectory($outputDir);
        }

        $this->generateBuyFromUk($startDate, $endDate, $outputDir, $organisationSlug);
        $this->generateExports($startDate, $endDate, $outputDir, $organisationSlug);
        $this->generateSalesUk($startDate, $endDate, $outputDir, $organisationSlug);
        $this->generateImports($startDate, $endDate, $outputDir, $organisationSlug);

        $this->info('All packaging reports generated successfully!');
        $this->info("Output directory: " . storage_path("app/{$outputDir}"));

        return Command::SUCCESS;
    }

    protected function generateBuyFromUk(string $startDate, string $endDate, string $outputDir, ?string $organisationSlug): void
    {
        $this->info('Generating buy_from_uk.csv...');

        $query = DB::table('org_stock_movements as osm')
            ->join('org_stocks as os', 'osm.org_stock_id', '=', 'os.id')
            ->join('stocks as s', 'os.stock_id', '=', 's.id')
            ->leftJoin('stock_families as sf', 's.stock_family_id', '=', 'sf.id')
            ->leftJoin('org_stock_has_org_supplier_products as ososp', 'os.id', '=', 'ososp.org_stock_id')
            ->leftJoin('org_supplier_products as osp', 'ososp.org_supplier_product_id', '=', 'osp.id')
            ->leftJoin('org_suppliers as osup', 'osp.org_supplier_id', '=', 'osup.id')
            ->leftJoin('suppliers as sup', 'osup.supplier_id', '=', 'sup.id')
            ->leftJoin('addresses as addr', 'sup.address_id', '=', 'addr.id')
            ->leftJoin('countries as c', 'addr.country_id', '=', 'c.id')
            ->select(
                's.code as Part Reference',
                DB::raw('ROUND(COALESCE(s.gross_weight / 1000.0, 0), 2) as "Part Package Weight"'),
                'sf.code as Category Code',
                DB::raw('SUM(osm.quantity) as total_quantity'),
                DB::raw('ROUND(SUM(osm.quantity * COALESCE(s.gross_weight / 1000.0, 0)), 2) as total_weight'),
                DB::raw('STRING_AGG(DISTINCT c.code, \',\') as countries')
            )
            ->where('osm.date', '>=', $startDate . ' 00:00:00')
            ->where('osm.date', '<=', $endDate . ' 23:59:59')
            ->where('osm.flow', '=', 'in')
            ->where('osm.type', '=', 'purchase')
            ->where('c.code', '=', 'GB')
            ->groupBy('s.id', 's.code', 's.gross_weight', 'sf.code');

        if ($organisationSlug) {
            $query->join('organisations as org', 'osm.organisation_id', '=', 'org.id')
                ->where('org.slug', '=', $organisationSlug);
        }

        $results = $query->get();

        $this->writeCsv($outputDir . '/buy_from_uk.csv', $results);
        $this->info("✓ buy_from_uk.csv generated with " . count($results) . " records");
    }

    protected function generateExports(string $startDate, string $endDate, string $outputDir, ?string $organisationSlug): void
    {
        $this->info('Generating exports.csv...');

        $query = DB::table('org_stock_movements as osm')
            ->join('org_stocks as os', 'osm.org_stock_id', '=', 'os.id')
            ->join('stocks as s', 'os.stock_id', '=', 's.id')
            ->leftJoin('stock_families as sf', 's.stock_family_id', '=', 'sf.id')
            ->leftJoin('delivery_note_items as dni', function ($join) {
                $join->on('osm.operation_id', '=', 'dni.id')
                    ->where('osm.operation_type', '=', 'DeliveryNoteItem');
            })
            ->leftJoin('delivery_notes as dn', 'dni.delivery_note_id', '=', 'dn.id')
            ->leftJoin('countries as c', 'dn.delivery_country_id', '=', 'c.id')
            ->select(
                's.code as Part Reference',
                DB::raw('ROUND(COALESCE(s.gross_weight / 1000.0, 0), 2) as "Part Package Weight"'),
                'sf.code as Category Code',
                DB::raw('SUM(ABS(osm.quantity)) as total_quantity'),
                DB::raw('ROUND(SUM(ABS(osm.quantity) * COALESCE(s.gross_weight / 1000.0, 0)), 2) as total_weight'),
                DB::raw('STRING_AGG(DISTINCT c.code, \',\') as countries')
            )
            ->where('osm.date', '>=', $startDate . ' 00:00:00')
            ->where('osm.date', '<=', $endDate . ' 23:59:59')
            ->where('osm.flow', '=', 'out')
            ->where('osm.type', '=', 'picked')
            ->where('c.code', '!=', 'GB')
            ->whereNotNull('c.code')
            ->groupBy('s.id', 's.code', 's.gross_weight', 'sf.code');

        if ($organisationSlug) {
            $query->join('organisations as org', 'osm.organisation_id', '=', 'org.id')
                ->where('org.slug', '=', $organisationSlug);
        }

        $results = $query->get();

        $this->writeCsv($outputDir . '/exports.csv', $results);
        $this->info("✓ exports.csv generated with " . count($results) . " records");
    }

    protected function generateSalesUk(string $startDate, string $endDate, string $outputDir, ?string $organisationSlug): void
    {
        $this->info('Generating sales_uk.csv...');

        $query = DB::table('org_stock_movements as osm')
            ->join('org_stocks as os', 'osm.org_stock_id', '=', 'os.id')
            ->join('stocks as s', 'os.stock_id', '=', 's.id')
            ->leftJoin('stock_families as sf', 's.stock_family_id', '=', 'sf.id')
            ->leftJoin('delivery_note_items as dni', function ($join) {
                $join->on('osm.operation_id', '=', 'dni.id')
                    ->where('osm.operation_type', '=', 'DeliveryNoteItem');
            })
            ->leftJoin('delivery_notes as dn', 'dni.delivery_note_id', '=', 'dn.id')
            ->leftJoin('countries as c', 'dn.delivery_country_id', '=', 'c.id')
            ->select(
                's.code as Part Reference',
                DB::raw('ROUND(COALESCE(s.gross_weight / 1000.0, 0), 2) as "Part Package Weight"'),
                'sf.code as Category Code',
                DB::raw('SUM(ABS(osm.quantity)) as total_quantity'),
                DB::raw('ROUND(SUM(ABS(osm.quantity) * COALESCE(s.gross_weight / 1000.0, 0)), 2) as total_weight'),
                DB::raw('STRING_AGG(DISTINCT c.code, \',\') as countries')
            )
            ->where('osm.date', '>=', $startDate . ' 00:00:00')
            ->where('osm.date', '<=', $endDate . ' 23:59:59')
            ->where('osm.flow', '=', 'out')
            ->where('osm.type', '=', 'picked')
            ->where('c.code', '=', 'GB')
            ->groupBy('s.id', 's.code', 's.gross_weight', 'sf.code');

        if ($organisationSlug) {
            $query->join('organisations as org', 'osm.organisation_id', '=', 'org.id')
                ->where('org.slug', '=', $organisationSlug);
        }

        $results = $query->get();

        $this->writeCsv($outputDir . '/sales_uk.csv', $results);
        $this->info("✓ sales_uk.csv generated with " . count($results) . " records");
    }

    protected function generateImports(string $startDate, string $endDate, string $outputDir, ?string $organisationSlug): void
    {
        $this->info('Generating imports.csv...');

        $query = DB::table('org_stock_movements as osm')
            ->join('org_stocks as os', 'osm.org_stock_id', '=', 'os.id')
            ->join('stocks as s', 'os.stock_id', '=', 's.id')
            ->leftJoin('stock_families as sf', 's.stock_family_id', '=', 'sf.id')
            ->leftJoin('org_stock_has_org_supplier_products as ososp', 'os.id', '=', 'ososp.org_stock_id')
            ->leftJoin('org_supplier_products as osp', 'ososp.org_supplier_product_id', '=', 'osp.id')
            ->leftJoin('org_suppliers as osup', 'osp.org_supplier_id', '=', 'osup.id')
            ->leftJoin('suppliers as sup', 'osup.supplier_id', '=', 'sup.id')
            ->leftJoin('addresses as addr', 'sup.address_id', '=', 'addr.id')
            ->leftJoin('countries as c', 'addr.country_id', '=', 'c.id')
            ->select(
                's.code as Part Reference',
                DB::raw('ROUND(COALESCE(s.gross_weight / 1000.0, 0), 2) as "Part Package Weight"'),
                'sf.code as Category Code',
                DB::raw('SUM(osm.quantity) as total_quantity'),
                DB::raw('ROUND(SUM(osm.quantity * COALESCE(s.gross_weight / 1000.0, 0)), 2) as total_weight'),
                DB::raw('STRING_AGG(DISTINCT c.code, \',\') as countries')
            )
            ->where('osm.date', '>=', $startDate . ' 00:00:00')
            ->where('osm.date', '<=', $endDate . ' 23:59:59')
            ->where('osm.flow', '=', 'in')
            ->where('osm.type', '=', 'purchase')
            ->where('c.code', '!=', 'GB')
            ->whereNotNull('c.code')
            ->groupBy('s.id', 's.code', 's.gross_weight', 'sf.code');

        if ($organisationSlug) {
            $query->join('organisations as org', 'osm.organisation_id', '=', 'org.id')
                ->where('org.slug', '=', $organisationSlug);
        }

        $results = $query->get();

        $this->writeCsv($outputDir . '/imports.csv', $results);
        $this->info("✓ imports.csv generated with " . count($results) . " records");
    }

    protected function writeCsv(string $filename, $data): void
    {
        $handle = fopen(storage_path("app/{$filename}"), 'w');

        if (count($data) > 0) {
            $firstRow = (array) $data[0];
            fputcsv($handle, array_keys($firstRow));

            foreach ($data as $row) {
                fputcsv($handle, (array) $row);
            }
        } else {
            fputcsv($handle, ['Part Reference', 'Part Package Weight', 'Category Code', 'total_quantity', 'total_weight', 'countries']);
        }

        fclose($handle);
    }
}
