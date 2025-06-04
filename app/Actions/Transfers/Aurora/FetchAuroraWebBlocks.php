<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Oct 2024 16:16:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Web\ExternalLink\AttachExternalLinkToWebBlock;
use App\Actions\Web\ExternalLink\CheckExternalLinkStatus;
use App\Actions\Web\WebBlock\DeleteWebBlock;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Str;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Traits\WebBlocks\WithFetchCTAWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchFamilyWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchIFrameWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchOverviewWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchProductsWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchProductWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchScriptWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchSeeAlsoWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchTextWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchDepartmentWebBlock;
use App\Actions\Traits\WebBlocks\WithFetchImagesWebBlock;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Web\ExternalLink\StoreExternalLink;
use App\Actions\Web\WebBlock\StoreWebBlock;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use App\Transfers\AuroraOrganisationService;
use App\Transfers\WowsbarOrganisationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FetchAuroraWebBlocks
{
    use WithAuroraParsers;
    use WithAuroraOrganisationsArgument;
    use WithOrganisationSource;
    use WithFetchTextWebBlock;
    use WithFetchImagesWebBlock;
    use WithFetchIFrameWebBlock;
    use WithFetchProductWebBlock;
    use WithFetchOverviewWebBlock;
    use WithFetchCTAWebBlock;
    use WithFetchSeeAlsoWebBlock;
    use WithFetchProductsWebBlock;
    use WithFetchFamilyWebBlock;
    use WithFetchScriptWebBlock;
    use WithFetchDepartmentWebBlock;


    protected AuroraOrganisationService|WowsbarOrganisationService|SourceOrganisationService|null $organisationSource = null;

    private string $dbSuffix;

    /**
     * @throws \Exception
     */
    public function handle(SourceOrganisationService $organisationSource, Webpage $webpage, $reset = false, $dbSuffix = ''): Webpage
    {

        $shop = $webpage->shop;
        if ($shop->type == ShopTypeEnum::FULFILMENT || $shop->type == ShopTypeEnum::DROPSHIPPING) {
            return $webpage;
        }



        $this->dbSuffix = $dbSuffix;

        $this->organisationSource = $organisationSource;


        if ($reset) {
            $this->reset($webpage);
        }

        $oldMigrationsChecksum = $webpage->webBlocks()->get()->pluck('migration_checksum', 'migration_checksum')->toArray();

        if (isset($webpage->migration_data)) {
            $migrationTypes = ['both', 'loggedIn', 'loggedOut'];



            $allMigrationsDataByType = [];
            foreach ($migrationTypes as $type) {
                if (isset($webpage->migration_data[$type])) {
                    if (isset($webpage->migration_data[$type]['blocks'])) {
                        $allMigrationsDataByType[$type] = $this->getMigrationData($webpage, $webpage->migration_data[$type]['blocks'], $type);
                    }
                }
            }

            $migrationsData = [];
            foreach ($allMigrationsDataByType as $type => $migrationData) {
                foreach ($migrationData as $data) {
                    $checksum = $data['migrationChecksum'];
                    if ($type == 'both') {
                        $migrationsData[$checksum]                            = $data;
                        $migrationsData[$checksum]['visibility']['loggedIn']  = true;
                        $migrationsData[$checksum]['visibility']['loggedOut'] = true;
                        continue;
                    }
                    $isLoggedIn = $type == 'loggedIn';
                    if (!isset($migrationsData[$checksum])) {
                        $migrationsData[$checksum]                            = $data;
                        $migrationsData[$checksum]['visibility']['loggedIn']  = $isLoggedIn;
                        $migrationsData[$checksum]['visibility']['loggedOut'] = !$isLoggedIn;
                    } else {
                        $migrationsData[$checksum][$type] = true;
                    }
                }
            }


            $this->processMigrationsData($migrationsData, $oldMigrationsChecksum);
        }


        if (count($oldMigrationsChecksum) > 0) {
            $this->deleteWebBlockHaveOldChecksum($webpage, $oldMigrationsChecksum);
        }

        return $webpage;
    }

    private function getMigrationData($webpage, array $blocks, $type): array
    {
        $migrationData = [];
        foreach ($blocks as $index => $auroraBlock) {
            $migrationChecksum = md5(json_encode($auroraBlock));
            $loggedInStatus    = $type === 'loggedIn';
            $migrationData[]   = [
                'visibility'        => [
                    'loggedIn'  => $loggedInStatus,
                    'loggedOut' => !$loggedInStatus,
                ],
                'webpage'           => $webpage,
                'auroraBlock'       => $auroraBlock,
                'position'          => $index + 1,
                'migrationChecksum' => $migrationChecksum,
            ];
        }

        return $migrationData;
    }

    /**
     * @throws \Exception
     */
    private function processMigrationsData(array $migrationsData, array &$oldMigrationsChecksum): void
    {
        // the position will duplicate example: 1,1
        // because there type of loggedIn and loggedOut, if continue with a new position, the loggedOut will after the loggedIn webBlock
        foreach ($migrationsData as $checksum => $migrationData) {
            if (isset($oldMigrationsChecksum[$checksum])) {
                $modelHasWebBlocks = DB::table("model_has_web_blocks")->where('migration_checksum', $checksum);
                $modelHasWebBlocks->update(['position' => $migrationData['position']]);
                unset($oldMigrationsChecksum[$checksum]);
            } else {
                $this->processData($migrationData['webpage'], $migrationData['auroraBlock'], $migrationData['migrationChecksum'], $migrationData['position'], $migrationData['visibility']);
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function processData(
        Webpage $webpage,
        $auroraBlock,
        $migrationChecksum,
        int $position,
        $visibility = ["loggedIn" => true, "loggedOut" => true]
    ): void {
        $models = [];
        $group  = $webpage->group;



        //print "***>>".$auroraBlock["type"]."<<<***\n";

        switch ($auroraBlock["type"]) {
            case "images":
                $webBlockType = $group->webBlockTypes()->where("code", "images")->first();
                $layout       = $this->processImagesData($webpage, $auroraBlock);
                break;
            case "text": // -> need a new web block type for text column/array of a text, this can't store the array of a text for images
                if ($template = $this->getTemplateTextColumn($auroraBlock)) {
                    $webBlockType = $group->webBlockTypes()->where("slug", "text-column")->first();
                    $layout       = $this->processTextColumnData($webpage, $auroraBlock, $template);
                } else {
                    $webBlockType = $group->webBlockTypes()->where("slug", "text")->first();
                    $layout       = $this->processTextData($webpage, $auroraBlock);
                }
                break;
            case "telephone":
                $webBlockType = $group->webBlockTypes()->where("slug", "text")->first();
                $layout       = $this->processPhoneData($auroraBlock);
                break;
            case "code":
            case "reviews":
                $webBlockType = $group->webBlockTypes()->where("slug", "script")->first();
                $layout       = $this->processScriptData($auroraBlock);
                break;
            case "map":
            case "iframe":
                $webBlockType = $group->webBlockTypes()->where("slug", "iframe")->first();
                $layout       = $this->processIFrameData($auroraBlock);
                break;
            case "product":
                $webBlockType = $group->webBlockTypes()->where("slug", "product-1")->first();
                $layout       = $this->processProductData($auroraBlock);


                $fieldValue = Arr::get($webBlockType->data, 'fieldValue');

                data_set($layout, 'data.fieldValue', $fieldValue);


                /** @var Product $product */
                $product = $webpage->model;

                $product->update(
                    [
                        'description' => Arr::get($layout, 'data.fieldValue.value.text')
                    ]
                );

                $models[]     = $product;
                break;

            case "category_products_XX":
                $webBlockType = $group->webBlockTypes()->where("slug", "family")->first();
                $models[]     = ProductCategory::find($webpage->model_id);
                $layout       = $this->processFamilyData($webpage, $auroraBlock);
                break;

            case "see_also":
                $webBlockType = $group->webBlockTypes()->where("slug", "see_also")->first();
                $productsId   = [];
                $categoriesId = [];
                foreach ($auroraBlock["items"] as $item) {
                    if ($item['type'] == "product") {
                        if ($item['product_id']) {
                            $productsId[] = $item['product_id'];
                        }
                    }
                    if ($item['type'] == 'category') {
                        $categoriesId[] = $item['category_key'];
                    }
                }

                foreach ($productsId as $productId) {
                    $product = $this->parseProduct($webpage->organisation->id.':'.$productId);
                    if ($product) {
                        $models[] = $product;
                    }
                }

                foreach ($categoriesId as $categoryId) {
                    $family = $this->parseFamily($webpage->organisation->id.':'.$categoryId);
                    if ($family) {
                        $models[] = $family;
                    } else {
                        $department = $this->parseDepartment($webpage->organisation->id.':'.$categoryId);
                        if ($department) {
                            $models[] = $department;
                        }
                    }
                }


                $layout = $this->processSeeAlsoData();
                break;

            case "products":
                $webBlockType = $group->webBlockTypes()->where("slug", "products")->first();
                $productsId   = [];
                foreach ($auroraBlock["items"] as $item) {
                    if ($item['type'] == "product") {
                        $productsId[] = $item['product_id'];
                    }
                }
                if (count($productsId) > 0) {
                    foreach ($productsId as $productId) {
                        $product = $this->parseProduct($webpage->organisation->id.':'.$productId);
                        if ($product) {
                            $models[] = $this->parseProduct($webpage->organisation->id.':'.$productId);
                        }
                    }
                }
                $layout = $this->processProductsData($auroraBlock);
                break;

            case "category_categories_XX":
                $webBlockType = $group->webBlockTypes()->where("slug", "department")->first();
                $layout       = $this->processDepartmentData($webpage, $auroraBlock);
                break;

            case "blackboard_XX":
                $webBlockType = $group->webBlockTypes()->where("slug", "overview-aurora")->first();
                $layout       = $this->processOverviewData($webBlockType, $webpage, $auroraBlock);



                break;
            case "button":
                $webBlockType = $group->webBlockTypes()->where("slug", "cta-aurora-1")->first();
                $layout       = $this->processCTAData($webpage, $webBlockType, $auroraBlock);
                break;
            default:
                print ">>>>> ".$webpage->slug."  ".$auroraBlock["type"]."  <<<<<<\n";

                return;
        }

        if ($layout == null) {
            return;
        }

        data_set($layout, "blueprint", Arr::get($webBlockType, "blueprint"));

        // set default properties for web block aurora
        $isDefaultExist = Storage::disk('datasets')->exists('default-properties-web-block-aurora/' . $auroraBlock['type'] . '.json');
        if ($isDefaultExist) {
            $properties = Storage::disk('datasets')->json('default-properties-web-block-aurora/' . $auroraBlock['type'] . '.json');
            data_set($layout, "data.fieldValue.container.properties", $properties);
        }

        $webBlock = StoreWebBlock::make()->action(
            $webBlockType,
            [
                "layout"             => $layout,
                "migration_checksum" => $migrationChecksum,
                "models"             => $models,
            ],
            strict: false
        );

        $modelHasWebBlocksData = [
            'show_logged_in'     => $visibility['loggedIn'],
            'show_logged_out'    => $visibility['loggedOut'],
            "group_id"           => $webpage->group_id,
            "organisation_id"    => $webpage->organisation_id,
            "shop_id"            => $webpage->shop_id,
            "website_id"         => $webpage->website_id,
            "webpage_id"         => $webpage->id,
            "position"           => $position,
            "model_id"           => $webpage->id,
            "model_type"         => class_basename(Webpage::class),
            "web_block_id"       => $webBlock->id,
            "migration_checksum" => $migrationChecksum,
        ];

        if (isset($auroraBlock["show"])) {
            $modelHasWebBlocksData['show'] = boolval($auroraBlock["show"]);
        }


        $webpage->modelHasWebBlocks()->create($modelHasWebBlocksData);

        $this->postExternalLinks($webBlock, $webpage, $layout, Arr::get($modelHasWebBlocksData, 'show', true));
        $this->postProcessLayout($webBlock, $webpage, $layout);

        UpdateWebpageContent::run($webpage->refresh());
        /*   BroadcastPreviewWebpage::dispatch($webpage); */
    }

    private function postExternalLinks(WebBlock $webBlock, Webpage $webpage, &$layout, bool $webBlockShow): void
    {
        $code = $webBlock->webBlockType->code;
        if (!in_array($code, ['text', 'text-column' ,'overview', 'images'])) {
            return;
        }

        $externalLinks = $layout['external_links'] ?? null;
        if ($externalLinks) {
            foreach ($externalLinks as $link) {
                $externalLink = $webpage->group->externalLinks()->where('url', $link)->first();
                if (!$externalLink) {
                    $externalLink = StoreExternalLink::make()->action($webpage->group, [
                        'url'    => $link,
                        'status' => CheckExternalLinkStatus::run($link)
                    ]);
                }


                AttachExternalLinkToWebBlock::make()->action($webpage, $webBlock, $externalLink, [
                    'show' => $webBlockShow // <-- fix this and set the show value depending on if Seb-block is shown or not
                ]);
            }
        }
        data_forget($layout, 'external_links');
    }

    private function postProcessLayout($webBlock, $webpage, &$layout): void
    {
        $code = $webBlock->webBlockType->code;
        if (
            $code == "images"
            || $code == "text"
            || $code == "overview_aurora"
            || $code == "cta_aurora_1"
            || $code == "family"
            || $code == "department"
        ) {
            if ($code == "family_XX") {
                $items  = $layout['data']["fieldValue"]["value"]["items"];
                $addOns = [];
                foreach ($items as $item) {
                    if ($item['type'] == "image") {
                        $imageSource = $this->processImage($webBlock, $item, $webpage);
                        $addOns[]    = ['position' => $item['position'], "type" => $item['type'], "source" => $imageSource];
                    } else {
                        $addOns[] = $item;
                    }
                }
                data_set($layout, "data.fieldValue.value.addOns", $addOns);
                unset($layout['data']["fieldValue"]["value"]["items"]);
            } elseif ($code == "department_XX") {
                $sections = $layout['data']["fieldValue"]["value"]["sections"];
                foreach ($sections as $sectionPosition => $section) {
                    $items = $section['items'];
                    if ($items) {
                        foreach ($items as $index => $item) {
                            if ($item['type'] == "image") {
                                $imageSource             = $this->processImage($webBlock, $item, $webpage);
                                $items[$index]["source"] = $imageSource;
                                unset($items[$index]["aurora_source"]);
                            }
                        }
                        $sections[$sectionPosition]["items"] = $items;
                    }
                }
                data_set($layout, "data.fieldValue.value.sections", $sections);
            } elseif ($code == "text") {
                $text    = $layout['data']['fieldValue']['value'];
                $pattern = '/<img\s+[^>]*src=["\']([^"\']*)["\'][^>]*>/i';

                $text                                  = preg_replace_callback($pattern, function ($match) use ($webBlock, $webpage) {
                    $originalImage = $match[1];
                    $media         = FetchAuroraWebBlockMedia::run($webBlock, $webpage, $originalImage);
                    $imageElement  = $match[0];

                    if ($media) {
                        $image        = $media->getImage();
                        $picture      = GetPictureSources::run($image);
                        $imageUrl     = $picture['original'];
                        $imageElement = preg_replace('/src="([^"]*)"/', 'src="'.$imageUrl.'"', $imageElement);
                        $imageElement = preg_replace("/(fr-fil|fr-dii)/", "", $imageElement); // remove class fr-fil & fr-dii
                    }

                    return $imageElement;
                }, $text);
                $layout['data']['fieldValue']['value'] = $text; // result for image still not found event the imageUrl is not empty

            } elseif ($code == "images") {
                $imgResources = [];
                foreach ($layout['data']["fieldValue"]["value"] as $index => $imageRawData) {
                    $imageSource    = $this->processImage($webBlock, $imageRawData, $webpage);
                    $linkData       = $layout['data']["fieldValue"]["value"][$index]['link_data'];
                    $imgResources[] = ["source" => $imageSource, "link_data" => $linkData];
                    unset($layout['data']["fieldValue"]["value"][$index]);
                }
                // make like this, to set img placed in the correct key
                $layout['data']['fieldValue']['value']["images"]      = $imgResources;
                $layout['data']['fieldValue']['value']["layout_type"] = Arr::get($layout, "data.fieldValue.layout_type");
                Arr::forget($layout, "data.fieldValue.layout_type");
            } elseif ($code == "cta_aurora_1") {
                $imageRawData = Arr::get($layout, 'data.fieldValue.button.container.properties.background.image.original');
                if ($imageRawData) {
                    $imageSource = $this->processImage($webBlock, $imageRawData, $webpage);
                    data_set($layout, 'data.fieldValue.button.container.properties.background.image', $imageSource);
                }
            } elseif ($code == "overview_aurora_XX") {
                $imagesAurora = Arr::get($layout, 'data.fieldValue.images');
                if ($imagesAurora) {
                    $imgSources = [];
                    foreach ($imagesAurora as $imgAurora) {
                        $imgSources[] = [
                            'properties' => $imgAurora['properties'],
                            'source'     => $this->processImage($webBlock, $imgAurora, $webpage)
                        ];
                    }
                    data_set($layout, 'data.fieldValue.images', $imgSources);
                }
            } else {
                foreach ($layout['data']["fieldValue"]["value"] as $key => $container) {
                    if ($key == "images") {
                        foreach ($container as $index => $imageRawData) {
                            $imageSource                                                   = $this->processImage($webBlock, $imageRawData, $webpage);
                            $layout['data']["fieldValue"]["value"][$key][$index]['source'] = $imageSource;
                            unset($layout['data']["fieldValue"]["value"][$key][$index]['aurora_source']);
                        }
                    }
                }
            }
            $webBlock->updateQuietly([
                "layout" => $layout,
            ]);
        }
    }

    private function processImage($webBlock, array|string $imageRawData, $webpage): array|null
    {
        if (!isset($imageRawData["aurora_source"])) {
            return null;
        }
        $auroraImage = $imageRawData["aurora_source"];

        $auroraImage = Str::startsWith($auroraImage, "/") ? $auroraImage : "/".$auroraImage;

        $media = FetchAuroraWebBlockMedia::run($webBlock, $webpage, $auroraImage);

        if ($media == null) {
            return null;
        }

        $image = $media->getImage();

        return GetPictureSources::run($image);
    }

    private function reset(Webpage $webpage): void
    {
        foreach ($webpage->webBlocks as $webBlock) {
            DeleteWebBlock::run($webBlock);
        }
    }


    public function deleteWebBlockHaveOldChecksum(Webpage $webpage, array $oldChecksum): void
    {
        foreach ($webpage->webBlocks as $webBlock) {
            if (isset($oldChecksum[$webBlock->migration_checksum])) {
                DeleteWebBlock::run($webBlock);
            }
        }
    }


}
