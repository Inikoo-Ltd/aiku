<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Web\Redirect\UpdateRedirect;
use App\Actions\Web\Webpage\ProcessUpdateWebpageUrl;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SwitchWebsiteToNewInPage
{
    use AsAction;

    public string $commandSignature = 'website:switch_to_new_in_page {website : Website slug} {--replace=* : Url of another live page the NEW IN page replaces, e.g. new-arrivals; it is closed with a 301 to /new}';

    /**
     * Puts the NEW IN page live at /new, by hand and one website at a time, once marketing is ready.
     * A page already at /new moves to /new-old. That page and the pages named in $replaceUrls are closed
     * with a permanent redirect to the NEW IN page, and the redirects that led to them lead to it instead.
     * Every step runs here and not queued, so no queued follow-up can land after the switch.
     *
     * @param  array<int, string>  $replaceUrls
     */
    public function handle(Website $website, array $replaceUrls = [], ?Command $command = null): Webpage
    {
        $newInPage = $website->newInPage;
        if (!$newInPage) {
            throw ValidationException::withMessages(['website' => "$website->slug has no NEW IN page yet, run repair:create_system_pages first"]);
        }

        $url = WebpageSubTypeEnum::systemPages()[WebpageSubTypeEnum::NEW_IN_PAGE->value]['url'];

        $pagesToClose = collect($replaceUrls)->map(function (string $replaceUrl) use ($website, $newInPage) {
            $page = $this->findPage($website, $replaceUrl, $newInPage);
            if (!$page || $page->state != WebpageStateEnum::LIVE) {
                throw ValidationException::withMessages(['replace' => "$website->slug has no live page at /$replaceUrl"]);
            }

            return $page;
        });

        if (strtolower($newInPage->url) != $url) {
            $occupant = $this->findPage($website, $url, $newInPage);
            if ($occupant) {
                $this->movePageAside($occupant, $url, $command);
                $pagesToClose->push($occupant);
            }

            Redirect::where('website_id', $website->id)->whereRaw('lower(from_path) = ?', [$url])->delete();

            $draftUrl    = $newInPage->url;
            $isCodeTaken = Webpage::where('website_id', $website->id)->where('id', '!=', $newInPage->id)->whereRaw('lower(code) = ?', [$url])->exists();
            $newInPage->update(array_filter(['url' => $url, 'code' => $isCodeTaken ? null : $url]));
            ProcessUpdateWebpageUrl::run($newInPage, $draftUrl);
            $command?->line("NEW IN page moved from /$draftUrl to /$url");
        }

        if ($newInPage->state != WebpageStateEnum::LIVE) {
            PublishWebpage::make()->action($newInPage, ['comment' => 'NEW IN page goes live']);
            $command?->line("NEW IN page published at /$url");
        }

        $pagesToClose->unique('id')
            ->filter(fn (Webpage $page) => $page->state == WebpageStateEnum::LIVE)
            ->each(fn (Webpage $page) => $this->closeInFavourOf($page, $newInPage->refresh(), $command));

        return $newInPage->refresh();
    }

    private function findPage(Website $website, string $url, Webpage $except): ?Webpage
    {
        return Webpage::where('website_id', $website->id)
            ->where('id', '!=', $except->id)
            ->whereRaw('lower(url) = ?', [strtolower($url)])
            ->first();
    }

    private function movePageAside(Webpage $page, string $url, ?Command $command): void
    {
        $asideUrl = $url.'-old';
        $suffix   = 2;
        while (Webpage::where('website_id', $page->website_id)->where(fn ($query) => $query->whereRaw('lower(url) = ?', [$asideUrl])->orWhereRaw('lower(code) = ?', [$asideUrl]))->exists()) {
            $asideUrl = $url.'-old-'.$suffix++;
        }

        $page->update(array_filter([
            'url'  => $asideUrl,
            'code' => strtolower($page->code) == $url ? $asideUrl : null,
        ]));

        ProcessUpdateWebpageUrl::run($page, $url);

        $command?->line("$page->code: moved from /$url to /$asideUrl");
    }

    private function closeInFavourOf(Webpage $page, Webpage $newInPage, ?Command $command): void
    {
        Redirect::where('to_webpage_id', $page->id)->get()
            ->each(fn (Redirect $redirect) => UpdateRedirect::make()->action($redirect, ['to_webpage_id' => $newInPage->id]));

        UpdateWebpage::make()->action($page, [
            'state_data' => [
                'state'               => WebpageStateEnum::CLOSED->value,
                'redirect_webpage_id' => $newInPage->id,
            ],
        ]);

        $command?->line("$page->code: closed, /$page->url redirects to /$newInPage->url");
    }

    public function asCommand(Command $command): int
    {
        $website = Website::where('slug', $command->argument('website'))->firstOrFail();

        try {
            $newInPage = $this->handle($website, $command->option('replace'), $command);
        } catch (ValidationException $exception) {
            $command->error(collect($exception->errors())->flatten()->implode(' '));

            return 1;
        }

        $command->info("NEW IN page live: $newInPage->canonical_url");

        return 0;
    }
}
