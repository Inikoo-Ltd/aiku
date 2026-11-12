<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Actions\OrgAction;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Puts an earlier version of an info website page or shared part back. The restore is itself kept as a
 * snapshot, so it can be undone the same way.
 */
class RestoreStaticWebsiteSnapshot extends OrgAction
{
    public function handle(Snapshot $snapshot): StaticWebpage|StaticWebsiteComponent
    {
        /** @var StaticWebpage|StaticWebsiteComponent $parent */
        $parent = $snapshot->parent;

        $parent->snapshotComment = __('Restored the version of :date', ['date' => $snapshot->created_at->format('j M Y H:i')]);
        $parent->html            = $snapshot->compiled_layout;
        $parent->save();

        return $parent;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('group-webmaster.edit');
    }

    public function asController(Snapshot $snapshot, ActionRequest $request): StaticWebpage|StaticWebsiteComponent
    {
        $this->initialisationFromGroup(group(), $request);

        abort_unless(
            $snapshot->builder === SnapshotBuilderEnum::STATIC_HTML
            && ($snapshot->parent instanceof StaticWebpage || $snapshot->parent instanceof StaticWebsiteComponent)
            && $snapshot->parent->staticWebsite->group_id === $this->group->id,
            404
        );

        return $this->handle($snapshot);
    }

    public function action(Snapshot $snapshot): StaticWebpage|StaticWebsiteComponent
    {
        $this->asAction = true;
        $this->initialisationFromGroup($snapshot->parent->staticWebsite->group, []);

        return $this->handle($snapshot);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
