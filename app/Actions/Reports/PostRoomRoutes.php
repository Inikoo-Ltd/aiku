<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 14:54:29 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Reports;

use App\Actions\Comms\PostRoom\UI\IndexPostRooms;
use Illuminate\Support\Facades\Route;

class PostRoomRoutes
{
    public function __invoke($parent): void
    {
        // todo review this
        Route::get('/post_rooms', [IndexPostRooms::class, $parent == 'organisation' ? 'inOrganisation' : 'asController'])->name('post_rooms.index');

    }
}
