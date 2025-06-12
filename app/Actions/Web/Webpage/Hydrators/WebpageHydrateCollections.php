<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-15h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Webpage\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateCollections
{
    use AsAction;
    use WithEnumStats;

    private Webpage $webpage;

    public function __construct(Webpage $webpage)
    {
        $this->webpage = $webpage;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->webpage->id))->dontRelease()];
    }

    public function handle(Webpage $webpage): void
    {
        $productCategoryId = $webpage->model_id;

        // All collections related to this ProductCategory via model_has_collections
        $collectionIds = DB::table('model_has_collections')
            ->where('model_type', 'ProductCategory')
            ->where('model_id', $productCategoryId)
            ->pluck('collection_id');

        $numberCollections = $collectionIds->count();

        // Collections that have their own webpage
        $numberCollectionsWithWebpage = DB::table('webpages')
            ->where('model_type', 'Collection')
            ->whereIn('model_id', $collectionIds)
            ->count(DB::raw('DISTINCT model_id'));

        // Collections that have an online webpage
        $numberCollectionsWithOnlineWebpage = DB::table('webpages')
            ->where('model_type', 'Collection')
            ->where('state', WebpageStateEnum::LIVE)
            ->whereIn('model_id', $collectionIds)
            ->count(DB::raw('DISTINCT model_id'));

        // Collections that have an offline webpage
        $numberCollectionsWithOfflineWebpage = DB::table('webpages')
            ->where('model_type', 'Collection')
            ->where('state', WebpageStateEnum::CLOSED)
            ->whereIn('model_id', $collectionIds)
            ->count(DB::raw('DISTINCT model_id'));

        $webpage->stats()->update([
            'number_collections' => $numberCollections,
            'number_collections_with_webpage' => $numberCollectionsWithWebpage,
            'number_collections_with_online_webpage' => $numberCollectionsWithOnlineWebpage,
            'number_collections_with_offline_webpage' => $numberCollectionsWithOfflineWebpage,
        ]);
    }


}
