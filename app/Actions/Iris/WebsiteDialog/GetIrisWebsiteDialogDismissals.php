<?php

namespace App\Actions\Iris\WebsiteDialog;

use App\Actions\IrisAction;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisWebsiteDialogDismissals extends IrisAction
{
    /**
     * Dialogs of this website the customer already closed, as ulid => published version, so the
     * storefront can keep "once per customer" dialogs closed on every device.
     *
     * @return array<string, string|null>
     */
    public function handle(Website $website, Customer $customer): array
    {
        return DB::table('website_dialog_dismissals')
            ->join('website_dialogs', 'website_dialogs.id', '=', 'website_dialog_dismissals.website_dialog_id')
            ->where('website_dialogs.website_id', $website->id)
            ->where('website_dialog_dismissals.customer_id', $customer->id)
            ->pluck('website_dialog_dismissals.version', 'website_dialogs.ulid')
            ->all();
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->website, $request->user()->customer);
    }

    /**
     * @param array<string, string|null> $dismissals
     * @return array{data: object}
     */
    public function jsonResponse(array $dismissals): array
    {
        return ['data' => (object)$dismissals];
    }
}
