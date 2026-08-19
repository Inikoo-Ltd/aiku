<?php

/*
 * Author Louis Perez
 * Created on 11-08-2026-10h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Webpage\UpdateWebpageCanonicalUrl;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairWebpageBlogSubType
{
    use AsAction;

    public function handle(Webpage $webpage, ?WebpageSubTypeEnum $webpageSubType = null)
    {
        if ($webpageSubType) {
            $webpage->updateQuietly([
                'sub_type' => $webpageSubType
            ]);
        }

        UpdateWebpageCanonicalUrl::run($webpage);
    }

    public string $commandSignature = 'repair:webpage_blog_sub_type';

    public function asCommand(Command $command)
    {
        $travelWebpages = Webpage::where('type', WebpageTypeEnum::BLOG)->whereRaw("code ilike '%david-blog%'")->get();

        foreach ($travelWebpages as $webpage) {
            $command->info("Repairing $webpage->slug: Fixing Sub Type & Updating Canonical URL");
            $this->handle($webpage, WebpageSubTypeEnum::NEWSLETTERS);
        }

        $tipsWebpages = Webpage::where('type', WebpageTypeEnum::BLOG)->where('sub_type', 'tips')->get();

        foreach ($tipsWebpages as $webpage) {
            $command->info("Repairing $webpage->slug: Fixing Sub Type & Updating Canonical URL");
            $this->handle($webpage, WebpageSubTypeEnum::BUSINESS_TIPS);
        }

        $webpages = Webpage::where('type', WebpageTypeEnum::BLOG)->get();

        foreach ($webpages as $webpage) {
            $command->info("Repairing $webpage->slug: Updating Canonical URL");
            $this->handle($webpage);
        }
    }
}
