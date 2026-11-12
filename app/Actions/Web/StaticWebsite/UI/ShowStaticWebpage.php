<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Enums\UI\Web\StaticWebsitePartTabsEnum;
use App\Enums\Web\StaticWebsite\StaticWebsiteComponentEnum;
use App\Http\Resources\Web\StaticWebsiteSnapshotsResource;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaticWebpage extends OrgAction
{
    use WithStaticWebsiteAuthorisation;

    public function asController(StaticWebsite $staticWebsite, StaticWebpage $webpage, ActionRequest $request): StaticWebpage
    {
        $this->initialisationFromGroup(group(), $request)->withTab(StaticWebsitePartTabsEnum::values());
        abort_unless($staticWebsite->group_id === $this->group->id, 404);

        return $webpage;
    }

    public function htmlResponse(StaticWebpage $webpage, ActionRequest $request): Response
    {
        $staticWebsite = $webpage->staticWebsite;
        $html          = (string) $webpage->html;
        $sharedParts   = collect(StaticWebsiteComponentEnum::cases())
            ->filter(fn (StaticWebsiteComponentEnum $component) => str_contains($html, $component->marker()))
            ->map(fn (StaticWebsiteComponentEnum $component) => [
                'label' => $component === StaticWebsiteComponentEnum::HEADER ? __('Shared header') : __('Shared footer'),
                'route' => ['name' => 'grp.marketing.info_websites.components.show', 'parameters' => [$staticWebsite->slug, $component->value]],
            ])
            ->values()
            ->all();

        $snapshots = fn () => StaticWebsiteSnapshotsResource::collection(
            IndexStaticWebsiteSnapshots::make()->handle($webpage, StaticWebsitePartTabsEnum::SNAPSHOTS->value)
        );

        return Inertia::render(
            'Org/Marketing/StaticWebsitePart',
            [
                'title'       => $webpage->path.' · '.$staticWebsite->name,
                'breadcrumbs' => ShowStaticWebsite::make()->getBreadcrumbs(
                    $staticWebsite,
                    ['route' => ['name' => 'grp.marketing.info_websites.webpages.index', 'parameters' => [$staticWebsite->slug]], 'label' => __('Webpages')],
                    ['route' => ['name' => 'grp.marketing.info_websites.webpages.show', 'parameters' => [$staticWebsite->slug, $webpage->id]], 'label' => $webpage->path],
                ),
                'pageHead'    => [
                    'title' => $webpage->path,
                    'model' => __('Webpage'),
                    'icon'  => ['icon' => 'fal fa-browser', 'title' => __('Webpage')],
                ],
                'preview_url' => route('grp.marketing.info_websites.preview', [$staticWebsite->slug, ltrim($webpage->path, '/')]),
                'live_url'    => 'https://www.'.$staticWebsite->domain.$webpage->path,
                'can_restore' => $request->user()->authTo('group-webmaster.edit'),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => StaticWebsitePartTabsEnum::navigation(),
                ],

                StaticWebsitePartTabsEnum::SHOWCASE->value => $this->tab === StaticWebsitePartTabsEnum::SHOWCASE->value
                    ? fn () => [
                        'details'      => [
                            ['label' => __('Path'), 'value' => $webpage->path, 'code' => true],
                            ['label' => __('Title'), 'value' => $webpage->title],
                            ['label' => __('Description'), 'value' => $webpage->description],
                            ['label' => __('Copied from'), 'value' => $webpage->source_url, 'href' => $webpage->source_url],
                            ['label' => __('Copied on'), 'value' => $webpage->crawled_at?->toIso8601String(), 'type' => 'date'],
                            ['label' => __('Last change'), 'value' => $webpage->updated_at?->toIso8601String(), 'type' => 'date'],
                            ['label' => __('Size'), 'value' => round(strlen($html) / 1024).' KB'],
                        ],
                        'shared_parts' => $sharedParts,
                    ]
                    : Inertia::optional(fn () => []),

                StaticWebsitePartTabsEnum::SNAPSHOTS->value => $this->tab === StaticWebsitePartTabsEnum::SNAPSHOTS->value ? $snapshots : Inertia::optional($snapshots),
            ]
        )->table(IndexStaticWebsiteSnapshots::make()->tableStructure(withPart: false, prefix: StaticWebsitePartTabsEnum::SNAPSHOTS->value));
    }
}
