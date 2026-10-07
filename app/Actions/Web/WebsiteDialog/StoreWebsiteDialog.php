<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\Helpers\Snapshot\StoreWebsiteDialogSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\WebsiteHydrateWebsiteDialogs;
use App\Enums\Web\WebsiteDialog\WebsiteDialogDisplayFrequencyEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogTriggerEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithWebEditAuthorisation;

    public function handle(Website $website, array $modelData): WebsiteDialog
    {
        data_set($modelData, 'group_id', $website->group_id);
        data_set($modelData, 'organisation_id', $website->organisation_id);
        data_set($modelData, 'ulid', (string)Str::ulid());
        data_set($modelData, 'settings', [
            'trigger'           => WebsiteDialogTriggerEnum::AUTOMATIC->value,
            'target_pages'      => ['type' => 'all', 'specific' => []],
            'target_users'      => ['auth_state' => 'all'],
            'display_frequency' => WebsiteDialogDisplayFrequencyEnum::ONCE_PER_SESSION->value,
            'delay_seconds'     => 0,
        ]);

        /** @var WebsiteDialog $websiteDialog */
        $websiteDialog = $website->websiteDialogs()->create($modelData);

        $snapshot = StoreWebsiteDialogSnapshot::run(
            $websiteDialog,
            [
                'layout' => [
                    'template_code'        => null,
                    'component'            => null,
                    'container_properties' => null,
                    'fields'               => null,
                    'settings'             => $websiteDialog->settings,
                ]
            ]
        );

        $websiteDialog->update(['unpublished_snapshot_id' => $snapshot->id]);

        WebsiteHydrateWebsiteDialogs::dispatch($website->id)->delay(2);

        return $websiteDialog;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function htmlResponse(WebsiteDialog $websiteDialog): Response
    {
        return Redirect::route('grp.org.shops.show.web.website_dialogs.workshop', [
            'organisation'  => $websiteDialog->website->organisation->slug,
            'shop'          => $websiteDialog->website->shop->slug,
            'website'       => $websiteDialog->website->slug,
            'websiteDialog' => $websiteDialog->ulid
        ]);
    }

    public function asController(Shop $shop, Website $website, ActionRequest $request): WebsiteDialog
    {
        $this->ensureWebsiteDialogScope($shop, $website);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $this->validatedData);
    }

    public function action(Website $website, array $modelData): WebsiteDialog
    {
        $this->asAction = true;
        $this->initialisationFromShop($website->shop, $modelData);

        return $this->handle($website, $this->validatedData);
    }
}
