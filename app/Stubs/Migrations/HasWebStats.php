<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Mar 2023 04:45:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\Webpage\WebpagePurposeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasWebStats
{
    public function getWebsitesStatsFields(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_websites')->default(0);
        $table->unsignedInteger('number_websites_under_maintenance')->default(0);

        foreach (WebsiteTypeEnum::cases() as $websiteType) {
            $table->unsignedInteger('number_websites_type_'.$websiteType->snake())->default(0);
        }
        foreach (WebsiteStateEnum::cases() as $websiteState) {
            $table->unsignedInteger('number_websites_state_'.$websiteState->snake())->default(0);
        }



        return $table;
    }


    public function getSnapshotsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_snapshots')->default(0);
        foreach (SnapshotStateEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_snapshots_state_'.$case->snake())->default(0);
        }
        return $table;
    }

    public function getWebpagesStatsFields(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_webpages')->default(0);
        foreach (WebpageStateEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_webpages_state_'.$case->snake())->default(0);
        }
        foreach (WebpageTypeEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_webpages_type_'.$case->snake())->default(0);
        }
        foreach (WebpagePurposeEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_webpages_purpose_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function getWebUsersStatsFields(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_web_users')->default(0);
        $table->unsignedInteger('number_current_web_users')->default(0);

        return $table;
    }

    public function getDeploymentsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_deployments')->default(0);
        $table->dateTimeTz('last_deployed_at')->nullable();

        return $table;
    }

    public function getContentBlocksFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_blocks')->default(0);
        $table->unsignedInteger('number_visible_blocks')->default(0);
        $table->unsignedInteger('number_published_blocks')->default(0);

        return $table;
    }

}
