<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreWebsiteDialogSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Actions\Web\WebsiteHydrateWebsiteDialogs;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStateEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogTriggerEnum;
use App\Http\Resources\Web\WebsiteDialogResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PublishWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithActionUpdate;
    use WithWebEditAuthorisation;

    public function handle(WebsiteDialog $websiteDialog, array $modelData): WebsiteDialog
    {
        $layout = $websiteDialog->unpublishedSnapshot->layout ?? [];

        if (!Arr::get($layout, 'component')) {
            throw ValidationException::withMessages([
                'template_code' => __('Select a template before publishing the dialog.')
            ]);
        }

        $from  = Arr::get($modelData, 'schedule_at') ? Carbon::parse(Arr::get($modelData, 'schedule_at')) : now();
        $until = Arr::get($modelData, 'schedule_finish_at') ? Carbon::parse(Arr::get($modelData, 'schedule_finish_at')) : null;

        if ($until && $until->lte($from)) {
            throw ValidationException::withMessages([
                'schedule_finish_at' => __('The finish date must be after the start date.')
            ]);
        }

        $clashes = $this->getClashes($websiteDialog, $from, $until);

        if ($clashes->isNotEmpty() && !Arr::get($modelData, 'supersede')) {
            throw ValidationException::withMessages([
                'supersede' => __('The dialog of this website is already taken by :names during those dates. Publish again choosing to replace it, or change your dates.', [
                    'names' => $clashes->pluck('name')->join(', ')
                ])
            ]);
        }

        $publisher = request()->user();

        $snapshot = StoreWebsiteDialogSnapshot::run(
            $websiteDialog,
            [
                'state'          => SnapshotStateEnum::LIVE,
                'published_at'   => now(),
                'layout'         => $layout,
                'first_commit'   => $websiteDialog->state === WebsiteDialogStateEnum::IN_PROCESS,
                'comment'        => Arr::get($modelData, 'published_message'),
                'publisher_id'   => $publisher?->id,
                'publisher_type' => $publisher?->getMorphClass(),
            ]
        );

        StoreDeployment::run(
            $websiteDialog,
            [
                'snapshot_id'    => $snapshot->id,
                'publisher_id'   => $publisher?->id,
                'publisher_type' => $publisher?->getMorphClass(),
            ]
        );

        $this->update($websiteDialog, [
            'live_snapshot_id'          => $snapshot->id,
            'published_layout'          => $layout,
            'published_checksum'        => md5(json_encode($layout)),
            'published_message'         => Arr::get($modelData, 'published_message'),
            'state'                     => WebsiteDialogStateEnum::READY,
            'status'                    => $from->isFuture() ? WebsiteDialogStatusEnum::INACTIVE : WebsiteDialogStatusEnum::ACTIVE,
            'is_dirty'                  => false,
            'ready_at'                  => now(),
            'live_at'                   => $from,
            'schedule_at'               => Arr::get($modelData, 'schedule_at') ? $from : null,
            'schedule_finish_at'        => $until,
            'closed_at'                 => $until,
            'paused_by_website_dialog_id' => null,
            'paused_until'              => null,
        ]);

        if ($from->isFuture()) {
            ApplyWebsiteDialogSchedule::dispatch($websiteDialog)->delay($from);
        }

        if ($until) {
            ApplyWebsiteDialogSchedule::dispatch($websiteDialog)->delay($until);
        }

        $this->supersedeClashes($websiteDialog, $from, $until);

        BreakWebsiteIrisCache::run($websiteDialog->website);
        WebsiteHydrateWebsiteDialogs::dispatch($websiteDialog->website_id)->delay(2);

        return $websiteDialog;
    }

    /**
     * Hands the website dialog over to the one being published: whatever it paused before is let
     * go first, so the clashes are recomputed from scratch, and each one is then taken off only
     * when the new window actually starts, not the moment it is published.
     */
    private function supersedeClashes(WebsiteDialog $websiteDialog, Carbon $from, ?Carbon $until): void
    {
        ReleasePausedWebsiteDialogs::run($websiteDialog);

        foreach ($this->getClashes($websiteDialog, $from, $until) as $clash) {
            if ($from->isFuture()) {
                PauseSupersededWebsiteDialog::dispatch($clash, $websiteDialog->id)->delay($from);
            } else {
                PauseSupersededWebsiteDialog::run($clash, $websiteDialog->id);
            }

            if ($until) {
                ResumeSupersededWebsiteDialog::dispatch($clash, $websiteDialog->id)->delay($until);
            }
        }
    }

    /**
     * @return Collection<int, WebsiteDialog>
     */
    public function getClashes(WebsiteDialog $websiteDialog, Carbon $from, ?Carbon $until): Collection
    {
        if ($websiteDialog->getDraftTrigger() !== WebsiteDialogTriggerEnum::AUTOMATIC->value) {
            return new Collection();
        }

        return WebsiteDialog::clashingWith($websiteDialog->website_id, $from, $until)
            ->where('id', '!=', $websiteDialog->id)
            ->get();
    }

    public function rules(): array
    {
        return [
            'schedule_at'        => ['sometimes', 'nullable', 'date'],
            'schedule_finish_at' => ['sometimes', 'nullable', 'date'],
            'published_message'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'supersede'          => ['sometimes', 'boolean'],
        ];
    }

    public function jsonResponse(WebsiteDialog $websiteDialog): WebsiteDialogResource
    {
        return WebsiteDialogResource::make($websiteDialog->refresh());
    }

    public function asController(Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): WebsiteDialog
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($websiteDialog, $this->validatedData);
    }

    public function action(WebsiteDialog $websiteDialog, array $modelData): WebsiteDialog
    {
        $this->asAction = true;
        $this->initialisationFromShop($websiteDialog->website->shop, $modelData);

        return $this->handle($websiteDialog, $this->validatedData);
    }
}
