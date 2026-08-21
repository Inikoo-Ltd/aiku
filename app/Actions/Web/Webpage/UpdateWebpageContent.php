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

        $fingerprintData = '';
        foreach ($webpage->webBlocks as $webBlock) {
            $fingerprintData .= hash(
                'sha256',
                serialize(
                    [
                        'show'   => $webBlock->pivot->show,
                        'in'     => $webBlock->pivot->show_logged_in,
                        'out'    => $webBlock->pivot->show_logged_out,
                        'layout' => $webBlock->layout,
                        'data'   => $webBlock->data,
                    ]
                )
            );


            $layout['web_blocks'][] = [
                'id'         => $webBlock->pivot->id,
                'name'       => $webBlock->webBlockType->name,
                'show'       => $webBlock->pivot->show,
                'type'       => $webBlock->webBlockType->code,
                'visibility' => ['in' => $webBlock->pivot->show_logged_in, 'out' => $webBlock->pivot->show_logged_out],
                'web_block'  => WebBlockResource::make($webBlock)->getArray(),

            ];
        }
        $fingerprintData = hash('sha256', $fingerprintData);


        $snapshot->update(
            [
                'layout'   => $layout,
                'checksum' => $fingerprintData,
            ]
        );


        $isDirty = true;

        if ($webpage->published_checksum == $fingerprintData) {
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
