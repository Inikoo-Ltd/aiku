<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Enums\UI\Web\StaticWebsitePartTabsEnum;
use App\Enums\UI\Web\StaticWebsiteTabsEnum;
use App\Http\Resources\Web\StaticWebsiteSnapshotsResource;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaticWebsiteComponent extends OrgAction
{
    use WithStaticWebsiteAuthorisation;

    public function asController(StaticWebsite $staticWebsite, StaticWebsiteComponent $component, ActionRequest $request): StaticWebsiteComponent
    {
        $this->initialisationFromGroup(group(), $request)->withTab(StaticWebsitePartTabsEnum::values());
        abort_unless($staticWebsite->group_id === $this->group->id, 404);

        return $component;
    }

    public function htmlResponse(StaticWebsiteComponent $component, ActionRequest $request): Response
    {
        $staticWebsite = $component->staticWebsite;
        $label         = $component->label();
        $usedBy        = $staticWebsite->webpages()->whereRaw('position(? in html) > 0', [StaticWebsiteComponent::marker($component->code)])->count();

        $snapshots = fn () => StaticWebsiteSnapshotsResource::collection(
            IndexStaticWebsiteSnapshots::make()->handle($component, StaticWebsitePartTabsEnum::SNAPSHOTS->value)
        );

        return Inertia::render(
            'Org/Marketing/StaticWebsitePart',
            [
                'title'       => $label.' · '.$staticWebsite->name,
                'breadcrumbs' => ShowStaticWebsite::make()->getBreadcrumbs(
                    $staticWebsite,
                    ['route' => ['name' => 'grp.marketing.info_websites.show', 'parameters' => [$staticWebsite->slug, 'tab' => StaticWebsiteTabsEnum::COMPONENTS->value]], 'label' => __('Shared parts')],
                    ['route' => ['name' => 'grp.marketing.info_websites.components.show', 'parameters' => [$staticWebsite->slug, $component->code]], 'label' => $label],
                ),
                'pageHead'    => [
                    'title' => $label,
                    'model' => __('Shared part'),
                    'icon'  => ['icon' => 'fal fa-puzzle-piece', 'title' => __('Shared part')],
                ],
                'preview_url' => route('grp.marketing.info_websites.preview', [$staticWebsite->slug]),
                'live_url'    => 'https://www.'.$staticWebsite->domain,
                'can_restore' => $request->user()->authTo('group-webmaster.edit'),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => StaticWebsitePartTabsEnum::navigation(),
                ],

                StaticWebsitePartTabsEnum::SHOWCASE->value => $this->tab === StaticWebsitePartTabsEnum::SHOWCASE->value
                    ? fn () => [
                        'details' => [
                            ['label' => __('Part'), 'value' => $label],
                            ['label' => __('Used by'), 'value' => trans_choice(':count page|:count pages', $usedBy, ['count' => $usedBy])],
                            ['label' => __('Copied on'), 'value' => $component->crawled_at?->toIso8601String(), 'type' => 'date'],
                            ['label' => __('Last change'), 'value' => $component->updated_at?->toIso8601String(), 'type' => 'date'],
                            ['label' => __('Size'), 'value' => round(strlen($component->html) / 1024).' KB'],
                        ],
                        'note'    => __('Every page that uses this part shows the same copy, so a change here reaches all of them at once.'),
                    ]
                    : Inertia::optional(fn () => []),

                StaticWebsitePartTabsEnum::SNAPSHOTS->value => $this->tab === StaticWebsitePartTabsEnum::SNAPSHOTS->value ? $snapshots : Inertia::optional($snapshots),
            ]
        )->table(IndexStaticWebsiteSnapshots::make()->tableStructure(withPart: false, prefix: StaticWebsitePartTabsEnum::SNAPSHOTS->value));
    }
}
