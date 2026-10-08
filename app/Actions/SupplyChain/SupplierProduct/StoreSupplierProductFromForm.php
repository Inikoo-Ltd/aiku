<?php

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierProduct\Upload\CheckSupplierProductSheet;
use App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The New supplier product form's final save: one product checked like a supplier product upload row, with the
 * AI findings of its review (ReviewSupplierProductForm), then created the same way (families, trade unit,
 * barcode, SKO and supplier product) once nothing is left to fix or decide. The AI is not asked again.
 */
class StoreSupplierProductFromForm extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, array $modelData, ?int $userId = null): SupplierProduct
    {
        $row = CheckSupplierProductSheet::make()->checkProduct($supplier, $modelData);

        [$row, $review] = ReviewSupplierProductForm::make()->applyReview($supplier, $row, Arr::get($modelData, 'review'));
        if (Arr::get($review, 'status') !== 'done') {
            throw ValidationException::withMessages(['review' => __('Press Save to check the product first.')]);
        }

        $problems = $this->problems($row, Arr::get($modelData, 'accepted', []));
        if ($problems !== []) {
            throw ValidationException::withMessages(['findings' => $problems]);
        }

        $decisions = collect($row['findings'])
            ->whereIn('level', ['block', 'link'])
            ->mapWithKeys(fn (array $finding) => [$finding['code'] => ['accepted' => true, 'user_id' => $userId, 'at' => now()->toIso8601String(), 'message' => $finding['message']]])
            ->all();

        $supplierProduct = DB::transaction(fn () => ImportSupplierProductUpload::make()->importValues($supplier, $row['values'], $decisions)['supplier_product']);
        ReviewSupplierProductForm::make()->forget($modelData['review']);

        return $supplierProduct;
    }

    /**
     * @param array{values: array<string, mixed>, findings: list<array{level: string, code: string, column: ?string, message: string}>} $row
     * @param list<string> $accepted codes of the findings ticked "I accept responsibility" or "Add this supplier to it"
     *
     * @return list<string>
     */
    public function problems(array $row, array $accepted): array
    {
        $problems = collect($row['findings'])
            ->filter(fn (array $finding) => $finding['level'] === 'error' || (in_array($finding['level'], ['block', 'link'], true) && !in_array($finding['code'], $accepted, true)))
            ->pluck('message')
            ->values()
            ->all();

        if (blank(Arr::get($row['values'], 'sko_name'))) {
            $problems[] = __('The SKO name is missing.');
        }

        return $problems;
    }

    public function rules(): array
    {
        $rules = [
            'sko_name'   => ['nullable', 'string', 'max:255'],
            'review'     => ['nullable', 'string', 'max:64'],
            'accepted'   => ['sometimes', 'array'],
            'accepted.*' => ['string'],
        ];
        foreach (SupplierProductSheetColumnEnum::cases() as $column) {
            $rules[$column->value] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Supplier $supplier, ActionRequest $request): SupplierProduct
    {
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData, $request->user()->id);
    }

    public function htmlResponse(SupplierProduct $supplierProduct): RedirectResponse
    {
        return StoreSupplierProduct::make()->htmlResponse($supplierProduct);
    }
}
