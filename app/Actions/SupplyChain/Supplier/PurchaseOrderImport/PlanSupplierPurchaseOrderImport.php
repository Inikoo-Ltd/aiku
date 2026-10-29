<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:57
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier\PurchaseOrderImport;

use App\Actions\Procurement\OrgSupplierProducts\ResolveOrgStockForSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\Upload\CheckSupplierProductSheet;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * What importing the order sheet would do: one draft purchase order per "Order Cartons <key>"
 * column (UK, SK, ES… matched to the organisation buying from the supplier), each line in
 * cartons times the supplier product's carton. A line already on the organisation's open draft
 * takes the sheet's quantity, as the supplier product upload does. Everything that will not be
 * ordered is listed with its reason, and earlier imports of the same file are named.
 */
class PlanSupplierPurchaseOrderImport
{
    use AsObject;

    public const string UPLOAD_MODEL = 'SupplierPurchaseOrder';

    /**
     * @return array{
     *     errors: list<string>,
     *     file: array{name: string, hash: string, lines_hash: ?string},
     *     currency: ?string,
     *     organisations: list<array<string, mixed>>,
     *     skipped: list<array{row: ?int, code: string, cartons: array<string, int|string>, reason: string}>,
     *     rows: int,
     *     rows_without_cartons: int,
     *     previous_imports: list<array<string, mixed>>
     * }
     */
    public function handle(Supplier $supplier, UploadedFile $file, User $user): array
    {
        $sheet = ReadSupplierPurchaseOrderSheet::run($file->getRealPath());

        $plan = [
            'errors'               => $sheet['errors'],
            'file'                 => ['name' => $file->getClientOriginalName(), 'hash' => sha1_file($file->getRealPath()), 'lines_hash' => null],
            'currency'             => $supplier->currency?->code,
            'organisations'        => [],
            'skipped'              => [],
            'rows'                 => count($sheet['rows']),
            'rows_without_cartons' => 0,
            'previous_imports'     => [],
        ];

        if ($plan['errors'] !== []) {
            return $plan;
        }

        $supplierProducts = $this->supplierProducts($supplier, $sheet['rows']);
        $orderColumns     = $this->orderColumns($supplier, array_values(array_unique($sheet['order_columns'])), $user);

        $wanted = [];
        foreach ($sheet['rows'] as $row) {
            $code    = $row['supplier_code'] ?? $row['part_reference'] ?? '';
            $cartons = [];
            foreach ($row['order'] as $key => $cell) {
                $value = $cell['value'];
                if ($value === null || (is_numeric($value) && (float) $value == 0)) {
                    continue;
                }
                if (!is_numeric($value) || (float) $value != (int) $value || (int) $value < 0) {
                    $plan['skipped'][] = $this->skip($row['row'], $code, [$key => $cell['text']], __('":value" is not a whole number of cartons.', ['value' => $cell['text']]));

                    continue;
                }
                $cartons[$key] = (int) $value;
            }

            if ($cartons === []) {
                $plan['rows_without_cartons']++;

                continue;
            }

            $supplierProduct = $supplierProducts->get(strtolower((string) $row['supplier_code'])) ?? $supplierProducts->get(strtolower((string) $row['part_reference']));
            if (!$supplierProduct) {
                $plan['skipped'][] = $this->skip($row['row'], $code, $cartons, __(':supplier has no product with this code.', ['supplier' => $supplier->code]));

                continue;
            }

            foreach ($cartons as $key => $quantity) {
                if ($orderColumns[$key]['reason'] !== null) {
                    $orderColumns[$key]['cartons'] += $quantity;

                    continue;
                }

                $wanted[$key][$supplierProduct->id] ??= ['supplier_product' => $supplierProduct, 'cartons' => 0, 'rows' => [], 'sheet_units_per_carton' => $row['units_per_carton'], 'name' => $row['name']];
                $wanted[$key][$supplierProduct->id]['cartons'] += $quantity;
                $wanted[$key][$supplierProduct->id]['rows'][]  = $row['row'];
            }
        }

        foreach ($orderColumns as $key => $column) {
            if ($column['reason'] !== null && $column['cartons'] > 0) {
                $plan['skipped'][] = $this->skip(null, __('Order Cartons :key', ['key' => $key]), [$key => $column['cartons']], $column['reason']);
            }
        }

        $fingerprint = [];
        foreach (array_keys($orderColumns) as $key) {
            if (!isset($wanted[$key])) {
                continue;
            }
            $organisationPlan = $this->organisationPlan($orderColumns[$key]['org_supplier'], $key, $wanted[$key], $plan['skipped']);
            if ($organisationPlan['lines'] === []) {
                continue;
            }
            $plan['organisations'][] = $organisationPlan;
            foreach ($organisationPlan['lines'] as $line) {
                $fingerprint[] = $key.':'.$line['supplier_product_id'].':'.$line['cartons'];
            }
        }

        usort($plan['skipped'], fn (array $a, array $b) => ($a['row'] ?? PHP_INT_MAX) <=> ($b['row'] ?? PHP_INT_MAX));

        if ($fingerprint !== []) {
            sort($fingerprint);
            $plan['file']['lines_hash'] = sha1(implode('|', $fingerprint));
        }

        $plan['previous_imports'] = $this->previousImports($supplier, $file, $plan['file']['hash'], $plan['file']['lines_hash']);

        return $plan;
    }

