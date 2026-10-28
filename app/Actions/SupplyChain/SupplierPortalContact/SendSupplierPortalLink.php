<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact;

use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Actions\SupplyChain\SupplierShareLink\StoreSupplierShareLink;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Notifications\SupplierPortalLinkNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The supplier log in page: the supplier's own contact email gets a fresh private link for each supplier it is a contact
 * of, sent to that email only. Any other email gets nothing, and the page says the same either way.
 */
class SendSupplierPortalLink
{
    use AsAction;

    public const int DAYS = 7;

    /**
     * @return int how many links were sent
     */
    public function handle(string $email, string $locale = 'en'): int
    {
        Supplier::where('status', true)
            ->where(fn ($query) => $query->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
                ->orWhereRaw("exists (select 1 from jsonb_array_elements_text(case when jsonb_typeof(data->'extra_emails') = 'array' then data->'extra_emails' else '[]'::jsonb end) as extra(email) where lower(extra.email) = ?)", [mb_strtolower(trim($email))]))
            ->get()
            ->each(fn (Supplier $supplier) => SyncSupplierPortalContact::run($supplier));

        $contacts = SupplierPortalContact::authorised()
            ->where('email', mb_strtolower(trim($email)))
            ->whereHas('supplier', fn ($query) => $query->where('status', true))
            ->with('supplier.group')
            ->get();

        foreach ($contacts as $contact) {
            $result = StoreSupplierShareLink::make()->handle($contact->supplier, [
                'days'                       => self::DAYS,
                'supplier_portal_contact_id' => $contact->id,
            ]);

            $contact->update(['last_link_sent_at' => now(), 'number_links_sent' => $contact->number_links_sent + 1]);

            Notification::route('mail', $contact->email)->notify(
                (new SupplierPortalLinkNotification($result['url'], $contact->supplier->name, $contact->supplier->group->name, self::DAYS))->locale($locale)
            );
        }

        return $contacts->count();
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:255']];
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->handle($request->validated('email'), ShowSupplierPortalLogin::language($request));

        return back()->with('link_requested', true);
    }
}
