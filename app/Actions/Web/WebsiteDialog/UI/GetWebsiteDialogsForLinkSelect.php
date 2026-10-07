<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\OrgAction;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetWebsiteDialogsForLinkSelect extends OrgAction
{
    /**
     * Published dialogs a webpage button can open.
     *
     * @return Collection<int, WebsiteDialog>
     */
    public function handle(Website $website): Collection
    {
        return $website->websiteDialogs()
            ->whereNotNull('published_layout')
            ->orderBy('name')
            ->get(['id', 'ulid', 'name', 'status', 'published_layout']);
    }

    public function authorize(ActionRequest $request): bool
    {
        $permissions = [
            "websites-view.{$this->organisation->id}",
            "web.{$this->shop->id}",
            "web.{$this->shop->id}.view",
            "web.{$this->shop->id}.edit",
            'group-webmaster.view',
        ];

        if ($this->shop->fulfilment) {
            $permissions[] = "fulfilment-shop.{$this->shop->fulfilment->id}.view";
            $permissions[] = "fulfilment-shop.{$this->shop->fulfilment->id}.edit";
        }

        return $request->user()->authTo($permissions);
    }

    public function asController(Website $website, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function jsonResponse(Collection $websiteDialogs): array
    {
        return [
            'data' => $websiteDialogs->map(fn (WebsiteDialog $websiteDialog) => [
                'id'        => $websiteDialog->ulid,
                'name'      => $websiteDialog->name,
                'href'      => '#website-dialog-'.$websiteDialog->ulid,
                'is_active' => $websiteDialog->status === WebsiteDialogStatusEnum::ACTIVE,
                'opens'     => $websiteDialog->getTrigger(),
            ])->values()->all(),
        ];
    }
}
