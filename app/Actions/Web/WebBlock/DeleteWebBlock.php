<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Oct 2024 09:53:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Actions\OrgAction;
use App\Models\Helpers\Media;
use App\Models\Web\WebBlock;
use Illuminate\Support\Facades\DB;

class DeleteWebBlock extends OrgAction
{
    public function handle(WebBlock $webBlock): void
    {

        $mediaIds = $webBlock->images()->pluck('media.id');


        DB::table("model_has_web_blocks")
            ->where("web_block_id", $webBlock->id)
            ->delete();

        DB::table("web_block_has_models")
            ->where("web_block_id", $webBlock->id)
            ->delete();


        DB::table("model_has_media")
            ->where("model_type", 'WebBlock')
            ->where("model_id", $webBlock->id)
            ->delete();

        $externalLinks = $webBlock->externalLinks;
        foreach ($externalLinks as $externalLink) {
            $pivotData = $externalLink->pivot;
            $show = $pivotData->show;
            $prepareQuery = [];
            if ($show) {
                $prepareQuery += [
                    'number_websites_shown' => $externalLink->number_websites_shown - 1,
                    'number_webpages_shown' => $externalLink->number_webpages_shown - 1,
                    'number_web_blocks_shown' => $externalLink->number_web_blocks_shown - 1
                ];
            } else {
                $prepareQuery += [
                    'number_websites_hidden' => $externalLink->number_websites_hidden - 1,
                    'number_webpages_hidden' => $externalLink->number_webpages_hidden - 1,
                    'number_web_blocks_hidden' => $externalLink->number_web_blocks_hidden - 1
                ];
            }
            $externalLink->update($prepareQuery);
        }

        $webBlock->externalLinks()->detach();


        /** @var Media $media */
        foreach ($mediaIds as $mediaId) {
            $usage = DB::table('model_has_media')->where('media_id', $mediaId)->count();
            if ($usage == 0) {
                Media::find($mediaId)->delete();
            }
        }


        $webBlock->deletePreservingMedia();

    }



}
