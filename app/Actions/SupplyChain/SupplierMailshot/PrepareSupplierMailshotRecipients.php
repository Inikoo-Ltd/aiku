<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierMailshot;

use App\Actions\Comms\Mailshot\ProcessSendMailshot;
use App\Actions\Comms\Mailshot\UpdateMailshotRecipientsStoredAt;
use App\Models\Comms\Mailshot;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Who an email to suppliers goes to: every email of the group's active suppliers in the chosen countries that may
 * still get emails from us.
 */
class PrepareSupplierMailshotRecipients
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function audienceQuery(Mailshot $mailshot): Builder
    {
        return DB::table('supplier_portal_contacts')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_portal_contacts.supplier_id')
            ->join('addresses', 'addresses.id', '=', 'suppliers.address_id')
            ->where('suppliers.group_id', $mailshot->group_id)
            ->where('suppliers.status', true)
            ->whereNull('suppliers.deleted_at')
            ->whereIn('addresses.country_id', Arr::get($mailshot->recipients_recipe, 'countries', []))
            ->whereNull('supplier_portal_contacts.revoked_at')
            ->where('supplier_portal_contacts.is_subscribed_to_newsletter', true);
    }

    public function handle(Mailshot $mailshot): void
    {
        $contactIds = $this->audienceQuery($mailshot)
            ->orderBy('supplier_portal_contacts.id')
            ->get(['supplier_portal_contacts.id', 'supplier_portal_contacts.email'])
            ->filter(fn ($contact) => filter_var($contact->email, FILTER_VALIDATE_EMAIL))
            ->pluck('id');

        foreach ($contactIds->chunk(50) as $chunk) {
            ProcessSendMailshot::dispatch($mailshot->id, [], $chunk->values()->all());
        }

        $mailshot->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $contactIds->count(),
        ]);
        UpdateMailshotRecipientsStoredAt::run($mailshot);
    }
}
