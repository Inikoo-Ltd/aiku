<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact;

use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use Closure;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The supplier's own contact emails, the main one and the other emails, are the ones that can log in by themselves.
 * Every one of them is allowed; an email the supplier no longer has stops working, and so do the links it was sent.
 */
class SyncSupplierPortalContact
{
    use AsObject;

    /**
     * @return list<string>
     */
    public static function emails(Supplier $supplier): array
    {
        return collect([$supplier->email, ...($supplier->data['extra_emails'] ?? [])])
            ->map(fn ($email) => filter_var(mb_strtolower(trim((string) $email)), FILTER_VALIDATE_EMAIL))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Another active supplier that already logs in with this email, the main one or one of its other emails.
     */
    public static function supplierUsingEmail(string $email, ?Supplier $except = null): ?Supplier
    {
        $email = mb_strtolower(trim($email));

        return Supplier::where('status', true)
            ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
            ->where(fn ($query) => $query->whereRaw('lower(email) = ?', [$email])
                ->orWhereRaw("exists (select 1 from jsonb_array_elements_text(case when jsonb_typeof(data->'extra_emails') = 'array' then data->'extra_emails' else '[]'::jsonb end) as extra(email) where lower(extra.email) = ?)", [$email]))
            ->first();
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    public static function notUsedByAnotherSupplierRule(?Supplier $supplier): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($supplier) {
            if (is_string($value) && $other = self::supplierUsingEmail($value, $supplier)) {
                $fail(__(':email already logs in for supplier :supplier.', ['email' => $value, 'supplier' => $other->code]));
            }
        };
    }

    public function handle(Supplier $supplier): void
    {
        $emails = self::emails($supplier);

        SupplierPortalContact::where('supplier_id', $supplier->id)
            ->whereNull('revoked_at')
            ->whereNotIn('email', $emails)
            ->get()
            ->each(fn (SupplierPortalContact $contact) => RevokeSupplierPortalContact::run($contact));

        foreach ($emails as $email) {
            $contact = SupplierPortalContact::firstOrNew(['supplier_id' => $supplier->id, 'email' => $email]);
            $contact->fill([
                'group_id'           => $supplier->group_id,
                'name'               => $contact->name ?? ($email === mb_strtolower(trim((string) $supplier->email)) ? $supplier->contact_name : null),
                'revoked_at'         => null,
                'revoked_by_user_id' => null,
            ]);
            if ($contact->isDirty()) {
                $contact->save();
            }
        }
    }
}
