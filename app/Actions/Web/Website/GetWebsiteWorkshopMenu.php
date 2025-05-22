<?php

namespace App\Actions\Web\Website;

use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopMenu
{
    use AsObject;

    public function handle(Website $website): array
    {
        //todo thi sis a horrible hack need to ne replaced one day from a repair action
        if(!$website->unpublishedHeaderSnapshot){

            return [
                'menu'    => Arr::get($website->published_layout, 'menu', [])
            ];
        }

        return [
            'menu'    => Arr::get($website->unpublishedHeaderSnapshot, 'menu', [])
        ];
    }
}
