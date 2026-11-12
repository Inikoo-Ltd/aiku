<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Actions\Web\StaticWebsite\RenderStaticWebpage;
use App\Actions\Web\StaticWebsite\StageStaticWebsiteChange;
use App\Models\Web\StaticWebsite;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows a page as it would be after a change an assistant prepared and has not saved yet, in the same
 * sandbox as the preview, so the user can look at the result before confirming it.
 */
class ShowStaticWebsiteChangePreview extends OrgAction
{
    use WithStaticWebsiteAuthorisation;

    public function handle(StaticWebsite $staticWebsite, array $staged, string $path): Response
    {
        $path = '/'.trim($path, '/');

        if (array_key_exists($path, $staged['pages']) && $staged['pages'][$path] === null) {
            return response(__('This change deletes :path. The page as it is now:', ['path' => $path])."\n\n".route('grp.marketing.info_websites.preview', [$staticWebsite->slug, ltrim($path, '/')]), 200, [
                'Content-Type'            => 'text/plain; charset=UTF-8',
                'Content-Security-Policy' => ShowStaticWebsitePreview::SANDBOX_POLICY,
            ]);
        }

        $html = $staged['pages'][$path]['html'] ?? $staticWebsite->webpages()->where('path', $path)->value('html');
        if ($html === null) {
            abort(404);
        }

        return ShowStaticWebsitePreview::make()->previewResponse(
            $staticWebsite,
            RenderStaticWebpage::run($staticWebsite, $html, $staged['components'])
        );
    }

    public function asController(StaticWebsite $staticWebsite, string $change, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        $staged = StageStaticWebsiteChange::find($change);
        abort_unless(
            $staticWebsite->group_id === $this->group->id && $staged && $staged['static_website_id'] === $staticWebsite->id,
            404
        );

        return $this->handle($staticWebsite, $staged, (string) $request->query('path', $staged['preview_path']));
    }
}
