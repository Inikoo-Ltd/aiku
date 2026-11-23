<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Actions\Web\StaticWebsite\GetStaticWebsiteCruxReport;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\UI\Web\StaticWebsiteTabsEnum;
use App\Enums\Web\StaticWebsite\StaticWebsiteComponentEnum;
use App\Enums\Web\StaticWebsite\StaticWebsiteStateEnum;
use App\Http\Resources\Web\StaticWebpagesResource;
use App\Http\Resources\Web\StaticWebsiteComponentsResource;
use App\Http\Resources\Web\StaticWebsiteSnapshotsResource;
use App\Models\Helpers\Snapshot;
use App\Models\Web\StaticWebsite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * An info website in Aiku: what it is and where it stands, its pages, the parts its pages share and
 * every kept version of them.
 */
class ShowStaticWebsite extends OrgAction
{
    use WithStaticWebsiteAuthorisation;

    public function asController(StaticWebsite $staticWebsite, ActionRequest $request): StaticWebsite
    {
        $this->initialisationFromGroup(group(), $request)->withTab(StaticWebsiteTabsEnum::values());
        abort_unless($staticWebsite->group_id === $this->group->id, 404);

        return $staticWebsite;
    }

    public function htmlResponse(StaticWebsite $staticWebsite, ActionRequest $request): Response
    {
        $tab = fn (StaticWebsiteTabsEnum $tab, callable $prop) => $this->tab === $tab->value ? $prop : Inertia::optional($prop);

        return Inertia::render(
            'Org/Marketing/StaticWebsite',
            [
                'title'       => $staticWebsite->name,
                'breadcrumbs' => $this->getBreadcrumbs($staticWebsite),
                'pageHead'    => [
                    'title'     => $staticWebsite->name,
                    'model'     => __('Info website'),
                    'icon'      => ['icon' => 'fal fa-globe', 'title' => __('Info website')],
                    'iconRight' => StaticWebsiteStateEnum::stateIcon()[$staticWebsite->state->value],
                ],
                'preview_url' => route('grp.marketing.info_websites.preview', [$staticWebsite->slug]),
                'live_url'    => 'https://www.'.$staticWebsite->domain,
                'can_restore' => $request->user()->authTo('group-webmaster.edit'),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $this->tabsNavigation($staticWebsite),
                ],

                StaticWebsiteTabsEnum::SHOWCASE->value => $tab(StaticWebsiteTabsEnum::SHOWCASE, fn () => $this->showcase($staticWebsite, $request)),

                'real_user_speed_history' => $this->tab === StaticWebsiteTabsEnum::SHOWCASE->value
                    ? Inertia::defer(fn () => ['crux' => GetStaticWebsiteCruxReport::run($staticWebsite)], 'real_user_speed_history')
                    : null,

                StaticWebsiteTabsEnum::WEBPAGES->value => $tab(StaticWebsiteTabsEnum::WEBPAGES, fn () => StaticWebpagesResource::collection(
                    IndexStaticWebpages::make()->handle($staticWebsite, StaticWebsiteTabsEnum::WEBPAGES->value)
                )),

                StaticWebsiteTabsEnum::COMPONENTS->value => $tab(StaticWebsiteTabsEnum::COMPONENTS, fn () => StaticWebsiteComponentsResource::collection(
                    IndexStaticWebsiteComponents::make()->handle($staticWebsite, StaticWebsiteTabsEnum::COMPONENTS->value)
                )),

                StaticWebsiteTabsEnum::SNAPSHOTS->value => $tab(StaticWebsiteTabsEnum::SNAPSHOTS, fn () => StaticWebsiteSnapshotsResource::collection(
                    IndexStaticWebsiteSnapshots::make()->handle($staticWebsite, StaticWebsiteTabsEnum::SNAPSHOTS->value)
                )),
            ]
        )
            ->table(IndexStaticWebpages::make()->tableStructure(StaticWebsiteTabsEnum::WEBPAGES->value))
            ->table(IndexStaticWebsiteComponents::make()->tableStructure(StaticWebsiteTabsEnum::COMPONENTS->value))
            ->table(IndexStaticWebsiteSnapshots::make()->tableStructure(prefix: StaticWebsiteTabsEnum::SNAPSHOTS->value));
    }

    private function showcase(StaticWebsite $staticWebsite, ActionRequest $request): array
    {
        $lastChange  = collect([$staticWebsite->webpages()->max('updated_at'), $staticWebsite->components()->max('updated_at')])->filter()->max();
        $homepageId  = $staticWebsite->webpages()->where('path', '/')->value('id');
        $sharedParts = $staticWebsite->components()->pluck('code');
        $partRoute   = fn (StaticWebsiteComponentEnum $part) => $sharedParts->contains($part->value)
            ? ['name' => 'grp.marketing.info_websites.components.show', 'parameters' => [$staticWebsite->slug, $part->value]]
            : null;

        return [
            'id'               => $staticWebsite->id,
            'url'              => 'https://www.'.$staticWebsite->domain,
            'url_hint'         => __('Shows the old host until the domain points to Aiku'),
            'can_edit'         => $request->user()->authTo('group-webmaster.edit'),
            'route_storefront' => $homepageId
                ? ['name' => 'grp.marketing.info_websites.webpages.show', 'parameters' => [$staticWebsite->slug, $homepageId]]
                : null,
            'route_header'     => $partRoute(StaticWebsiteComponentEnum::HEADER),
            'route_footer'     => $partRoute(StaticWebsiteComponentEnum::FOOTER),
            'details'          => [
                ['label' => __('State'), 'value' => StaticWebsiteStateEnum::labels()[$staticWebsite->state->value]],
                ['label' => __('Copied from'), 'value' => $staticWebsite->source_url, 'href' => $staticWebsite->source_url],
                ['label' => __('Copied on'), 'value' => $staticWebsite->crawled_at?->toIso8601String(), 'type' => 'date'],
                ['label' => __('Last change'), 'value' => $lastChange ? Carbon::parse($lastChange)->toIso8601String() : null, 'type' => 'date'],
            ],
        ];
    }

    private function tabsNavigation(StaticWebsite $staticWebsite): array
    {
        $navigation = StaticWebsiteTabsEnum::navigation();

        $navigation[StaticWebsiteTabsEnum::WEBPAGES->value]['number']   = $staticWebsite->webpages()->count();
        $navigation[StaticWebsiteTabsEnum::COMPONENTS->value]['number'] = $staticWebsite->components()->count();
        $navigation[StaticWebsiteTabsEnum::SNAPSHOTS->value]['number']  = $this->snapshots($staticWebsite)->count();

        return $navigation;
    }

    private function snapshots(StaticWebsite $staticWebsite): Builder
    {
        return Snapshot::where('builder', SnapshotBuilderEnum::STATIC_HTML)
            ->where(function ($query) use ($staticWebsite) {
                $query->where(fn ($query) => $query->where('parent_type', 'StaticWebpage')->whereIn('parent_id', $staticWebsite->webpages()->select('id')))
                    ->orWhere(fn ($query) => $query->where('parent_type', 'StaticWebsiteComponent')->whereIn('parent_id', $staticWebsite->components()->select('id')));
            });
    }

    /**
     * @param array{route: array{name: string, parameters: array}, label: string}|null ...$suffix
     */
    public function getBreadcrumbs(StaticWebsite $staticWebsite, ?array ...$suffix): array
    {
        $crumb = fn (array $item) => ['type' => 'simple', 'simple' => $item];

        return array_merge(
            IndexStaticWebsites::make()->getBreadcrumbs(),
            [
                $crumb([
                    'route' => ['name' => 'grp.marketing.info_websites.show', 'parameters' => [$staticWebsite->slug]],
                    'label' => $staticWebsite->name,
                ]),
            ],
            array_map($crumb, array_filter($suffix))
        );
    }
}
