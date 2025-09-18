<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Slack;

use App\Actions\GrpAction;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class NewMasterProductCategoryCreated extends GrpAction
{
    public function handle(MasterProductCategory $masterProductCategory): SlackMessage
    {
        $type = $masterProductCategory->type->label();
        return (new SlackMessage())
             ->sectionBlock(function (SectionBlock $block) use ($masterProductCategory, $type) {
                 $block->text("*New $type Created");

                 $block->field("*$type Code:*\n$masterProductCategory->code")->markdown();

                 $block->field("*$type Name:*\n$masterProductCategory->name")->markdown();


             })
             ->dividerBlock()
             ->contextBlock(function (ContextBlock $block) use ($masterProductCategory) {
                 $timestamp = $masterProductCategory->created_at ?? now();
                 $block->text("Created on $timestamp");
             });
    }
}
