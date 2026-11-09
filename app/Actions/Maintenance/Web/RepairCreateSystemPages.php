<?php

/*
 * Author Louis Perez
 * Created on 03-09-2026-14h-57m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Redirect;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairCreateSystemPages
{
    use WithActionUpdate;
    use WithRepairWebpages;

    protected function handle(Website $website, ?Command $command = null): void
    {
        foreach (WebpageSubTypeEnum::systemPages() as $subTypeValue => $systemPage) {
            $subType = WebpageSubTypeEnum::from($subTypeValue);

            if (Arr::has($systemPage, 'shop_types') && !in_array($website->shop->type, $systemPage['shop_types'], true)) {
                continue;
            }

            $webpage = Webpage::where('website_id', $website->id)
                ->where('type', WebpageTypeEnum::SYSTEM_PAGE)
                ->where('sub_type', $subType)
                ->first();

            if (!$webpage) {
                $url = $this->freeUrl($website, $systemPage);

                if (!$url) {
                    $command?->error("{$systemPage['title']}: /{$systemPage['url']} is taken by another webpage");

                    continue;
                }

                $webpage = StoreWebpage::make()->action($website, [
                    'url'      => $url,
                    'code'     => $url,
                    'title'    => $systemPage['title'],
                    'type'     => WebpageTypeEnum::SYSTEM_PAGE,
                    'sub_type' => $subType,
                ]);
            } else {
                if ($systemPage['web_block'] && count($this->getWebpageBlocksByType($webpage, $systemPage['web_block'])) == 0) {
                    $this->replaceOlderSystemWebBlocks($webpage, $systemPage, $command);
                    $this->restoreSystemWebBlock($webpage, $systemPage['web_block'], $command);
                }

                foreach (Arr::get($systemPage, 'template_web_blocks', []) as $webBlockTemplate) {
                    if (count($this->getWebpageBlocksByType($webpage, $webBlockTemplate->templateCodes())) == 0) {
                        $this->restoreTemplateWebBlock($webpage->refresh(), $webBlockTemplate, $command);
                    }
                }
            }

            $website->update([$systemPage['website_field'] => $webpage->id]);

            $command?->info("{$systemPage['title']}: {$webpage->canonical_url}");
        }
    }

    /**
     * The url of a new system page. A page with url_when_taken whose url another webpage or a redirect
     * already has is created there instead (StoreWebpage would delete that redirect), unpublished like every new system page, and the other webpage is left
     * alone (the NEW IN page takes /new over only with website:switch_to_new_in_page).
     *
     * @param  array{url: string, url_when_taken?: string}  $systemPage
     */
    protected function freeUrl(Website $website, array $systemPage): ?string
    {
        $isFree = fn (string $url): bool => !Webpage::where('website_id', $website->id)
            ->where(fn ($query) => $query->whereRaw('lower(url) = ?', [strtolower($url)])->orWhereRaw('lower(code) = ?', [strtolower($url)]))
            ->exists()
            && !Redirect::where('website_id', $website->id)->whereRaw('lower(from_path) = ?', [strtolower($url)])->exists();

        if (!Arr::has($systemPage, 'url_when_taken') || $isFree($systemPage['url'])) {
            return $systemPage['url'];
        }

        return $isFree($systemPage['url_when_taken']) ? $systemPage['url_when_taken'] : null;
    }

    /**
     * @param  array{web_block: string, replaces_web_blocks?: array<int, string>}  $systemPage
     */
    protected function replaceOlderSystemWebBlocks(Webpage $webpage, array $systemPage, ?Command $command = null): void
    {
        if (!WebBlockType::where('code', $systemPage['web_block'])->exists()) {
            return;
        }

        foreach (Arr::get($systemPage, 'replaces_web_blocks', []) as $olderWebBlockCode) {
            if (count($this->getWebpageBlocksByType($webpage, $olderWebBlockCode)) > 0) {
                $this->deleteWebBlocksByCode($webpage, $olderWebBlockCode);
                $command?->line("{$webpage->code}: {$olderWebBlockCode} web block replaced by {$systemPage['web_block']}");
            }
        }
    }

    protected function restoreSystemWebBlock(Webpage $webpage, string $webBlockCode, ?Command $command = null): void
    {
        if (!$this->createWebBlock($webpage, $webBlockCode)) {
            $command?->error("{$webpage->code}: web block type {$webBlockCode} not found, run group:seed_web_block_types");

            return;
        }

        $command?->line("{$webpage->code}: {$webBlockCode} web block was missing, added it back");

        $webpage->refresh();

        if ($webpage->is_dirty && $webpage->state == WebpageStateEnum::LIVE) {
            PublishWebpage::make()->action(
                $webpage,
                [
                    'comment' => 'publish after restoring system web block',
                ]
            );
        }
    }

    protected function restoreTemplateWebBlock(Webpage $webpage, WebBlockTemplateEnum $webBlockTemplate, ?Command $command = null): void
    {
        if (!$this->createWebBlockFromWebsiteTemplate($webpage, $webBlockTemplate)) {
            $command?->error("{$webpage->code}: no {$webBlockTemplate->value} web block type found, run group:seed_web_block_types");

            return;
        }

        $command?->line("{$webpage->code}: {$webBlockTemplate->value} web block was missing, added it back");

        $webpage->refresh();

        if ($webpage->is_dirty && $webpage->state == WebpageStateEnum::LIVE) {
            PublishWebpage::make()->action(
                $webpage,
                [
                    'comment' => 'publish after restoring system web block',
                ]
            );
        }
    }

    public string $commandSignature = 'repair:create_system_pages {--website_id=}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $websites = Website::where('status', true)
            ->when(
                $command->option('website_id'),
                fn ($q) => $q->where('id', $command->option('website_id'))
            )
            ->get();

        foreach ($websites as $website) {
            $command->info("-- Processing: {$website->slug}");
            $this->handle($website, $command);
        }
    }
}
