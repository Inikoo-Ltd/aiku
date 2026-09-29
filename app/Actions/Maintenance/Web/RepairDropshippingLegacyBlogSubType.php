<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Blogs of a dropshipping shop written before it had its own categories still carry a sub type
 * from the catalogue set (blog, business tips, newsletters...), which has no dashboard url on a
 * dropshipping website. They are moved to the dropshipping guides category and their canonical
 * url rebuilt. A blog made from a mailshot keeps its sub type, it decides the builder it opens in.
 */
class RepairDropshippingLegacyBlogSubType
{
    use AsAction;

    public function handle(Webpage $webpage, bool $dryRun = false): void
    {
        if ($dryRun) {
            return;
        }

        RepairWebpageBlogSubType::run($webpage, WebpageSubTypeEnum::DROPSHIPPING_GUIDES);
    }

    /**
     * @return Collection<int, Webpage>
     */
    public function getWebpagesToRepair(?int $webpageID = null): Collection
    {
        return Webpage::where('type', WebpageTypeEnum::BLOG)
            ->whereHas('shop', fn ($query) => $query->where('type', ShopTypeEnum::DROPSHIPPING))
            ->whereNotIn('sub_type', [
                ...WebpageSubTypeEnum::blogCategoryValues(ShopTypeEnum::DROPSHIPPING),
                WebpageSubTypeEnum::MAILSHOT->value,
            ])
            ->when($webpageID, fn ($query) => $query->where('id', $webpageID))
            ->with('website')
            ->get();
    }

    public string $commandSignature = 'repair:dropshipping_legacy_blog_sub_type {webpage_id?} {--dry-run}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun   = (bool)$command->option('dry-run');
        $webpages = $this->getWebpagesToRepair($command->argument('webpage_id'));

        if ($webpages->isEmpty()) {
            $command->info('Nothing to repair');

            return 0;
        }

        foreach ($webpages as $webpage) {
            $command->info(($dryRun ? 'WOULD MOVE' : 'MOVING')." webpage $webpage->id ($webpage->slug) from ".$webpage->getRawOriginal('sub_type').' to '.WebpageSubTypeEnum::DROPSHIPPING_GUIDES->value);

            $this->handle($webpage, $dryRun);
        }

        return 0;
    }
}
