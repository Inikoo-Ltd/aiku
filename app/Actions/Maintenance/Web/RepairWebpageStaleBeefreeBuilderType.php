<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A blog made from a mailshot stores builderType 'beefree' on its web block, and Iris renders the
 * compiled beefree html instead of the article content while that flag is there. Moving such a blog
 * to a real category swaps the workshop to the page builder but leaves the flag behind, so the page
 * goes blank. This drops the stale flag and republishes, keeping the beefree layout as a backup.
 */
class RepairWebpageStaleBeefreeBuilderType
{
    use AsAction;

    public function handle(Webpage $webpage, bool $dryRun = false): bool
    {
        $modelHasWebBlock = $webpage->modelHasWebBlocks()->first();

        if (!$modelHasWebBlock) {
            return false;
        }

        $layout = $this->getWebBlockLayout($webpage);

        if (Arr::get($layout, 'data.fieldValue.builderType') !== 'beefree') {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        Arr::forget($layout, 'data.fieldValue.builderType');

        UpdateModelHasWebBlocks::make()->action($modelHasWebBlock, ['layout' => $layout]);

        if ($webpage->state == WebpageStateEnum::LIVE) {
            PublishWebpage::make()->action($webpage->refresh(), ['comment' => 'Remove stale beefree builder type']);
        }

        return true;
    }

    /**
     * Webpages left with the beefree flag after their category moved away from mailshot, and whose
     * article content can be shown by the page builder layout.
     */
    public function getWebpagesToRepair(?int $webpageID): array
    {
        return Webpage::where('type', WebpageTypeEnum::BLOG)
            ->where('sub_type', '!=', WebpageSubTypeEnum::MAILSHOT)
            ->when($webpageID, fn ($query) => $query->where('id', $webpageID))
            ->get()
            ->filter(function (Webpage $webpage) {
                return Arr::get($this->getWebBlockLayout($webpage), 'data.fieldValue.builderType') === 'beefree';
            })
            ->all();
    }

    /**
     * The web block layout is cast to an object, the actions writing it back take an array.
     *
     * @return array<string, mixed>
     */
    protected function getWebBlockLayout(Webpage $webpage): array
    {
        $layout = $webpage->modelHasWebBlocks()->first()?->webBlock->layout;

        return json_decode(json_encode($layout), true) ?? [];
    }

    public string $commandSignature = 'repair:webpage_stale_beefree_builder_type {webpage_id?} {--dry-run}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun   = (bool)$command->option('dry-run');
        $webpages = $this->getWebpagesToRepair($command->argument('webpage_id'));

        if (!$webpages) {
            $command->info('Nothing to repair');

            return 0;
        }

        foreach ($webpages as $webpage) {
            $content = Arr::get($this->getWebBlockLayout($webpage), 'data.fieldValue.content');

            if (!$content) {
                $command->warn("SKIPPED webpage $webpage->id ($webpage->slug): no article content, its only content is the beefree layout, set its category back to mailshot instead");
                continue;
            }

            $command->info(($dryRun ? 'WOULD REPAIR' : 'REPAIRING')." webpage $webpage->id ($webpage->slug) $webpage->canonical_url");

            $this->handle($webpage, $dryRun);
        }

        return 0;
    }
}
