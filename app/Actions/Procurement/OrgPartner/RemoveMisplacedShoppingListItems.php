<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 2 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\OrgAction;
use App\Actions\Procurement\PartnerShoppingListItem\DeletePartnerShoppingListItem;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class RemoveMisplacedShoppingListItems extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * Drop open shopping-list items that sit in a bucket that should not be ordered (covered or dead stock).
     */
    public function handle(OrgPartner $orgPartner, string $bucket): int
    {
        abort_unless(in_array($bucket, ['ok', 'dead'], true), 422, 'Only covered or dead stock items can be cleared');

        $stockIds = GetPartnerStockCoverBuckets::make()->stockIdsInBucket($orgPartner, $bucket);

        $items = PartnerShoppingListItem::query()
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereIn('stock_id', $stockIds)
            ->get();

        foreach ($items as $item) {
            DeletePartnerShoppingListItem::make()->action($item);
        }

        return $items->count();
    }

    public function rules(): array
    {
        return [
            'bucket' => ['required', Rule::in(['ok', 'dead'])],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $this->validatedData['bucket']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
