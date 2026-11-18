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
use App\Models\SupplyChain\SupplierShareLink;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A supplier that keeps its products in a spreadsheet sends many new product ideas at once: one row of our template
 * is one product idea, sent as if filled in the Suggest a product form. Nothing is saved while any row is wrong, and
 * every wrong row is named, so the supplier fixes the file and uploads it again.
 */
class ImportSupplierPortalProductProposals
{
    use AsAction;

    public const int MAX_ROWS = 200;

    public const int MAX_FILE_KB = 5120;

    /**
     * @return list<SupplierSubmission>
     */
    public function handle(SupplierShareLink $link, UploadedFile $file): array
    {
        $rows = $this->rowsOf($file, $link->supplier->currency?->code);

        if (!$rows) {
            throw ValidationException::withMessages(['file' => __('The file has no products. Fill one product per row below the headings of our template.')]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('Send at most :count products in one file.', ['count' => self::MAX_ROWS])]);
        }

        $errors = [];
        foreach ($rows as $rowNumber => $row) {
            $validator = Validator::make($row, StoreSupplierPortalSubmission::productProposalRules(), [], $this->attributeNames());
            if ($validator->fails()) {
                $errors['row_'.$rowNumber] = __('Row :row: :errors', ['row' => $rowNumber, 'errors' => implode(' ', $validator->errors()->all())]);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(fn () => array_values(array_map(
            fn (array $row) => StoreSupplierPortalSubmission::make()->handle($link, SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL, $row),
            $rows
        )));
    }

    /**
     * The filled rows of the first sheet, keyed by their row number in the spreadsheet. A row with nothing but the
     * currency we put in the template is left out.
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
    private function attributeNames(): array
    {
        return array_map(fn (string $heading) => __($heading), SupplierProductProposalTemplateExport::COLUMNS);
    }

    public function asController(string $token, Request $request): RedirectResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:'.self::MAX_FILE_KB],
        ]);

        $this->handle($link, $validated['file']);

        return back()->with('submitted', SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL->value);
    }
}
