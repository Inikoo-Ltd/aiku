<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Slack;

use App\Actions\GrpAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class NewMasterProductCategoryCreated extends GrpAction
{
    public function handle(MasterProductCategory $masterProductCategory): SlackMessage
    {
        $type = $masterProductCategory->type->label();
        $url = null;
        if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $url = route('grp.masters.master_shops.show.master_departments.show', [
                $masterProductCategory->masterShop->slug,
                $masterProductCategory->slug
            ]);
        } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $url = route('grp.masters.master_shops.show.master_sub_departments.show', [
                $masterProductCategory->masterShop->slug,
                $masterProductCategory->slug
            ]);
        } else {
            $url = route('grp.masters.master_shops.show.master_families.show', [
                $masterProductCategory->masterShop->slug,
                $masterProductCategory->slug
            ]);
        }

        return (new SlackMessage())
             ->headerBlock("*New Master $type Created")
             ->sectionBlock(function (SectionBlock $block) use ($masterProductCategory, $type) {
                 $block->field("*$type Code:*\n$masterProductCategory->code")->markdown();

                 $block->field("*$type Name:*\n$masterProductCategory->name")->markdown();
             })
             ->dividerBlock()
             ->actionsBlock(function (ActionsBlock $block) use ($url) {
                 $block->button('View')->primary()->url($url);
             })
             ->dividerBlock()
            ->sectionBlock(function (SectionBlock $block) use ($masterProductCategory, $type) {
                $block->text("*New $type Created in Shops");

                foreach ($masterProductCategory->productCategories as $productCategory) {
                    $block->field("{$productCategory->shop->code}")->markdown();

                    $block->field("{$productCategory->name}")->markdown();
                }
            })
             ->dividerBlock()
            ->actionsBlock(function (ActionsBlock $block) use ($masterProductCategory) {
                foreach ($masterProductCategory->productCategories as $productCategory) {
                    if (!$productCategory->webpage) {
                        continue;
                    }

                    $url = 'https://' . $productCategory->webpage->website->domain . '/';
                    if ($productCategory->department && $productCategory->department->url) {
                        $url .= $productCategory->department->url . '/';
                    }
                    if ($productCategory->subDepartment && $productCategory->subDepartment->url) {
                        $url .= $productCategory->subDepartment->url . '/';
                    }
                    $url .= $productCategory->url;

                    $block->button("{$productCategory->shop->code}")->url($url);
                }

            })
             ->dividerBlock()
             ->contextBlock(function (ContextBlock $block) use ($masterProductCategory) {
                 $timestamp = $masterProductCategory->created_at ?? now();
                 $block->text("Created on $timestamp");
             });
    }
}
