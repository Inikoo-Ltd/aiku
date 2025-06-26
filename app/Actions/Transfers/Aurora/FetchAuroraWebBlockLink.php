<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Transfers\Aurora;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Web\Website;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\AuroraOrganisationService;
use App\Transfers\SourceOrganisationService;
use App\Transfers\WowsbarOrganisationService;
use Illuminate\Support\Facades\DB;

class FetchAuroraWebBlockLink extends OrgAction
{
    use WithAuroraParsers;
    use WithOrganisationSource;

    protected AuroraOrganisationService|WowsbarOrganisationService|SourceOrganisationService|null $organisationSource = null;

    /**
     * @throws \Exception
     */
    public function handle(SourceOrganisationService $organisationSource, Website $website, $auroraLink): array
    {
        $this->organisationSource = $organisationSource;


        if (!$this->isInternalLink($website, $auroraLink)) {
            $linkData = [
                'type' => 'external',
                'url'  => $auroraLink
            ];
        } else {
            $linkData = [
                'type' => 'internal',
                'id'   => null
            ];

            $auroraLink        = $this->cleanUrl($auroraLink);
            $auroraLink        = str_replace($website->domain, '', $auroraLink);
            $auroraLink        = preg_replace('/^\/+/', "", $auroraLink);
            $dataSource        = explode(':', $website->source_id);
            $auroraWebpageData = DB::connection('aurora')
                ->table('Page Store Dimension')
                ->select('Page Key as source_id')
                ->where('Webpage Website Key', $dataSource[1])
                ->where('Webpage Code', $auroraLink)
                ->orWhere('Webpage Canonical Code', $auroraLink)
                ->first();


            //todo look for webpage also in Page Redirection Dimension

            $linkedWebpage = false;

            if ($auroraLink == "") {
                $linkedWebpage = $website->storefront;
            } elseif ($auroraWebpageData) {
                $linkedWebpage = $this->parseWebpage($website->organisation_id.':'.$auroraWebpageData->source_id);
            }

            if ($linkedWebpage) {
                data_set($linkData, 'id', $linkedWebpage->id);
                data_set($linkData, 'url', $linkedWebpage->getUrl());
                data_set($linkData, 'workshop_url', route('grp.org.shops.show.web.webpages.workshop', [
                    $linkedWebpage->organisation->slug,
                    $linkedWebpage->shop->slug,
                    $linkedWebpage->website->slug,
                    $linkedWebpage->slug,
                ]));
            } else {
                $linkData = [
                    'type'      => 'external',
                    'url'       => $auroraLink,
                    'error'     => true,
                    'error_msg' => 'Webpage count not be fetched'
                ];
            }
        }

        return $linkData;
    }

    public function isInternalLink($website, $auroraLink): bool
    {
        if (str_starts_with($auroraLink, "tel:")) {
            return false;
        }
        if (str_starts_with($auroraLink, "mailto:")) {
            return false;
        }

        if (!str_starts_with($auroraLink, "http")) {
            return true;
        }
        $domain     = $website->domain;
        $auroraLink = $this->cleanUrl($auroraLink);


        $auroraDomain = preg_replace('/\/.*$/', "", $auroraLink);

        return $domain == $auroraDomain;
    }

    public function cleanUrl($auroraLink): string
    {
        $auroraLink = preg_replace('/^https?:\/\//', "", $auroraLink);
        $auroraLink = preg_replace('/^www\./', "", $auroraLink);

        $auroraLink = preg_replace('/^\/+/', "", $auroraLink);
        $auroraLink = preg_replace('/\/+$/', "", $auroraLink);

        return trim($auroraLink);
    }


}
