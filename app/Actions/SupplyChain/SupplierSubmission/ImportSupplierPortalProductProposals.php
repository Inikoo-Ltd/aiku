<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 08:52
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Exports\SupplyChain\SupplierProductProposalTemplateExport;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A supplier that keeps its products in a spreadsheet sends many new product ideas at once. The filled template is
 * read first and nothing is saved: the supplier sees every row with what is wrong in it, corrects it on the page and
 * adds pictures to each product. The products are then sent a few at a time, each one as if filled in the Suggest a
 * product form, so a long list with pictures never goes in one request and what was sent stays sent if it stops.
 */
class ImportSupplierPortalProductProposals
{
    use AsAction;

    public const int MAX_ROWS = 200;

    public const int MAX_FILE_KB = 5120;

    public const int MAX_PRODUCTS_PER_REQUEST = 10;

    /**
     * @return list<array{row: int, values: array<string, mixed>, errors: array<string, string>}>
     */
    public function previewRows(Supplier $supplier, UploadedFile $file): array
    {
        $rows = $this->rowsOf($file, $supplier->currency?->code);

        if (!$rows) {
            throw ValidationException::withMessages(['file' => __('The file has no products. Fill one product per row below the headings of our template.')]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('Send at most :count products in one file.', ['count' => self::MAX_ROWS])]);
        }

        $preview = [];
        foreach ($rows as $rowNumber => $values) {
            $errors = Validator::make($values, StoreSupplierPortalSubmission::productProposalRules(), [], $this->attributeNames())->errors();

            $preview[] = [
                'row'    => $rowNumber,
                'values' => $values,
                'errors' => collect($errors->keys())->mapWithKeys(fn (string $field) => [$field => $errors->first($field)])->all(),
            ];
        }

        return $preview;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     *
     * @return list<int> the spreadsheet rows sent
     */
    public function handle(SupplierShareLink $link, array $products): array
    {
        return DB::transaction(fn () => array_map(function (array $product) use ($link) {
            $row = (int) Arr::pull($product, 'row');
            StoreSupplierPortalSubmission::make()->handle($link, SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL, $product);

            return $row;
        }, $products));
    }

    /**
     * The filled rows of the first sheet, keyed by their row number in the spreadsheet. A row with nothing but a
     * currency is left out.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsOf(UploadedFile $file, ?string $currency): array
    {
        $sheet  = Excel::toArray(new \stdClass(), $file)[0] ?? [];
        $fields = array_keys(SupplierProductProposalTemplateExport::COLUMNS);
        $rows   = [];

        foreach (array_slice($sheet, 1, preserve_keys: true) as $index => $cells) {
            $row = [];
            foreach ($fields as $position => $field) {
                $row[$field] = $this->cellValue($field, $cells[$position] ?? null);
            }

            if (!array_filter(array_diff_key($row, ['currency' => true]), fn ($value) => $value !== null)) {
                continue;
            }

            $row['currency'] = strtoupper($row['currency'] ?? (string) $currency) ?: null;
            $rows[$index + 1] = $row;
        }

        return $rows;
    }

    private function cellValue(string $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'proposed_price' => is_string($value) ? str_replace(',', '.', $value) : $value,
            'lead_time_days' => $value,
            default          => (string) $value,
        };
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(string $prefix = ''): array
    {
        return collect(SupplierProductProposalTemplateExport::COLUMNS)
            ->mapWithKeys(fn (string $heading, string $field) => [$prefix.$field => __($heading)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function productsRules(): array
    {
        return [
            'products'           => ['required', 'array', 'max:'.self::MAX_PRODUCTS_PER_REQUEST],
            'products.*.row'     => ['required', 'integer', 'min:2'],
            ...collect(StoreSupplierPortalSubmission::productProposalRules())->mapWithKeys(fn (array $rules, string $field) => ['products.*.'.$field => $rules])->all(),
            'products.*.files'   => ['nullable', 'array', 'max:'.StoreSupplierPortalSubmission::MAX_FILES],
            'products.*.files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.StoreSupplierPortalSubmission::MAX_FILE_KB],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function fileRules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:'.self::MAX_FILE_KB]];
    }

    public function preview(string $token, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = $request->validate(self::fileRules());

        return response()->json(['rows' => $this->previewRows($link->supplier, $validated['file'])]);
    }

    public function asController(string $token, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = Validator::make($request->all(), $this->productsRules(), [], $this->attributeNames('products.*.'))->validate();

        return response()->json(['sent' => $this->handle($link, $validated['products'])]);
    }
}
