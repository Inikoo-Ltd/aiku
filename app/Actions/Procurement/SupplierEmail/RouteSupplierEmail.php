<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierEmail;

use App\Actions\Chat\ChatSession\SuggestChatSessionCustomer;
use App\Enums\Procurement\SupplierEmail\SupplierEmailRoutedByEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierEmail;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RouteSupplierEmail
{
    use AsAction;

    /**
     * Most of our suppliers write from a personal mailbox at one of these. A domain shared by
     * millions says nothing about which supplier is writing, so only a company domain routes.
     */
    public const array FREE_MAIL_DOMAINS = [
        '163.com', '126.com', 'qq.com', 'foxmail.com', 'sina.com', 'sina.cn', 'yeah.net', 'aliyun.com', 'sohu.com',
        'hotmail.fr', 'hotmail.es', 'hotmail.it', 'outlook.es', 'yahoo.in', 'rediffmail.com', 'yandex.ru', 'mail.ru',
    ];

    /**
     * The supplier on the other side of a message, in order of certainty: the thread it belongs
     * to, the supplier's own address, an address already seen writing for a supplier, then the
     * supplier's company domain. Two suppliers answering to the same address or domain is no
     * answer at all, and the mail waits in the inbox for someone to assign it.
     *
     * @param  array<int, string>  $counterpartAddresses
     *
     * @return array{0: OrgSupplier|null, 1: SupplierEmailRoutedByEnum|null}
     */
    public function handle(Organisation $organisation, array $counterpartAddresses, ?string $threadId = null): array
    {
        if ($threadId) {
            $orgSupplierId = SupplierEmail::where('organisation_id', $organisation->id)
                ->where('gmail_thread_id', $threadId)
                ->whereNotNull('org_supplier_id')
                ->latest('sent_at')
                ->value('org_supplier_id');

            if ($orgSupplierId) {
                return [OrgSupplier::find($orgSupplierId), SupplierEmailRoutedByEnum::THREAD];
            }
        }

        $addresses = collect($counterpartAddresses)->filter()->map(fn (string $address) => Str::lower(trim($address)))->unique()->values();

        if ($addresses->isEmpty()) {
            return [null, null];
        }

        $byAddress = $this->orgSuppliers($organisation)
            ->whereIn(DB::raw('lower(suppliers.email::text)'), $addresses->all())
            ->get();

        if ($byAddress->count() === 1) {
            return [$byAddress->first(), SupplierEmailRoutedByEnum::ADDRESS];
        }

        if ($byAddress->isEmpty()) {
            $learnedIds = SupplierEmail::where('organisation_id', $organisation->id)
                ->whereIn(DB::raw('lower(from_address)'), $addresses->all())
                ->whereNotNull('org_supplier_id')
                ->distinct()
                ->pluck('org_supplier_id');

            if ($learnedIds->count() === 1) {
                return [OrgSupplier::find($learnedIds->first()), SupplierEmailRoutedByEnum::ADDRESS];
            }
        }

        $domains = $addresses->map(fn (string $address) => Str::after($address, '@'))
            ->reject(fn (string $domain) => $domain === '' || self::isFreeMailDomain($domain))
            ->unique()
            ->values();

        if ($domains->isNotEmpty()) {
            $byDomain = $this->orgSuppliers($organisation)
                ->whereIn(DB::raw("lower(split_part(suppliers.email::text, '@', 2))"), $domains->all())
                ->get();

            if ($byDomain->count() === 1) {
                return [$byDomain->first(), SupplierEmailRoutedByEnum::DOMAIN];
            }
        }

        return [null, null];
    }

    public static function isFreeMailDomain(string $domain): bool
    {
        return in_array($domain, SuggestChatSessionCustomer::FREE_MAIL_DOMAINS, true) || in_array($domain, self::FREE_MAIL_DOMAINS, true);
    }

    private function orgSuppliers(Organisation $organisation): Builder
    {
        return OrgSupplier::query()
            ->select('org_suppliers.*')
            ->join('suppliers', 'suppliers.id', '=', 'org_suppliers.supplier_id')
            ->where('org_suppliers.organisation_id', $organisation->id)
            ->whereNotNull('suppliers.email');
    }
}
