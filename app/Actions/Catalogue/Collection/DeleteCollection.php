<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Actions\Catalogue\Collection\Search\CollectionRecordSearch;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCollections;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteCollection extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private ?Collection $collection;

    public function handle(Collection $collection, bool $forceDelete = false): Collection
    {
        if ($forceDelete) {
            DB::table('model_has_collections')->where('collection_id', $collection->id)->delete();
            DB::table('collection_has_models')->where('collection_id', $collection->id)->delete();

            if ($collection->stats) {
                $collection->stats->delete();
            }

            if ($collection->salesIntervals) {
                $collection->salesIntervals->delete();
            }

            if ($collection->orderingStats) {
                $collection->orderingStats->delete();
            }

            $collection->forceDelete();
        } else {
            $collection->delete();
        }

        CollectionRecordSearch::run($collection);
        ShopHydrateCollections::dispatch($collection->shop);

        return $collection;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->collection->webpage) {
            $validator->errors()->add('webpage', __('Cannot force delete a collection that has a webpage'));
        }
    }

    public function action(Collection $collection, bool $forceDelete = false): Collection
    {
        $this->collection = $collection;

        return $this->handle($collection, $forceDelete);
    }

    public function asController(Collection $collection, ActionRequest $request): Collection
    {
        $this->collection = $collection;
        $this->initialisation($collection->organisation, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($collection, $forceDelete);
    }

    public function inShop(Shop $shop, Collection $collection, ActionRequest $request): Collection
    {
        $this->collection = $collection;
        $this->initialisationFromShop($shop, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($collection, $forceDelete);
    }

    public function htmlResponse(Collection $collection): RedirectResponse
    {
        return redirect()->route(
            'grp.org.shops.show.catalogue.collections.index',
            [
                'organisation' => $collection->organisation->slug,
                'shop'         => $collection->shop->slug
            ]
        );
    }
}
