<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\OrgAction;
use App\Actions\Procurement\ShoppingListItem\DeleteShoppingListItem;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class RemoveMisplacedAgentShoppingListItems extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * Drop open shopping-list items that sit in a bucket that should not be ordered (covered or dead stock).
     */
    public function handle(OrgAgent $orgAgent, string $bucket): int
    {
        abort_unless(in_array($bucket, ['ok', 'dead'], true), 422, 'Only covered or dead stock items can be cleared');

        $orgSupplierProductIds = GetAgentStockCoverBuckets::make()->orgSupplierProductIdsInBucket($orgAgent, $bucket);

        $items = ShoppingListItem::query()
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereIn('org_supplier_product_id', $orgSupplierProductIds)
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

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent, $this->validatedData['bucket']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
