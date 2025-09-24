<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Slack;

use App\Actions\GrpAction;
use App\Models\Masters\MasterAsset;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class NewMasterAssetCreated extends GrpAction
{
    public function handle(MasterAsset $masterAsset): SlackMessage
    {
        $type = $masterAsset->type->labels()[$masterAsset->type->value];
        $url = route('grp.masters.master_shops.show.master_products.show', [
            $masterAsset->masterShop->slug,
            $masterAsset->slug
        ]);
        return (new SlackMessage())
             ->headerBlock("*New Master $type Created")
             ->sectionBlock(function (SectionBlock $block) use ($masterAsset, $type) {
                 $block->field("*Master $type Code:*\n$masterAsset->code")->markdown();

                 $block->field("*Master $type Name:*\n$masterAsset->name")->markdown();
             })
             ->dividerBlock()
            ->actionsBlock(function (ActionsBlock $block) use ($url) {
                $block->button('View')->primary()->url($url);
            })
             ->dividerBlock()
            ->sectionBlock(function (SectionBlock $block) use ($masterAsset, $type) {
                $block->text("*New $type"."s Created in Shops");

                foreach ($masterAsset->products as $product) {
                    $block->field("{$product->shop->code}")->markdown();

                    $block->field("{$product->name}")->markdown();
                }
            })
             ->dividerBlock()
             ->actionsBlock(function (ActionsBlock $block) use ($masterAsset) {
                 foreach ($masterAsset->products as $product) {
                     if (!$product->webpage) {
                         continue;
                     }

                     $url = 'https://' . $product->webpage->website->domain . '/';
                     if ($product->department && $product->department->url) {
                         $url .= $product->department->url . '/';
                     }
                     if ($product->subDepartment && $product->subDepartment->url) {
                         $url .= $product->subDepartment->url . '/';
                     }
                     if ($product->family && $product->family->url) {
                         $url .= $product->family->url . '/';
                     }
                     $url .= $product->url;

                     $block->button("{$product->shop->code}")->url($url);
                 }

             })
             ->dividerBlock()
             ->contextBlock(function (ContextBlock $block) use ($masterAsset) {
                 $timestamp = $masterAsset->created_at ?? now();
                 $block->text("Created on $timestamp");
             });
    }
}
