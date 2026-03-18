<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 09:47:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInDepartmentsWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(ProductCategory $department, Command $command): void
    {
        $baseQuery = Webpage::where('sub_type', 'department')
            ->where('model_type', class_basename($department))
            ->where('model_id', $department->id);

        // TODO: DELETE FROM HERE LATER ONCE DONE
        $wrongWebpage = $baseQuery
            ->clone()
            ->where('url', strtolower($department->code) . '-alt')
            ->first();

        if ($wrongWebpage) {
            DeleteWebpage::run($wrongWebpage);
        }
        // TODO: UP UNTIL HERE 

        $hasOverviewPage = $baseQuery
            ->clone()
            ->where('layout_style', 'families-overview')
            ->exists();

        if (!$hasOverviewPage) {
            $webpageData = [
                'title'         => $department->name,
                'code'          => $department->code . '-overview',
                'url'           => strtolower($department->code) . '-overview',
                'sub_type'      => WebpageSubTypeEnum::DEPARTMENT,
                'type'          => WebpageTypeEnum::CATALOGUE,
                'model_type'    => class_basename($department),
                'model_id'      => $department->id,
                'layout_style'  => 'families-overview'
            ];

            StoreWebpage::make()->action($department->shop->website, $webpageData);
        }

        $webpages = $baseQuery->get();

        foreach ($webpages as $webpage) {
            $this->processDepartmentWebpages($webpage, $command, $webpage->layout_style);
        }
    }

    protected function processDepartmentWebpages(Webpage $webpage, Command $command, $layout_style = 'main_page'): void
    {
        /** @var ProductCategory $department */
        $department = $webpage->model;

        if ($layout_style == 'main_page') {
            
            // Layout for Main Page

            $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'department');
            if (count($countFamilyWebBlock) > 0) {
                foreach ($countFamilyWebBlock as $webBlockData) {
                    DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                    DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();
    
                    DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                    DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();
    
                    DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
                }
            }
    
            $collectionsWebBlock = $this->getWebpageBlocksByType($webpage, 'collections-1');
            if (count($collectionsWebBlock) > 0) {
                foreach ($collectionsWebBlock as $webBlockData) {
                    DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                    DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();
    
                    DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                    DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();
    
                    DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
                }
            }
    
            // NEW LOGIC, PREVENT MULTIPLE SAME SCOPED WEB BLOCK UNDER SAME PAGE (HANDLES TEMPLATES)
            $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::SUB_DEPARTMENTS->templateCodes(), WebBlockTemplateEnum::SUB_DEPARTMENTS);
    
            $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'overview_aurora');
    
            if (count($countFamilyWebBlock) > 0) {
                foreach ($countFamilyWebBlock as $webBlockData) {
                    $layout       = json_decode($webBlockData->layout, true);
                    $descriptions = Arr::get($layout, 'data.fieldValue.texts.values');
    
                    $description = '';
                    foreach ($descriptions as $descriptionData) {
                        $text = Arr::get($descriptionData, 'text');
                        if ($text) {
                            $description .= $text.' ';
                        }
                    }
                    $description = trim($description);
    
    
                    if ($description) {
                        $command->line('D: '.$department->id.' Department description updated');
                        $department->update(['description' => $description]);
                    }
    
                    DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                    DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();
    
                    DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                    DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();
    
                    DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
                }
            }
    
            $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes(), WebBlockTemplateEnum::LIST_PRODUCTS);
    
            $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::FAMILIES->templateCodes(), WebBlockTemplateEnum::FAMILIES);
    
            $countDepartmentDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'department-description-1');
            if (count($countDepartmentDescriptionBlock) == 0) {
                $this->createWebBlock($webpage, 'department-description-1');
            }
    
            $webpage->refresh();
    
            if (count($countDepartmentDescriptionBlock) == 0) {
                $this->setDescriptionWebBlockOnTop($webpage);
            }
            if ($command->option('hide-description')) {
                $this->setDescriptionWebBlockHidden($webpage);
            }
            $webpage->refresh();
        } else { 
            $this->deleteWebBlocksByCode($webpage, 'families-2');
            // Layout for Overview Page
            $countDepartmentDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'department-description-1');
            if (count($countDepartmentDescriptionBlock) == 0) {
                $this->createWebBlock($webpage, 'department-description-1');
            }

            $countDepartmentDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'families-1-overview');
            if (count($countDepartmentDescriptionBlock) == 0) {
                $this->createWebBlock($webpage, 'families-1-overview');
            }
    
            $webpage->refresh();
    
            if (count($countDepartmentDescriptionBlock) == 0) {
                $this->setDescriptionWebBlockOnTop($webpage);
            }
            if ($command->option('hide-description')) {
                $this->setDescriptionWebBlockHidden($webpage);
            }
            $webpage->refresh();

        }


        UpdateWebpageContent::run($webpage);
        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";


        if ($webpage->is_dirty) {
            if (in_array($department->state, [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])) {
                $command->line('Webpage '.$webpage->code.' is dirty, publishing after upgrade');
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'publish after upgrade',
                    ]
                );
            }
        }
    }

    public function setDescriptionWebBlockOnTop(Webpage $webpage): void
    {
        $departmentDescriptionWebBlock = $this->getWebpageBlocksByType($webpage, 'department-description-1')->first()->model_has_web_blocks_id;
        $webBlocks                     = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();

        $runningPosition = 2;
        foreach ($webBlocks as $key => $position) {
            if ($key == $departmentDescriptionWebBlock) {
                $webBlocks[$key] = 1;
            } else {
                $webBlocks[$key] = $runningPosition;
                $runningPosition++;
            }
        }

        foreach ($webBlocks as $key => $position) {
            DB::table('model_has_web_blocks')
                ->where('id', $key)
                ->update(['position' => $position]);
        }
        UpdateWebpageContent::run($webpage);
    }

    public function setDescriptionWebBlockHidden(Webpage $webpage): void
    {
        $departmentDescriptionWebBlock = $this->getWebpageBlocksByType($webpage, 'department-description-1')->first();

        if ($departmentDescriptionWebBlock) {
            DB::table('model_has_web_blocks')
                ->where('id', $departmentDescriptionWebBlock->model_has_web_blocks_id)
                ->update(['show' => false]);
        }

        UpdateWebpageContent::run($webpage);
    }

    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_departments_webpages {website?} {--hide-description}';

    public function asCommand(Command $command): void
    {
        $shop = null;
        if ($command->argument('website')) {
            $website   = Website::where('slug', $command->argument('website'))->first();
            $shop = $website->shop;
        } else {

        }

        $departmentIds = DB::table('product_categories')
            ->leftJoin('shops', 'shops.id', 'product_categories.shop_id')
            ->select('product_categories.id')
            ->where('product_categories.type', 'department')
            ->when(
                $shop,
                fn ($q) => $q->where('product_categories.shop_id', $shop->id),
                fn ($q) => $q->where('shops.state', 'open')
            )
            ->whereNull('product_categories.deleted_at')
            ->get();



        foreach ($departmentIds as $departmentId) {
            $department = ProductCategory::find($departmentId->id);
            if ($department) {
                $this->handle($department, $command);
            }
        }
    }

}
