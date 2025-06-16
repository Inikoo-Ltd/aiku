<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;

class FetchAuroraWebpage extends FetchAurora
{
    public function fetch(int $id): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        $this->parseModel();

        return $this->parsedData;
    }

    protected function parseModel(): void
    {
        if (!$this->auroraModelData) {
            return;
        }


        if (in_array($this->auroraModelData->{'Webpage Scope'}, ['Product', 'Category Products', 'Category Categories']) and $this->auroraModelData->{'Webpage Scope Key'} == '') {
            return;
        }


        if (preg_match('/\.sys$/', $this->auroraModelData->{'Webpage Code'})) {
            if (!in_array($this->auroraModelData->{'Webpage Code'}, [
                'home.sys',
                'home_logout.sys',
                'contact.sys',
                'tac.sys',
                'shipping.sys',
                'about.sys'
            ])) {
                return;
            }
        }
        if (preg_match('/^web\./i', $this->auroraModelData->{'Webpage Code'})) {
            return;
        }

        if (preg_match('/^fam\./i', $this->auroraModelData->{'Webpage Code'})) {
            return;
        }

        if (preg_match('/^dept\./i', $this->auroraModelData->{'Webpage Code'})) {
            return;
        }

        if (preg_match('/blog/i', $this->auroraModelData->{'Webpage Code'})) {
            return;
        }

        $website = $this->parseWebsite($this->organisation->id.':'.$this->auroraModelData->{'Webpage Website Key'});

        if ($website->shop->type == ShopTypeEnum::FULFILMENT || $website->shop->type == ShopTypeEnum::DROPSHIPPING) {
            return;
        }


        $parsedData = $this->processAuroraWebpage($this->organisation, $website, $this->auroraModelData);
        if (!$parsedData) {
            return;
        }



        $website = $parsedData['website'];




        if ($website->shop->type == ShopTypeEnum::FULFILMENT and
            $parsedData['webpage']['code'] == 'shipping.sys') {
            $parsedData['webpage']['code'] = 'shipping';
        }


        $this->parsedData['website'] = $parsedData['website'];
        $this->parsedData['webpage'] = $parsedData['webpage'];

        if ($this->auroraModelData->{'Webpage Code'} == 'home.sys') {
            $this->parsedData['is_home_logged_in'] = true;
        } elseif ($this->auroraModelData->{'Webpage Code'} == 'home_logout.sys') {
            $this->parsedData['is_home_logged_out'] = true;
        }

        $this->parsedData['last_published'] = $this->auroraModelData->{'Webpage Last Launch Date'};
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Page Store Dimension')
            ->where('aiku_ignore', 'No')
            ->where('Page Key', $id)->first();
    }

    public function processAuroraWebpage(Organisation $organisation, Website $website, $auroraModelData): array|null
    {

        if ($website->state == WebsiteStateEnum::CLOSED) {
            $status = WebpageStateEnum::CLOSED;
        } elseif ($website->state == WebsiteStateEnum::IN_PROCESS) {
            $status = match ($auroraModelData->{'Webpage State'}) {
                'Online' => WebpageStateEnum::READY,
                'Offline' => WebpageStateEnum::CLOSED,
                default => WebpageStateEnum::IN_PROCESS,
            };
        } else {
            $status = match ($auroraModelData->{'Webpage State'}) {
                'Online' => WebpageStateEnum::LIVE,
                'Offline' => WebpageStateEnum::CLOSED,
                default => WebpageStateEnum::IN_PROCESS,
            };
        }

        $url = $this->cleanWebpageCode($auroraModelData->{'Webpage Code'});

        $subType = match ($auroraModelData->{'Webpage Scope'}) {
            'Homepage', 'HomepageLogout', 'HomepageToLaunch' => WebpageSubTypeEnum::STOREFRONT,
            'Product' => WebpageSubTypeEnum::PRODUCT,
            'Category Products' => WebpageSubTypeEnum::FAMILY,
            'Category Categories' => WebpageSubTypeEnum::DEPARTMENT,
            'Register' => WebpageSubTypeEnum::REGISTER,
            'Login', 'ResetPwd' => WebpageSubTypeEnum::LOGIN,
            'TandC' => WebpageSubTypeEnum::TERMS_AND_CONDITIONS,
            'Basket', 'Top_Up', => WebpageSubTypeEnum::BASKET,
            'Checkout' => WebpageSubTypeEnum::CHECKOUT,
            default => WebpageSubTypeEnum::CONTENT,
        };

        $type = match ($auroraModelData->{'Webpage Scope'}) {
            'Homepage', 'HomepageLogout', 'HomepageToLaunch' => WebpageTypeEnum::STOREFRONT,
            'Product', 'Category Categories', 'Category Products' => WebpageTypeEnum::CATALOGUE,
            'Register', 'Login', 'ResetPwd', 'Basket', 'Top_Up', 'Checkout' => WebpageTypeEnum::OPERATIONS,
            'TandC' => WebpageTypeEnum::INFO,
            default => WebpageTypeEnum::CONTENT,
        };


        $model = null;


        if ($auroraModelData->{'Webpage Scope'} == 'Category Products') {
            $model = $this->parseFamily($organisation->id.':'.$auroraModelData->{'Webpage Scope Key'});
            if (!$model) {
                $model = $this->parseCollection($organisation->id.':'.$auroraModelData->{'Webpage Scope Key'});
            }


            if (!$model) {
                return null;
            }



        } elseif ($auroraModelData->{'Webpage Scope'} == 'Category Categories') {
            $model = $this->parseDepartment($organisation->id.':'.$auroraModelData->{'Webpage Scope Key'});
            if (!$model) {
                $model = $this->parseCollection($organisation->id.':'.$auroraModelData->{'Webpage Scope Key'});
            }
            if (!$model) {
                return null;
            }
        } elseif ($auroraModelData->{'Webpage Scope'} == 'Product') {
            $model = $this->parseProduct($organisation->id.':'.$auroraModelData->{'Webpage Scope Key'});
            if (!$model) {
                return null;
            }
        }


        $migrationData = null;
        if ($auroraModelData->{'Page Store Content Published Data'}) {
            $migrationData = json_decode($auroraModelData->{'Page Store Content Published Data'}, true);
        }

        // print ">>> $url <<<\n";

        $title = trim($auroraModelData->{'Webpage Name'});
        if ($title == '') {
            $title = $auroraModelData->{'Webpage Code'};
        }


        switch ($type) {
            case WebpageTypeEnum::CATALOGUE:
                if ($subType == WebpageSubTypeEnum::PRODUCT) {
                    $parentId = $website->products_id;
                } elseif ($subType == WebpageSubTypeEnum::FAMILY) {
                    $parentId = $website->catalogue_id;
                    /** @var ProductCategory $department */
                    $department = $model->department;
                    if ($department) {
                        $departmentSourceData        = explode(':', $department->source_department_id);
                        $auroraDepartmentWebpageData = DB::connection('aurora')->table('Page Store Dimension')
                            ->select('Page Key')
                            ->where('Webpage Scope', 'Category Categories')
                            ->where('Webpage Scope Key', $departmentSourceData[1])
                            ->first();
                        if ($auroraDepartmentWebpageData) {
                            $departmentWebpage = $this->parseWebpage($this->organisation->id.':'.$auroraDepartmentWebpageData->{'Page Key'});


                            if ($departmentWebpage) {
                                $parentId = $departmentWebpage->id;
                            } else {
                                print "error can not fetch department webpage\n";
                            }
                        }
                    }
                } else {
                    $parentId = $website->catalogue_id;
                }

                break;
            default:

                $parentId = $website->storefront_id;
                break;
        }


        if (strtolower($url) == 'products') {
            $url = 'products-showcase';
        }


        $webpage =
            [
                'parent_id'       => $parentId,
                'code'            => $url,
                'title'           => $title,
                'description'     => (string)$auroraModelData->{'Webpage Meta Description'},
                'url'             => strtolower($url),
                'state'           => $status,
                'sub_type'        => $subType,
                'type'            => $type,
                'fetched_at'      => now(),
                'last_fetched_at' => now(),
                'source_id'       => $organisation->id.':'.$auroraModelData->{'Page Key'},

            ];

        if ($migrationData) {
            if ($auroraModelData->{'Webpage Code'} == 'home.sys') {
                $webpage['migration_data'] = [
                    'loggedIn' => $migrationData
                ];
            } elseif ($auroraModelData->{'Webpage Code'} == 'home_logout.sys') {
                $webpage['migration_data'] = [
                    'loggedOut' => $migrationData
                ];
            } else {
                $webpage['migration_data'] = [
                    'both' => $migrationData
                ];
            }
        }


        if ($createdAt = $this->parseDatetime($auroraModelData->{'Webpage Creation Date'})) {
            $webpage['created_at'] = $createdAt;
        }

        if ($model) {
            $webpage['model_type'] = class_basename($model);
            $webpage['model_id']   = $model->id;
        }


        return [
            'website' => $website,
            'webpage' => $webpage
        ];
    }

}
