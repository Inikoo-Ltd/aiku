<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\OrgAction;
use App\Actions\Procurement\ShoppingListItem\DeleteShoppingListItem;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class RemoveMisplacedSupplierShoppingListItems extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * Drop open shopping-list items that sit in a bucket that should not be ordered (covered or dead stock).
     */
    public function handle(OrgSupplier $orgSupplier, string $bucket): int
    {
        abort_unless(in_array($bucket, ['ok', 'dead'], true), 422, 'Only covered or dead stock items can be cleared');

        $ids = GetSupplierStockCoverBuckets::make()->orgSupplierProductIdsInBucket($orgSupplier, $bucket);

        $items = ShoppingListItem::query()
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereIn('org_supplier_product_id', $ids)
            ->get();

        foreach ($items as $item) {
            DeleteShoppingListItem::make()->action($item);
        }

        return $items->count();
    }

    public function rules(): array
    {
        return [
            'bucket' => ['required', Rule::in(['ok', 'dead'])],
        ];
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): int
    {
        abort_if($orgSupplier->org_agent_id, 404);

        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier, $this->validatedData['bucket']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
