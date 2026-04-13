<?php

namespace App\Actions\Web\Website\UI\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class FetchFamilyDescriptionBlockLayout extends OrgAction
{
    use WithWebAuthorisation;
    
    public function asController(Website $website, WebBlockType $webBlockType, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($webBlockType, $this->validatedData);
    }

    public function handle(WebBlockType $webBlockType, array $modelData): Collection
    {
        // Later add selection, choose from Unpublished/Live 
        return WebBlockType::where('slug', $webBlockType->slug)
                ->orWhere('slug', $webBlockType->slug . '-extra-description')
                ->get()
                ->pluck('data', 'slug');
    }

    public function jsonResponse(Collection $webBlockData): array
    {
        return $webBlockData->toArray();
    }
}
