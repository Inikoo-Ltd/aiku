<?php

/*
 * Author: Louis Perez Napitupulu
 * Created: Wed, 07 Oct 2026 09:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Masters\MasterVariant;

use App\Actions\Catalogue\Variant\CopyMasterVariantProductOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterVariantProductOrder extends OrgAction
{
    use WithMastersEditAuthorisation;

    /**
     * @param array{products: array<int, array{id: int, index: int}>} $modelData
     */
    public function handle(MasterVariant $masterVariant, array $modelData): MasterVariant
    {
        DB::transaction(function () use ($masterVariant, $modelData) {
            foreach ($modelData['products'] as $product) {
                MasterAsset::where('id', $product['id'])
                    ->where('master_variant_id', $masterVariant->id)
                    ->update(['index_under_master_variant' => $product['index']]);
            }

            CopyMasterVariantProductOrder::run($masterVariant);
        });

        return $masterVariant;
    }

    public function rules(): array
    {
        return [
            'products'         => ['required', 'array'],
            'products.*.id'    => ['required', 'integer'],
            'products.*.index' => ['required', 'integer', 'gte:0'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->initialisationFromGroup($masterVariant->group, $request);

        return $this->handle($masterVariant, $this->validatedData);
    }
}
