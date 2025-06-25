<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Comms\OrgPostRoom\UI;

use App\Http\Resources\Mail\OrgPostRoomResource;
use App\Models\Comms\OrgPostRoom;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgPostRoomShowcase
{
    use AsObject;

    public function handle(OrgPostRoom $orgPostRoom): array
    {
        return [

            'orgPostRoom' => OrgPostRoomResource::make($orgPostRoom),
            'stats'   => $orgPostRoom->stats
        ];
    }
}
