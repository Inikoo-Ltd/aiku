<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 12 Oct 2022 17:56:45 Central European Summer Time, Benalmádena, Malaga Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\StoreWebsite;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FetchAuroraWebsites extends FetchAuroraAction
{
    use WithAuroraParsers;
    use WithShopSetOutboxesSourceId;

    public string $commandSignature = 'fetch:websites {organisations?*} {--s|source_id=} {--d|db_suffix=}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Website
    {
        if ($websiteData = $organisationSource->fetchWebsite($organisationSourceId)) {
            if ($website = Website::where('source_id', $websiteData['website']['source_id'])
                ->first()) {


                if ($website->shop->type == ShopTypeEnum::FULFILMENT) {
                    // Do no process fulfilment websites (Level 2)
                    return $website;
                }

                $website = UpdateWebsite::make()->action(
                    website: $website,
                    modelData: $websiteData['website'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } else {
                $website = StoreWebsite::make()->action(
                    shop: $websiteData['shop'],
                    modelData: $websiteData['website'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                Website::enableAuditing();
                $this->saveMigrationHistory(
                    $website,
                    Arr::except($websiteData['website'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );


                $this->recordNew($organisationSource);
                $sourceData = explode(':', $website->source_id);
                DB::connection('aurora')->table('Website Dimension')
                    ->where('Website Key', $sourceData[1])
                    ->update(['aiku_id' => $website->id]);

                if ($websiteData['launch']) {
                    LaunchWebsite::run(website: $website);
                }
            }


            if ($website->state == WebsiteStateEnum::LIVE) {
                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->storefront, 'home.sys');


                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->storefront, 'home_logout.sys');

                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::CONTACT)->first(), 'contact.sys');
                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::ABOUT_US)->first(), 'about.sys');
                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::CHECKOUT)->first(), 'checkout.sys');
                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::BASKET)->first(), 'basket.sys');

                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::SHIPPING)->first(), 'shipping.sys');
                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::TERMS_AND_CONDITIONS)->first(), 'tac.sys');


                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::LOGIN)->first(), 'login.sys');
                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::REGISTER)->first(), 'register.sys');
                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::CATALOGUE)->first(), 'catalogue.sys');

                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::RETURNS)->first(), 'returns');
                $result = $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::SHOWROOM)->first(), 'showroom');
                if (!$result) {
                    $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::SHOWROOM)->first(), 'showroom.sys');
                }

                $result = $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::COOKIES_POLICY)->first(), 'cookie_policy');
                if (!$result) {
                    $result = $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::COOKIES_POLICY)->first(), 'cookies_policy');
                }
                if (!$result) {
                    $result = $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::COOKIES_POLICY)->first(), 'cookiespolicy');
                }


                if (!$result) {
                    $result = $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::COOKIES_POLICY)->first(), 'cookies');
                }
                if (!$result) {
                    $result = $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::COOKIES_POLICY)->first(), 'cookie');
                }
                if (!$result) {
                    $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::COOKIES_POLICY)->first(), 'cookie.sys');
                }


                $result = $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::PRIVACY)->first(), 'privacy');
                if (!$result) {
                    $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::PRIVACY)->first(), 'privacy_policy');
                }
                if (!$result) {
                    $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::PRIVACY)->first(), 'integritet');
                }

                $this->saveFixedWebpageMigrationData($organisationSource, $website, $website->webpages()->where('sub_type', WebpageSubTypeEnum::PRICING)->first(), 'pricing');
            }

            /** @var Webpage $webpage */
            foreach ($website->webpages()->where('is_fixed', true)->get() as $webpage) {

                if($webpage->allow_fetch){
                    FetchAuroraWebBlocks::run($organisationSource, $webpage, reset: true, dbSuffix: $this->dbSuffix);
                    PublishWebpage::make()->action(
                        $webpage,
                        [
                            'comment' => 'Initial publish after migration',
                        ]
                    );
                }


            }


            $this->setShopSetOutboxesSourceId($website->shop);

            return $website;
        }

        return null;
    }


    protected function saveFixedWebpageMigrationData(SourceOrganisationService $organisationSource, Website $website, ?Webpage $webpage, $code): bool
    {
        if (!$webpage) {
            return false;
        }

        $sourceData = explode(':', $website->source_id);

        $auroraModelData = DB::connection('aurora')
            ->table('Page Store Dimension')->where('Webpage Website Key', $sourceData[1])
            ->where('Webpage Code', $code)->first();


        if ($auroraModelData) {
            $firstTime = $webpage->source_id == null;

            $webpage->updateQuietly(
                [
                    'source_id' => $website->organisation->id.':'.$auroraModelData->{'Page Key'},
                ]
            );

            if (!$webpage->fetched_at) {
                $webpage->updateQuietly(['fetched_at' => now()]);
            }


            $webpage = FetchAuroraWebpages::run($organisationSource, $auroraModelData->{'Page Key'});


            if ($webpage) {
                if ($firstTime) {
                    $this->saveMigrationHistory(
                        $webpage,
                        [
                            'code' => $webpage->code,
                            'title' => $webpage->title,
                        ]
                    );
                }


                DB::connection('aurora')->table('Page Store Dimension')
                    ->where('Page Key', $auroraModelData->{'Page Key'})
                    ->update(['aiku_id' => $webpage->id]);
            }


            return true;
        }

        return false;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Website Dimension')
            ->select('Website Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Website Dimension')->count();
    }
}
