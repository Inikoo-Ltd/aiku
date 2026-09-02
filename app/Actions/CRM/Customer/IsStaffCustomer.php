<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Staff shop on our own storefronts with ordinary customer accounts, for testing and casual
 * shopping. Those accounts must never count in customer analytics. A customer is staff when
 * its email uses one of our own domains, or is the login email of a system user or the work
 * email of an employee. Partner organisations (their customer accounts are linked in org_partners)
 * count as staff too: aromatics buying from the gifts shop is the group shopping from itself.
 */
class IsStaffCustomer
{
    use AsObject;

    public function handle(?int $customerId): bool
    {
        if (!$customerId) {
            return false;
        }

        return self::filter(Customer::query()->whereKey($customerId))->exists();
    }

    public static function filter(Builder $customers): Builder
    {
        return $customers->where(function (Builder $query) {
            $query->whereIn(DB::raw("lower(split_part(customers.email collate \"C\", '@', 2))"), self::staffEmailDomains())
                ->orWhereIn(DB::raw('lower(customers.email) collate "C"'), fn ($sub) => $sub->select(DB::raw('lower(email) collate "C"'))->from('users')->whereNotNull('email'))
                ->orWhereIn(DB::raw('lower(customers.email) collate "C"'), fn ($sub) => $sub->select(DB::raw('lower(work_email) collate "C"'))->from('employees')->whereNotNull('work_email'))
                ->orWhereIn('customers.id', fn ($sub) => $sub->select('customer_id')->from('org_partners')->whereNotNull('customer_id'));
        });
    }

    /**
     * @return array<int, string>
     */
    public static function staffEmailDomains(): array
    {
        return Cache::remember('marketing:staff_email_domains', now()->addHour(), function () {
            return DB::table('websites')
                ->whereNotNull('domain')
                ->pluck('domain')
                ->map(fn (string $domain) => preg_replace('/^www\./', '', strtolower(trim($domain))))
                ->merge((array) config('marketing.staff_email_domains', []))
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }
}
