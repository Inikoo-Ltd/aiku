<?php

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierProduct\Upload\CheckSupplierProductSheet;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Findings for the New supplier product form, the same ones an upload row gets. Called as the buyer types
 * (rules only, free), with start_review when Save is pressed (starts the AI step) and with the review id to
 * pick up the AI findings once they are back. Saves nothing.
 */
class CheckSupplierProductForm extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    /**
     * @return array{values: array<string, mixed>, findings: list<array<string, mixed>>, review: ?array{id: string, status: string, summary: ?string, suggestion: ?string, note: ?string}}
     */
    public function handle(Supplier $supplier, array $modelData): array
    {
        $row      = CheckSupplierProductSheet::make()->checkProduct($supplier, $modelData);
        $reviewId = Arr::get($modelData, 'start_review') ? ReviewSupplierProductForm::make()->start($supplier, $row) : Arr::get($modelData, 'review');

        [$row, $review] = ReviewSupplierProductForm::make()->applyReview($supplier, $row, $reviewId);

        return [
            'values'   => $row['values'],
            'findings' => $row['findings'],
            'review'   => $review ? [
                'id'         => $reviewId,
                'status'     => $review['status'],
                'summary'    => Arr::get($review, 'summary'),
                'suggestion' => Arr::get($review, 'suggestion'),
                'note'       => Arr::get($review, 'note'),
            ] : null,
        ];
    }

    public function rules(): array
    {
        return [
            ...StoreSupplierProductFromForm::make()->rules(),
            'start_review' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Supplier $supplier, ActionRequest $request): array
    {
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData);
    }
}
