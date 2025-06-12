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
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteCollection extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Collection $collection, bool $forceDelete = false): Collection
    {
        if ($forceDelete) {
            if ($collection->webpage) {
                throw new \Exception("Cannot force delete a collection that has a webpage");
            }

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

        return $collection;
    }

    public function action(Collection $collection, bool $forceDelete = false): Collection
    {
        return $this->handle($collection, $forceDelete);
    }

    public function asController(Collection $collection, ActionRequest $request): Collection
    {
        $this->initialisation($collection->organisation, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($collection, $forceDelete);
    }

    public function inShop(Shop $shop, Collection $collection, ActionRequest $request): Collection
    {
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
