<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UpdateWebsiteSearchBoosts;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class GetWebsiteSearchBoostCandidates extends OrgAction
{
    use WithWebAuthorisation;

    /**
     * @return array<int, array{type: string, id: int, code: string, name: string|null}>
     */
    public function handle(Shop $shop, string $query): array
    {
        $candidates = [];
        foreach (UpdateWebsiteSearchBoosts::BOOSTABLE_TYPES as $type => $modelClass) {
            $models = $modelClass::query()
                ->where('shop_id', $shop->id)
                ->where('is_in_website', true)
                ->where(function ($builder) use ($query) {
                    $builder->whereRaw('code ILIKE ?', ["%$query%"])
                        ->orWhereRaw('name ILIKE ?', ["%$query%"]);
                })
                ->orderBy('code')
                ->limit(5)
                ->get(['id', 'code', 'name']);

            foreach ($models as $model) {
                $candidates[] = [
                    'type' => $type,
                    'id'   => $model->id,
                    'code' => $model->code,
                    'name' => $model->name,
                ];
            }
        }

        return $candidates;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:100'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData['q']);
    }
}