    /**
     * The "Order Cartons" heading each organisation buying from the supplier is read from, as the
     * template names them (UK for the British shop), checked against the same matching the import uses.
     *
     * @return list<array{heading: string, organisation: string}>
     */
    public function orderColumnHeadings(Supplier $supplier): array
    {
        $checker = CheckSupplierProductSheet::make();

        return $supplier->orgSuppliers()->where('status', true)->orderBy('organisation_id')->with('organisation.country')->get()
            ->map(function (OrgSupplier $orgSupplier) use ($supplier, $checker) {
                $organisation = $orgSupplier->organisation;
                $key          = $organisation->country?->code === 'GB' && strtoupper($organisation->code) !== 'AROMA' ? 'UK' : $organisation->code;
                if ($checker->organisationForOrderColumn($supplier, $key)?->id !== $organisation->id) {
                    $key = $organisation->code;
                }

                return ['heading' => 'Order Cartons '.$key, 'organisation' => $organisation->code];
            })
            ->all();
    }

    /**
     * @param list<array{row: int, part_reference: ?string, supplier_code: ?string}> $rows
     *
     * @return Collection<string, SupplierProduct>
     */
    protected function supplierProducts(Supplier $supplier, array $rows): Collection
    {
        $codes = collect($rows)->flatMap(fn (array $row) => [$row['supplier_code'], $row['part_reference']])
            ->filter()
            ->map(fn ($code) => strtolower((string) $code))
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        return $supplier->supplierProducts()
            ->whereIn(DB::raw('lower(code)'), $codes->all())
            ->orderByRaw("state = 'active' desc, is_available desc")
            ->get()
            ->groupBy(fn (SupplierProduct $supplierProduct) => strtolower($supplierProduct->code))
            ->map(fn (Collection $sameCode) => $sameCode->first());
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, array{org_supplier: ?OrgSupplier, reason: ?string, cartons: int}>
     */
    protected function orderColumns(Supplier $supplier, array $keys, User $user): array
    {
        $checker = CheckSupplierProductSheet::make();
        $columns = [];
        foreach ($keys as $key) {
            $organisation = $checker->organisationForOrderColumn($supplier, $key);
            $orgSupplier  = $organisation ? $supplier->orgSuppliers()->where('organisation_id', $organisation->id)->with('organisation')->first() : null;

            $reason = match (true) {
                !$orgSupplier                                                  => __('No organisation buying from :supplier matches ":key".', ['supplier' => $supplier->code, 'key' => $key]),
                !$orgSupplier->status                                          => __(':organisation no longer buys from :supplier.', ['organisation' => $orgSupplier->organisation->code, 'supplier' => $supplier->code]),
                !$user->authTo("procurement.$orgSupplier->organisation_id.edit") => __('You cannot edit purchase orders of :organisation.', ['organisation' => $orgSupplier->organisation->code]),
                default                                                        => null,
            };

            $columns[$key] = ['org_supplier' => $orgSupplier, 'reason' => $reason, 'cartons' => 0];
        }

        return $columns;
    }

    /**
     * @param array<int, array{supplier_product: SupplierProduct, cartons: int, rows: list<int>, sheet_units_per_carton: ?int, name: ?string}> $products
     * @param list<array<string, mixed>> $skipped
     *
     * @return array<string, mixed>
     */
    protected function organisationPlan(OrgSupplier $orgSupplier, string $key, array $products, array &$skipped): array
    {
        $organisation = $orgSupplier->organisation;

        $orgSupplierProducts = OrgSupplierProduct::where('org_supplier_id', $orgSupplier->id)
            ->whereIn('supplier_product_id', array_keys($products))
            ->orderByRaw("state = '".OrgSupplierProductStateEnum::ACTIVE->value."' desc")
            ->get()
            ->unique('supplier_product_id')
            ->keyBy('supplier_product_id');

        /** @var PurchaseOrder|null $openDraft */
        $openDraft    = $orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest('id')->first();
        $currentUnits = $openDraft
            ? $openDraft->purchaseOrderTransactions()->whereIn('supplier_product_id', array_keys($products))->pluck('quantity_ordered', 'supplier_product_id')
            : collect();

        $lines = [];
        foreach ($products as $supplierProductId => $product) {
            /** @var SupplierProduct $supplierProduct */
            $supplierProduct    = $product['supplier_product'];
            $orgSupplierProduct = $orgSupplierProducts->get($supplierProductId);
            $unitsPerCarton     = (int) $supplierProduct->units_per_carton;

            $reason = match (true) {
                !$orgSupplierProduct                                                      => __('Not supplied to :organisation.', ['organisation' => $organisation->code]),
                $orgSupplierProduct->state !== OrgSupplierProductStateEnum::ACTIVE->value => __('Not active for :organisation.', ['organisation' => $organisation->code]),
                !$orgSupplierProduct->is_available || !$supplierProduct->is_available     => __('Not available from the supplier.'),
                !$supplierProduct->current_historic_supplier_product_id                                            => __('No price history, save the supplier product again.'),
                $unitsPerCarton < 1                                                       => __('Units per carton is not set.'),
                default                                                                   => $this->orgStockProblem($organisation, $supplierProduct),
            };

            if ($reason !== null) {
                $skipped[] = $this->skip(min($product['rows']), $supplierProduct->code, [$key => $product['cartons']], $reason);

                continue;
            }

            $units    = $product['cartons'] * $unitsPerCarton;
            $warnings = [];
            if ($product['sheet_units_per_carton'] && $product['sheet_units_per_carton'] !== $unitsPerCarton) {
                $warnings[] = __('The sheet says :sheet units per carton, Aiku has :aiku: ordered with :aiku.', ['sheet' => $product['sheet_units_per_carton'], 'aiku' => $unitsPerCarton]);
            }
            if (count($product['rows']) > 1) {
                $warnings[] = __('On rows :rows, cartons added together.', ['rows' => implode(', ', $product['rows'])]);
            }

            $lines[] = [
                'row'                     => min($product['rows']),
                'supplier_product_id'     => $supplierProductId,
                'org_supplier_product_id' => $orgSupplierProduct->id,
                'code'                    => $supplierProduct->code,
                'name'                    => $supplierProduct->name ?? $product['name'],
                'cartons'                 => $product['cartons'],
                'units_per_carton'        => $unitsPerCarton,
                'units'                   => $units,
                'current_units'           => $currentUnits->has($supplierProductId) ? (float) $currentUnits->get($supplierProductId) : null,
                'unit_cost'               => (float) $supplierProduct->cost,
                'amount'                  => round($units * (float) $supplierProduct->cost, 2),
                'warnings'                => $warnings,
            ];
        }

        usort($lines, fn (array $a, array $b) => $a['row'] <=> $b['row']);

        return [
            'key'             => $key,
            'org_supplier_id' => $orgSupplier->id,
            'organisation'    => ['id' => $organisation->id, 'code' => $organisation->code, 'name' => $organisation->name],
            'purchase_order'  => $openDraft ? ['id' => $openDraft->id, 'reference' => $openDraft->reference, 'is_new' => false] : ['id' => null, 'reference' => null, 'is_new' => true],
            'lines'           => $lines,
            'cartons'         => array_sum(array_column($lines, 'cartons')),
            'units'           => array_sum(array_column($lines, 'units')),
            'amount'          => round(array_sum(array_column($lines, 'amount')), 2),
        ];
    }

    protected function orgStockProblem(Organisation $organisation, SupplierProduct $supplierProduct): ?string
    {
        $orgStock = ResolveOrgStockForSupplierProduct::run($organisation, $supplierProduct);

        if (!$orgStock) {
            return __('No SKO in :organisation yet.', ['organisation' => $organisation->code]);
        }

        if (in_array($orgStock->state, [OrgStockStateEnum::DISCONTINUING, OrgStockStateEnum::DISCONTINUED], true)) {
            return __('SKO :code is :state.', ['code' => $orgStock->code, 'state' => $orgStock->state->value]);
        }

        return null;
    }

    /**
     * @param array<string, int|string> $cartons
     *
     * @return array{row: ?int, code: string, cartons: array<string, int|string>, reason: string}
     */
    protected function skip(?int $row, string $code, array $cartons, string $reason): array
    {
        return ['row' => $row, 'code' => $code, 'cartons' => $cartons, 'reason' => $reason];
    }

    /**
     * Earlier order imports of the same file, or of a file ordering exactly the same lines, and
     * supplier product uploads of the same file that already placed its cartons on drafts.
     *
     * @return list<array{id: int, filename: ?string, user: ?string, imported_at: ?string, match: string, purchase_orders: list<string>}>
     */
    protected function previousImports(Supplier $supplier, UploadedFile $file, string $fileHash, ?string $linesHash): array
    {
        $uploads = Upload::where('parent_type', $supplier->getMorphClass())
            ->where('parent_id', $supplier->id)
            ->where('state', UploadStateEnum::IMPORTED)
            ->where(function ($query) use ($file) {
                $query->where('model', self::UPLOAD_MODEL)
                    ->orWhere(fn ($query) => $query->where('model', 'SupplierProduct')->where('filesize', $file->getSize())->whereNotNull('data->purchase_orders'));
            })
            ->with('user')
            ->latest('id')
            ->limit(50)
            ->get();

        $previous = [];
        foreach ($uploads as $upload) {
            $match = match (true) {
                $this->uploadHash($upload) === $fileHash                                     => 'same_file',
                $linesHash !== null && ($upload->data['lines_hash'] ?? null) === $linesHash => 'same_lines',
                $upload->model === self::UPLOAD_MODEL && $upload->original_filename === $file->getClientOriginalName() => 'same_name',
                default                                                                      => null,
            };

            if ($match === null) {
                continue;
            }

            $previous[] = [
                'id'              => $upload->id,
                'filename'        => $upload->original_filename,
                'user'            => $upload->user?->contact_name ?: $upload->user?->username,
                'imported_at'     => $upload->created_at?->toIso8601String(),
                'match'           => $match,
                'purchase_orders' => collect($upload->data['purchase_orders'] ?? [])->pluck('purchase_order')->filter()->values()->all(),
            ];
        }

        return array_slice($previous, 0, 5);
    }

    protected function uploadHash(Upload $upload): ?string
    {
        if ($upload->model === self::UPLOAD_MODEL) {
            return $upload->data['file_hash'] ?? null;
        }

        try {
            $path = Storage::disk('excel-uploads')->path($upload->path.'/'.$upload->filename);

            return is_file($path) ? sha1_file($path) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
