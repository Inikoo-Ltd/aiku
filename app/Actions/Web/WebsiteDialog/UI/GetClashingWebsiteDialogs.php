<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\OrgAction;
use App\Enums\Web\WebsiteDialog\WebsiteDialogTriggerEnum;
use App\Http\Resources\Web\WebsiteDialogsResource;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use App\Actions\Web\WebsiteDialog\WithWebsiteDialogScope;
use Lorisleiva\Actions\ActionRequest;

class GetClashingWebsiteDialogs extends OrgAction
{
    use WithWebsiteDialogScope;

    /**
     * Active dialogs that would be paused if the given one is published with the given dates.
     *
     * @return Collection<int, WebsiteDialog>
     */
    public function handle(WebsiteDialog $websiteDialog, Carbon $from, ?Carbon $until): Collection
    {
        if ($websiteDialog->getDraftTrigger() !== WebsiteDialogTriggerEnum::AUTOMATIC->value) {
            return new Collection();
        }

        return WebsiteDialog::clashingWith($websiteDialog->website_id, $from, $until)
            ->where('id', '!=', $websiteDialog->id)
            ->get();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "supervisor-web.{$this->shop->id}",
            "web.{$this->shop->id}.edit",
            'group-webmaster.view',
        ]);
    }

    public function rules(): array
    {
        return [
            'schedule_at'        => ['sometimes', 'nullable', 'date'],
            'schedule_finish_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function asController(Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): Collection
    {
        $this->ensureWebsiteDialogScope(null, $website, $websiteDialog);
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle(
            $websiteDialog,
            $request->input('schedule_at') ? Carbon::parse($request->input('schedule_at')) : now(),
            $request->input('schedule_finish_at') ? Carbon::parse($request->input('schedule_finish_at')) : null
        );
    }

    public function jsonResponse(Collection $websiteDialogs): AnonymousResourceCollection
    {
        return WebsiteDialogsResource::collection($websiteDialogs);
    }
}
