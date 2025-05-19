<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Jun 2024 10:22:50 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Web\WebBlockResource;
use App\Models\Web\Webpage;

class UpdateWebpageContent extends OrgAction
{
    use WithActionUpdate;
    use WithWebEditAuthorisation;
    use WebpageContentManagement;

    public function handle(Webpage $webpage): Webpage
    {
        $snapshot = $webpage->unpublishedSnapshot;

        $layout = [];
        foreach ($webpage->webBlocks as $webBlock) {
            $layout['web_blocks'][] =
                [
                    'id'         => $webBlock->pivot->id,
                    'name'       => $webBlock->webBlockType->name,
                    'type'       => $webBlock->webBlockType->code,
                    'web_block'  => WebBlockResource::make($webBlock)->getArray(),
                    'visibility' => ['in' => $webBlock->pivot->show_logged_in, 'out' => $webBlock->pivot->show_logged_out],
                    'show'       => $webBlock->pivot->show,
                ];
        }

        $snapshot->update(
            [
                'layout' => $layout
            ]
        );

        $isDirty = true;
        if ($webpage->published_checksum == md5(json_encode($snapshot->layout))) {
            $isDirty = false;
        }

        $webpage->update(
            [
                'is_dirty' => $isDirty
            ]
        );


        return $webpage;
    }


}
