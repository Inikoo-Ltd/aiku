<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage;

use App\Actions\Chat\ChatSession\SuggestChatSessionCustomer;
use App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierMessage;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RouteSupplierMessage
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
     * Who is on the other side of a message, in order of certainty: the thread it belongs to, the
     * supplier's, agent's or partner's own address, an address already seen writing for one of
     * them, then their company domain. Two of them answering to the same address or domain is no
     * answer at all, and the mail waits in the inbox for someone to assign it.
     *
     * @param  array<int, string>  $counterpartAddresses
     *
     * @return array{0: OrgSupplier|OrgAgent|OrgPartner|null, 1: SupplierMessageRoutedByEnum|null}
     */
    public function handle(Organisation $organisation, array $counterpartAddresses, ?string $threadId = null): array
    {
        if ($threadId) {
            $threadEmail = SupplierMessage::where('organisation_id', $organisation->id)
                ->where('gmail_thread_id', $threadId)
                ->where(fn ($query) => $query->whereNotNull('org_supplier_id')->orWhereNotNull('org_agent_id')->orWhereNotNull('org_partner_id'))
                ->latest('sent_at')
                ->first();

            if ($threadEmail) {
                return [$threadEmail->counterpart(), SupplierMessageRoutedByEnum::THREAD];
            }
        }

        $addresses = collect($counterpartAddresses)->filter()->map(fn (string $address) => Str::lower(trim($address)))->unique()->values();

        if ($addresses->isEmpty()) {
            return [null, null];
        }

        $directory = $this->directory($organisation);

        $byAddress = $directory->filter(fn (array $entry) => $addresses->contains($entry['email']))->unique('key');

        if ($byAddress->count() === 1) {
            return [$byAddress->first()['counterpart'], SupplierMessageRoutedByEnum::ADDRESS];
        }

        if ($byAddress->isEmpty()) {
            $learned = SupplierMessage::where('organisation_id', $organisation->id)
                ->whereIn(DB::raw('lower(from_address)'), $addresses->all())
                ->where(fn ($query) => $query->whereNotNull('org_supplier_id')->orWhereNotNull('org_agent_id')->orWhereNotNull('org_partner_id'))
                ->get(['org_supplier_id', 'org_agent_id', 'org_partner_id'])
                ->unique(fn (SupplierMessage $email) => $email->org_supplier_id.'-'.$email->org_agent_id.'-'.$email->org_partner_id);

            if ($learned->count() === 1) {
                return [$learned->first()->counterpart(), SupplierMessageRoutedByEnum::ADDRESS];
            }
        }

        $domains = $addresses->map(fn (string $address) => Str::after($address, '@'))
            ->reject(fn (string $domain) => $domain === '' || self::isFreeMailDomain($domain))
            ->unique();

        if ($domains->isNotEmpty()) {
            $byDomain = $directory->filter(fn (array $entry) => $domains->contains(Str::after($entry['email'], '@')))->unique('key');

            if ($byDomain->count() === 1) {
                return [$byDomain->first()['counterpart'], SupplierMessageRoutedByEnum::DOMAIN];
            }
        }

        return [null, null];
    }

    /**
     * A WhatsApp conversation is its phone number, so a number already routed keeps its owner;
     * otherwise the number is looked up among the suppliers', agents' and partners' phones.
     * Numbers are stored however they were typed, so both sides are compared as digits, and a
     * number saved without its country code is matched on its last nine digits.
     *
     * @return array{0: OrgSupplier|OrgAgent|OrgPartner|null, 1: SupplierMessageRoutedByEnum|null}
     */
    public function byPhone(Organisation $organisation, string $phone): array
    {
        $digits = self::phoneDigits($phone);

        if (strlen($digits) < 7) {
            return [null, null];
        }

        $previous = SupplierMessage::where('organisation_id', $organisation->id)
            ->where('phone_number', $digits)
            ->where(fn ($query) => $query->whereNotNull('org_supplier_id')->orWhereNotNull('org_agent_id')->orWhereNotNull('org_partner_id'))
            ->latest('sent_at')
            ->first();

        if ($previous) {
            return [$previous->counterpart(), SupplierMessageRoutedByEnum::THREAD];
        }

        $matches = $this->phoneDirectory($organisation)
            ->filter(fn (array $entry) => $entry['phone'] === $digits
                || (strlen($entry['phone']) >= 9 && str_ends_with($digits, substr($entry['phone'], -9))))
            ->unique('key');

        return $matches->count() === 1
            ? [$matches->first()['counterpart'], SupplierMessageRoutedByEnum::ADDRESS]
            : [null, null];
    }

    public static function phoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    /**
     * @return Collection<int, array{key: string, phone: string, counterpart: OrgSupplier|OrgAgent|OrgPartner}>
     */
    private function phoneDirectory(Organisation $organisation): Collection
    {
        $suppliers = OrgSupplier::where('org_suppliers.organisation_id', $organisation->id)
            ->join('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->whereNotNull('suppliers.phone')
            ->get(['org_suppliers.*', 'suppliers.phone as counterpart_phone']);

        $agents = OrgAgent::where('org_agents.organisation_id', $organisation->id)
            ->join('agents', 'agents.id', 'org_agents.agent_id')
            ->join('organisations', 'organisations.id', 'agents.organisation_id')
            ->whereNotNull('organisations.phone')
            ->get(['org_agents.*', 'organisations.phone as counterpart_phone']);

        $partners = OrgPartner::where('org_partners.organisation_id', $organisation->id)
            ->join('organisations', 'organisations.id', 'org_partners.partner_id')
            ->whereNotNull('organisations.phone')
            ->get(['org_partners.*', 'organisations.phone as counterpart_phone']);

        return $suppliers->concat($agents)->concat($partners)
            ->map(fn ($counterpart) => [
                'key'         => class_basename($counterpart).':'.$counterpart->id,
                'phone'       => ltrim(self::phoneDigits((string) $counterpart->counterpart_phone), '0'),
                'counterpart' => $counterpart,
            ])
            ->filter(fn (array $entry) => strlen($entry['phone']) >= 7)
            ->values();
    }

    public static function isFreeMailDomain(string $domain): bool
    {
        return in_array($domain, SuggestChatSessionCustomer::FREE_MAIL_DOMAINS, true) || in_array($domain, self::FREE_MAIL_DOMAINS, true);
    }

    /**
     * ponytail: every supplier, agent and partner of the organisation, loaded per message; a few
     * hundred rows. Cache it per organisation if the mailbox ever gets busy enough to notice.
     *
     * @return Collection<int, array{key: string, email: string, counterpart: OrgSupplier|OrgAgent|OrgPartner}>
     */
    private function directory(Organisation $organisation): Collection
    {
        $suppliers = OrgSupplier::where('org_suppliers.organisation_id', $organisation->id)
            ->join('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->whereNotNull('suppliers.email')
            ->get(['org_suppliers.*', 'suppliers.email as counterpart_email']);

        $agents = OrgAgent::where('org_agents.organisation_id', $organisation->id)
            ->join('agents', 'agents.id', 'org_agents.agent_id')
            ->join('organisations', 'organisations.id', 'agents.organisation_id')
            ->whereNotNull('organisations.email')
            ->get(['org_agents.*', 'organisations.email as counterpart_email']);

        $partners = OrgPartner::where('org_partners.organisation_id', $organisation->id)
            ->join('organisations', 'organisations.id', 'org_partners.partner_id')
            ->whereNotNull('organisations.email')
            ->get(['org_partners.*', 'organisations.email as counterpart_email']);

        return $suppliers->concat($agents)->concat($partners)
            ->map(fn ($counterpart) => [
                'key'         => class_basename($counterpart).':'.$counterpart->id,
                'email'       => Str::lower(trim((string) $counterpart->counterpart_email)),
                'counterpart' => $counterpart,
            ])
            ->filter(fn (array $entry) => $entry['email'] !== '')
            ->values();
    }
}
