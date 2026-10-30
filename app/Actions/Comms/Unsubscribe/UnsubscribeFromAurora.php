<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 15:34:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Unsubscribe;

use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\CRM\Prospect\UpdateProspectEmailUnsubscribed;
use App\Actions\IrisAction;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class UnsubscribeFromAurora extends IrisAction
{
    public function handle(Organisation $organisation, string $selector, string $authenticator): array
    {
        $recipient = $this->getRecipient($organisation, $selector);
        $key       = config('app.aurora.unsubscribe_keys.'.$organisation->id);

        if (!$recipient || !$key || !$this->isAuthentic($recipient, $key, $selector, $authenticator)) {
            return [
                'api_response_status' => 404,
                'api_response_data'   => [],
            ];
        }

        if ($recipient instanceof Prospect) {
            UpdateProspectEmailUnsubscribed::run($recipient, now());

            return $this->success($recipient->email, $recipient->name);
        }

        UpdateCustomerComms::run(
            $recipient->comms,
            [
                'is_subscribed_to_newsletter'       => false,
                'is_subscribed_to_marketing'        => false,
                'is_subscribed_to_abandoned_cart'   => false,
                'is_subscribed_to_reorder_reminder' => false,
                'is_subscribed_to_basket_low_stock' => false,
                'is_subscribed_to_basket_reminder'  => false,
            ]
        );

        return $this->success($recipient->email, $recipient->contact_name);
    }

    private function getRecipient(Organisation $organisation, string $selector): Customer|Prospect|null
    {
        $sourceId = $organisation->id.':'.$selector;

        foreach ([null, 'archive'] as $connection) {
            if ($connection && !config('database.connections.archive.database')) {
                return null;
            }

            try {
                $db                = DB::connection($connection);
                $dispatchedEmailId = $db->table('dispatched_emails')->where('source_id', $sourceId)->value('id');
                if (!$dispatchedEmailId) {
                    continue;
                }

                $customerId = $db->table('customer_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmailId)->value('customer_id');
                if ($customerId) {
                    return Customer::find($customerId);
                }

                $prospectId = $db->table('prospect_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmailId)->value('prospect_id');

                return $prospectId ? Prospect::find($prospectId) : null;
            } catch (Throwable $exception) {
                Log::warning('Aurora unsubscribe link could not be resolved: '.$exception->getMessage());

                return null;
            }
        }

        return null;
    }

    private function isAuthentic(Customer|Prospect $recipient, string $key, string $selector, string $authenticator): bool
    {
        foreach ([$recipient->source_id, $recipient->post_source_id] as $sourceId) {
            if ($sourceId && hash_equals(hash('sha256', $key.explode(':', $sourceId)[1].$selector), $authenticator)) {
                return true;
            }
        }

        return false;
    }

    private function success(?string $email, ?string $name): array
    {
        return [
            'api_response_status' => 200,
            'api_response_data'   => [
                'recipient_email' => $email,
                'recipient_name'  => $name,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            's' => ['required', 'string', 'regex:/^\d+$/'],
            'a' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->organisation, $this->validatedData['s'], $this->validatedData['a']);
    }
}
