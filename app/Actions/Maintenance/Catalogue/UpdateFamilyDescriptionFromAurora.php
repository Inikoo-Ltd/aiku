<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 Oct 2025 18:13:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateFamilyDescriptionFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Shop $shop): void
    {
        $organisation             = $shop->organisation;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);


        ProductCategory::query()
            ->where(
                'shop_id',
                $shop->id
            )
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->whereNotNull('source_family_id')

          //    ->where('id',31890)
            ->orderBy('id')
            ->chunkById(1000, function ($families) {
                foreach ($families as $family) {
                    $description = '';


                    if ($family && $family->webpage && $family->webpage->source_id) {
                        $sourceData  = explode(':', $family->webpage->source_id);
                        $webpageData = DB::connection('aurora')->table('Page Store Dimension')->where('Page Key', $sourceData[1])->first();
                        if ($webpageData) {
                            $webpageData = $webpageData->{'Page Store Content Published Data'};
                            if ($webpageData) {
                                $webpageData    = json_decode($webpageData, true);
                                $blackBoardData = null;
                                $text           = '';


                                foreach ($webpageData['blocks'] as $block) {
                                    if (Arr::get($block, 'type') == 'blackboard') {
                                        $blackBoardData = $block;
                                        break;
                                    } elseif (Arr::get($block, 'type') == 'text') {
                                        foreach (Arr::get($block, 'text_blocks', []) as $textBlock) {
                                            $text .= $textBlock['text'].' ';
                                        }
                                    }
                                }

                                if ($blackBoardData) {
                                    foreach ($blackBoardData['texts'] as $text) {
                                        $description .= $text['text'].' ';
                                    }
                                    // Replace all instances of <p><br></p> (and the self-closing variant) with empty string
                                    $description = str_replace('<p><br></p>', '', $description);
                                    $description = str_replace('<p><br />\u003C/p>', '', $description); // handle potential escaped variant
                                    $description = str_replace('<p><br /></p>', '', $description);
                                }

                                if (!$description) {
                                    $description = $text;
                                }
                            }
                        }
                    }


                    if ($description) {
                        $masterCategory = $family->masterProductCategory;

                        //  dd($family->shop->language->code);
                        print "==== $family->id $family->slug    $masterCategory?->slug    ==============\n";

                        $family->update(['description' => $description]);
                        $family
                            ->setTranslation('description_i8n', $family->shop->language->code, $description)
                            ->save();

                        $family->update(['description_extra' => '']);
                        $family
                            ->setTranslation('description_extra_i8n', $family->shop->language->code, '')
                            ->save();
                        $family
                            ->setTranslation('description_title_i8n', $family->shop->language->code, '')
                            ->save();
                        if ($masterCategory) {
                            $masterCategory
                                ->setTranslation('description_i8n', $family->shop->language->code, $description)
                                ->save();
                            $masterCategory
                                ->setTranslation('description_extra_i8n', $family->shop->language->code, '')
                                ->save();
                            $masterCategory
                                ->setTranslation('description_title_i8n', $family->shop->language->code, '')
                                ->save();
                        }
                    }
                }
            }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:update_family_descriptions_from_aurora {shop_id}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::find($command->argument('shop_id'));

        // try {
        $this->handle($shop);

        //        } catch (Throwable $e) {
        //            $command->error($e->getMessage());
        //            return 1;
        //        }


        return 0;
    }

}
