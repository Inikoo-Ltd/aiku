<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Slack;

use App\Actions\GrpAction;
use App\Models\Masters\MasterAsset;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class NewMasterAssetCreated extends GrpAction
{
    public function handle(MasterAsset $masterAsset): SlackMessage
    {
        $type = $masterAsset->type->labels()[$masterAsset->type->value];
        return (new SlackMessage())
             ->sectionBlock(function (SectionBlock $block) use ($masterAsset, $type) {
                 $block->text("*New Master $type Created");

                 $block->field("*Master $type Code:*\n$masterAsset->code")->markdown();

                 $block->field("*Master $type Name:*\n$masterAsset->name")->markdown();


             })
             ->dividerBlock()
             ->contextBlock(function (ContextBlock $block) use ($masterAsset) {
                 $timestamp = $masterAsset->created_at ?? now();
                 $block->text("Created on $timestamp");
             });
    }
}
