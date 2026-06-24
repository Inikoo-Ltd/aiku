<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jun 2026 14:12:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Web\Redirect\StoreRedirectFromWebpage;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAuroraCanonicalAndCreateRedirect
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Website $website, ?Command $command = null): void
    {
        $organisation             = $website->organisation;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);


        Webpage::query()
            ->where('website_id', $website->id)
            ->where('state', WebpageStateEnum::LIVE)
            ->whereNotNull('source_id')

            //    ->where('id',31890)
            ->orderBy('id')
            ->chunkById(1000, function ($webpages) use ($command) {
                foreach ($webpages as $webpage) {
                    $sourceData = explode(':', $webpage->source_id);

                    $auData = DB::connection('aurora')->table('Page Store Dimension')
                        ->where('Page Key', $sourceData[1])
                        ->first();

                    if ($auData) {
                        if ($auData->{'Webpage Canonical Code'} != '') {
                            $auroraCanonical = strtolower($auData->{'Webpage Canonical Code'});

                            if (DB::table('redirects')->where('website_id', $webpage->website_id)
                                ->whereRaw('LOWER(from_path) = ?', [$auroraCanonical])
                                ->exists()) {
                                continue;
                            }

                            if ($auroraCanonical != $webpage->url) {
                                $command->info($auroraCanonical.' -> '.$webpage->canonical_url);

                                try {
                                    StoreRedirectFromWebpage::make()->action(
                                        $webpage,
                                        [
                                            'type'      => RedirectTypeEnum::PERMANENT,
                                            'from_path' => $auroraCanonical,
                                            'from_url'  => $auroraCanonical
                                        ]
                                    );
                                } catch (\Exception $e) {
                                    $command->error($e->getMessage());
                                }
                            }
                        }
                    }
                }
            }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:get_aurora_canonical_and_make_redirect {website?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('website')) {
            $website = Website::where('slug', $command->argument('website'))->first();
            $this->handle($website, $command);
            return 0;
        }

        foreach (Website::where('state',WebsiteStateEnum::LIVE)->get() as $website) {
            $this->handle($website, $command);
        }

        return 0;
    }

}
