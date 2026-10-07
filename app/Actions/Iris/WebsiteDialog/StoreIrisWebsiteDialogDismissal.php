<?php

namespace App\Actions\Iris\WebsiteDialog;

use App\Actions\IrisAction;
use App\Models\CRM\Customer;
use App\Models\Web\WebsiteDialog;
use App\Models\Web\WebsiteDialogDismissal;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisWebsiteDialogDismissal extends IrisAction
{
    private WebsiteDialog $websiteDialog;

    /**
     * Remembers the customer closed the published version they saw, so publishing the dialog again
     * shows it to them again.
     */
    public function handle(WebsiteDialog $websiteDialog, Customer $customer): WebsiteDialogDismissal
    {
        return WebsiteDialogDismissal::updateOrCreate(
            [
                'website_dialog_id' => $websiteDialog->id,
                'customer_id'       => $customer->id,
            ],
            [
                'version' => $websiteDialog->published_checksum,
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->websiteDialog->website_id === $this->website->id;
    }

    public function asController(WebsiteDialog $websiteDialog, ActionRequest $request): WebsiteDialogDismissal
    {
        $this->websiteDialog = $websiteDialog;
        $this->initialisation($request);

        return $this->handle($websiteDialog, $request->user()->customer);
    }

    /**
     * @return array{data: array{ulid: string, version: string|null}}
     */
    public function jsonResponse(WebsiteDialogDismissal $dismissal): array
    {
        return [
            'data' => [
                'ulid'    => $this->websiteDialog->ulid,
                'version' => $dismissal->version,
            ]
        ];
    }
}
