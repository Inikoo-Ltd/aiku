<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\CRM\Customer\ImportCustomers;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\ImportBulkPortfolios;
use App\Actions\HumanResources\Employee\ImportEmployees;
use App\Actions\Production\Artefact\ImportArtefact;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\RawMaterial\ImportRawMaterial;
use App\Actions\SysAdmin\Guest\ImportGuests;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Helpers\Upload;
use App\Models\HumanResources\JobPosition;
use App\Models\Production\Production;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group = $this->organisation->group;
    setPermissionsTeamId($this->group->id);
    actingAs($this->user);

    $production = Production::first();
    if (!$production) {
        $production = StoreProduction::make()->action(
            $this->organisation,
            ['code' => 'IMPPROD', 'name' => 'Import Production']
        );
    }
    $this->production = $production;
});

function csvUpload(string $name, array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return new UploadedFile($path, $name, 'text/csv', null, true);
}

test('import raw materials from file', function () {
    Storage::fake('local');

    $file = csvUpload('raw-materials.csv', [
        ['type', 'state', 'code', 'description', 'unit', 'unit_cost'],
        ['consumable', 'orphan', 'IMPRM1', 'Imported raw material', 'unit', '3.5'],
    ]);

    $upload = ImportRawMaterial::make()->handle($this->production, $file);

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->filename)->not->toBeNull()
        ->and($upload->original_filename)->toBe('raw-materials.csv')
        ->and($upload->group_id)->toBe($this->group->id)
        ->and($upload->organisation_id)->toBe($this->organisation->id)
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0);
});

test('import artefacts from file', function () {
    Storage::fake('local');

    $file = csvUpload('artefacts.csv', [
        ['code', 'name'],
        ['IMPART1', 'Imported artefact'],
    ]);

    $upload = ImportArtefact::make()->handle($this->production, $file);

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->filename)->not->toBeNull()
        ->and($upload->original_filename)->toBe('artefacts.csv')
        ->and($upload->organisation_id)->toBe($this->organisation->id)
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0);
});

test('import customers from file', function () {
    Storage::fake('local');

    $file = csvUpload('customers.csv', [
        ['shop', 'name', 'company', 'email', 'phone'],
        [$this->shop->slug, 'Imported Customer', 'Imported Co', 'imported.customer@example.com', ''],
    ]);

    $upload = ImportCustomers::make()->handle($this->group, $file);

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->filename)->not->toBeNull()
        ->and($upload->original_filename)->toBe('customers.csv')
        ->and($upload->group_id)->toBe($this->group->id)
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0);
});

test('import employees from file', function () {
    Storage::fake('local');

    $position = JobPosition::where('organisation_id', $this->organisation->id)->firstOrFail();

    $file = csvUpload('employees.csv', [
        ['worker_number', 'alias', 'name', 'job_title', 'type', 'employment_type', 'work_email', 'starting_date', 'positions'],
        ['IMPEMP1', 'impe', 'Imported Employee', 'Tester', 'employee', 'full-time', 'imported.employee@example.com', '01-Jan-25', $position->slug],
    ]);

    $upload = ImportEmployees::make()->handle($this->organisation, $file);

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->filename)->not->toBeNull()
        ->and($upload->original_filename)->toBe('employees.csv')
        ->and($upload->organisation_id)->toBe($this->organisation->id)
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0);
});

test('import guests from file', function () {
    Storage::fake('local');

    $position = JobPosition::where('group_id', $this->group->id)->where('scope', 'group')->firstOrFail();

    $file = csvUpload('guests.csv', [
        ['type', 'username', 'password', 'name', 'email', 'positions'],
        ['contractor', 'imported-guest', 'secret-password', 'Imported Guest', 'imported.guest@example.com', $position->slug],
    ]);

    $upload = ImportGuests::make()->handle($this->group, $file);

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->filename)->not->toBeNull()
        ->and($upload->original_filename)->toBe('guests.csv')
        ->and($upload->group_id)->toBe($this->group->id)
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0);
});

test('import portfolios in customer sales channel from file, unknown sku fails its own row only', function () {
    Storage::fake('local');

    $customer = createCustomer($this->shop);

    list(, $product) = createProduct($this->shop);

    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->firstOrFail();

    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $customer,
        $platform,
        ['reference' => 'test_portfolio_import_reference']
    );

    $file = csvUpload('portfolios.csv', [
        ['Sku', 'Title'],
        [$product->code, $product->name],
        ['SKU-DOES-NOT-EXIST', 'Typo made by the client'],
    ]);

    $upload = ImportBulkPortfolios::make()->handle($customerSalesChannel, $file);

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->original_filename)->toBe('portfolios.csv')
        ->and($upload->organisation_id)->toBe($this->organisation->id)
        ->and($upload->number_rows)->toBe(2)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(1)
        ->and($customerSalesChannel->portfolios()->count())->toBe(1)
        ->and($customerSalesChannel->portfolios()->first()->item_id)->toBe($product->id)
        ->and($upload->records()->where('status', UploadRecordStatusEnum::FAILED)->first()->errors)
        ->toContain('SKU not found in this shop.');
});

test('import portfolios reports a sku belonging to another shop instead of a null error', function () {
    Storage::fake('local');

    $customer = createCustomer($this->shop);

    $otherShop = createOwnShop('portfolio-import-foreign-shop')[2];
    list(, $foreignProduct) = createProduct($otherShop);

    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->firstOrFail();

    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $customer,
        $platform,
        ['reference' => 'test_foreign_sku_reference']
    );

    $file = csvUpload('foreign-portfolios.csv', [
        ['Sku', 'Title'],
        [$foreignProduct->code, $foreignProduct->name],
    ]);

    $upload = ImportBulkPortfolios::make()->handle($customerSalesChannel, $file);

    $record = $upload->records()->where('status', UploadRecordStatusEnum::FAILED)->first();

    expect($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(1)
        ->and($customerSalesChannel->portfolios()->count())->toBe(0)
        ->and($record->errors)->toContain('SKU not found in this shop.');
});

test('bulk import portfolios route is authorised for a crm user', function () {
    Storage::fake('local');

    $customer = createCustomer($this->shop);

    list(, $product) = createProduct($this->shop);

    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->firstOrFail();

    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $customer,
        $platform,
        ['reference' => 'test_portfolio_import_route_reference']
    );

    $file = csvUpload('portfolios-route.csv', [
        ['Sku', 'Title'],
        [$product->code, $product->name],
    ]);

    $response = actingAs($this->user)->post(
        route('grp.models.customer_sales_channel.portfolios.bulk_import', $customerSalesChannel->id),
        ['file' => $file]
    );

    $response->assertStatus(201);
    expect($customerSalesChannel->portfolios()->count())->toBe(1);
});
