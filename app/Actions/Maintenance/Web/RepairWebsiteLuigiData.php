<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:58:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Web\Website\Luigi\WithLuigis;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairWebsiteLuigiData
{
    use WithActionUpdate;
    use WithOrganisationSource;
    use WithLuigis;


    /**
     * @throws \Exception
     */
    public function handle(Website $website, Command $command): void
    {
        $accessToken = $this->getAccessToken($website);
        if (count($accessToken) < 2) {
            return;
        }


        $mibId = DB::table('webpages')->where('website_id', $website->id)->min('id');
        $maxId = DB::table('webpages')->where('website_id', $website->id)->max('id');

        if (is_null($mibId) || is_null($maxId)) {
            // No webpages for this website
            return;
        }

        for ($id = $mibId; $id <= $maxId; $id++) {
            $existInOtherWebsite = DB::table('webpages')->where('id', $id)->where('website_id', '!=', $website->id)->exists();
            if ($existInOtherWebsite) {
                continue;
            }


            $webpage = Webpage::query()
                ->where('id', $id)
                ->where('state', WebpageStateEnum::LIVE)
                ->whereIn('type', [WebpageTypeEnum::CATALOGUE, WebpageTypeEnum::BLOG])
                ->whereIn('model_type', ['Product', 'ProductCategory', 'Collection'])
                ->where('website_id', $website->id)
                ->first();

            if (!$webpage) {
                $oldIdentity = "$website->group_id:$website->organisation_id:{$website->shop->id}:$website->id:$id";

                $command->info("deleting object $oldIdentity");

                $this->deleteContentManual(
                    $website,
                    [
                        "identity" => $oldIdentity,
                        "type"     => "item",
                    ]
                );
            }
        }
    }


    public string $commandSignature = 'repair:website_luigi_data {website?}';

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {
        if ($command->argument('website')) {
            $website = Website::find($command->argument('website'));
            $this->handle($website, $command);
        } else {
            $count = Website::where('migrated', true)->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            Website::where('migrated', true)->orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar, $command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                        $bar->advance();
                    }
                });
        }
    }

}
