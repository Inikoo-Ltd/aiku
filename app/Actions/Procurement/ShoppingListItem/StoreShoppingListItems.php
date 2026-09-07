<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StoreShoppingListItems extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @param array<int, array{org_supplier_product_id: int, quantity_units: float, notes?: string}> $lines
     *
     * @return array{created: int, skipped: array<int, array{org_supplier_product_id: int, reason: string}>}
     */
    public function handle(array $lines): array
    {
        $created = 0;
        $skipped = [];

        foreach ($lines as $line) {
            $orgSupplierProduct = OrgSupplierProduct::find($line['org_supplier_product_id']);
            if (!$orgSupplierProduct) {
                continue;
            }

            try {
                StoreShoppingListItem::make()->action($orgSupplierProduct, [
                    'quantity_units' => $line['quantity_units'],
                    'notes'          => $line['notes'] ?? null,
                ]);
                $created++;
            } catch (HttpException $exception) {
                $skipped[] = [
                    'org_supplier_product_id' => $orgSupplierProduct->id,
                    'reason'                  => $exception->getMessage(),
                ];
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function rules(): array
    {
        return [
            'lines'                           => ['required', 'array', 'min:1'],
            'lines.*.org_supplier_product_id' => ['required', 'integer', 'exists:org_supplier_products,id'],
            'lines.*.quantity_units'          => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes'                   => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData['lines']);
    }

    public function action(Organisation $organisation, array $lines): array
    {
        $this->asAction = true;
        $this->initialisation($organisation, ['lines' => $lines]);

        return $this->handle($this->validatedData['lines']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
