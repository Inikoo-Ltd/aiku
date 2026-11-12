<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Actions\Web\StaticWebsite\RenderStaticWebpage;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows an earlier version, in the same sandbox as the preview: a page version with today's shared
 * parts, a shared part's version on the site's homepage.
 */
class ShowStaticWebsiteSnapshotPreview extends OrgAction
{
    use WithStaticWebsiteAuthorisation;

    public function handle(StaticWebsite $staticWebsite, Snapshot $snapshot): Response
    {
        $parent = $snapshot->parent;

        if ($parent instanceof StaticWebsiteComponent) {
            $homepage = $staticWebsite->webpages()->orderByRaw("path = '/' desc")->orderBy('path')->value('html') ?? '';
            $html     = RenderStaticWebpage::run($staticWebsite, $homepage, [$parent->code->value => $snapshot->compiled_layout]);
        } else {
            $html = RenderStaticWebpage::run($staticWebsite, (string) $snapshot->compiled_layout);
        }

        return ShowStaticWebsitePreview::make()->previewResponse($staticWebsite, $html);
    }

    public function asController(StaticWebsite $staticWebsite, Snapshot $snapshot, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        $parent = $snapshot->parent;
        abort_unless(
            $staticWebsite->group_id === $this->group->id
            && $snapshot->builder === SnapshotBuilderEnum::STATIC_HTML
            && ($parent instanceof StaticWebpage || $parent instanceof StaticWebsiteComponent)
            && $parent->static_website_id === $staticWebsite->id,
            404
        );

        return $this->handle($staticWebsite, $snapshot);
    }
}
