<?php

/*
 * Implement a Portfolio CSV Test
 * Author: Oggie Sutrisna 👌
 * Created: Tue, 03 Feb 2026 00:00:00 UTC
 */

namespace Tests\Unit\Retina\Dropshipping;

use App\Actions\Retina\Dropshipping\Portfolio\DownloadPortfoliosCSV;
use PHPUnit\Framework\TestCase;

class DownloadPortfoliosCsvTest extends TestCase
{
    public function testHeadingsExtendedProperties(): void
    {
        $action = new DownloadPortfoliosCSV();

        $this->assertSame([
            'Product code',
            'Product user reference',
            'Product name',
            'Materials/Ingredients',
            'Unit dimensions',
            'Unit net weight',
            'Package weight (shipping)',
            'Country of origin',
            'Tariff code',
            'Duty rate',
            'HTS US',
            'Data updated',
        ], $action->headingsExtendedProperties());
    }

    public function testHeadingsExtendedPropertiesWithSelectedColumns(): void
    {
        $action = new DownloadPortfoliosCSV();

        $this->assertSame([
            'Product code',
            'Product name',
            'Country of origin',
        ], $action->headingsExtendedProperties(['product_name', 'product_code', 'country_of_origin']));
    }

    public function testMapExtendedProperties(): void
    {
        $action = new DownloadPortfoliosCSV();

        $row = (object)[
            'code' => 'P-1001',
            'reference' => 'REF-001',
            'name' => 'Sample Product',
            'marketing_ingredients' => 'Cotton',
            'marketing_dimensions' => null,
            'marketing_weight' => 1500,
            'gross_weight' => 2000,
            'country_of_origin' => 'GB',
            'tariff_code' => '1234',
            'duty_rate' => '5',
            'hts_us' => '9876',
            'updated_at' => '2026-02-03 10:15:00',
            'product_state' => 'discontinued',
        ];

        $this->assertEquals([
            'P-1001',
            'REF-001',
            'Sample Product',
            'Cotton',
            '',
            1.5,
            2.0,
            'GB',
            '1234',
            '5',
            '9876',
            '2026-02-03 10:15:00',
        ], $action->mapExtendedProperties($row));
    }

    public function testMapExtendedPropertiesWithSelectedColumns(): void
    {
        $action = new DownloadPortfoliosCSV();

        $row = (object)[
            'code' => 'P-1001',
            'reference' => 'REF-001',
            'name' => 'Sample Product',
            'marketing_ingredients' => 'Cotton',
            'marketing_dimensions' => null,
            'marketing_weight' => 1500,
            'gross_weight' => 2000,
            'country_of_origin' => 'GB',
            'tariff_code' => '1234',
            'duty_rate' => '5',
            'hts_us' => '9876',
            'updated_at' => '2026-02-03 10:15:00',
        ];

        $this->assertEquals([
            'P-1001',
            'Sample Product',
            'GB',
        ], $action->mapExtendedProperties($row, ['product_name', 'product_code', 'country_of_origin']));
    }

    public function testNormalizeProductStates(): void
    {
        $action = new DownloadPortfoliosCSV();

        $this->assertSame([
            'in_process',
            'active',
        ], $action->normalizeProductStates(['active', 'in_process', 'active', '', 'unknown']));
    }
}
