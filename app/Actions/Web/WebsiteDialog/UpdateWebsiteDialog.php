<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Web\WebsiteDialog\WebsiteDialogDisplayFrequencyEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogTriggerEnum;
use App\Http\Resources\Web\WebsiteDialogResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithActionUpdate;
    use WithWebEditAuthorisation;

    /**
     * Saves the draft only: what the storefront shows changes when the dialog is published.
     */
    public function handle(WebsiteDialog $websiteDialog, array $modelData): WebsiteDialog
    {
        $draftKeys = ['template_code', 'component', 'fields', 'container_properties', 'settings'];

        if (Arr::hasAny($modelData, $draftKeys)) {
            $snapshot = $websiteDialog->unpublishedSnapshot;
            $layout   = $snapshot->layout ?? [];

            foreach ($draftKeys as $key) {
                if (Arr::exists($modelData, $key)) {
                    $layout[$key] = $modelData[$key];
                }
            }

            $snapshot->update(['layout' => $layout]);
            data_set($modelData, 'is_dirty', true);
        }

        return $this->update($websiteDialog, $modelData);
    }

    public function rules(): array
    {
        return [
            'name'                                => ['sometimes', 'string', 'max:255'],
            'template_code'                       => ['sometimes', 'nullable', 'string', 'max:255'],
            'component'                           => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields'                              => ['sometimes', 'array'],
            'container_properties'                => ['sometimes', 'array'],
            'settings'                            => ['sometimes', 'array'],
            'settings.target_users.auth_state'    => ['sometimes', Rule::in(['all', 'logged_in', 'logged_out'])],
            'settings.target_pages.type'          => ['sometimes', Rule::in(['all', 'specific'])],
            'settings.display_frequency'          => ['sometimes', Rule::enum(WebsiteDialogDisplayFrequencyEnum::class)],
            'settings.trigger'                    => ['sometimes', Rule::enum(WebsiteDialogTriggerEnum::class)],
            'settings.delay_seconds'              => ['sometimes', 'integer', 'min:0', 'max:600'],
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
