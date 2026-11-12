<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Actions\SupplyChain\SupplierShareLink\ResolveSupplierPortalProfile;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierAgreementAcceptance;
use App\Models\SupplyChain\SupplierAgreementVersion;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A supplier accepts the Supplier Agreement from its portal. The person types the company's legal name, their own
 * name and position and ticks the box; the server records the time, the link, where it came from, the language
 * shown and the hash of the exact text, so the proof does not depend on anything the browser says.
 */
class AcceptSupplierAgreement
{
    use AsAction;

    /**
     * @param  array{supplier_legal_name: string, authorised_person: string, position: string, ip?: string|null, country_code?: string|null, user_agent?: string|null, locale?: string|null, supplier_share_link_id?: int|null, supplier_portal_profile_id?: int|null}  $modelData
     */
    public function handle(Supplier $supplier, SupplierAgreementVersion $version, array $modelData): SupplierAgreementAcceptance
    {
        $acceptance = SupplierAgreementAcceptance::create([
            ...$modelData,
            'group_id'                      => $supplier->group_id,
            'supplier_id'                   => $supplier->id,
            'supplier_agreement_version_id' => $version->id,
            'accepted_at'                   => now(),
            'body_hash'                     => $version->bodyHash(),
            'created_at'                    => now(),
        ]);

        if ($acceptance->ip && $acceptance->country_code) {
            LocateSupplierAgreementAcceptance::dispatch($acceptance);
        }

        return $acceptance;
    }

    public function asController(string $token, Request $request): RedirectResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $current = GetSupplierAgreementStatus::make()->currentVersion($link->group_id);

        $validated = Validator::make($request->all(), [
            'supplier_agreement_version_id' => ['required', 'integer'],
            'accept'                        => ['accepted'],
            'supplier_legal_name'           => ['required', 'string', 'max:255'],
            'authorised_person'             => ['required', 'string', 'max:255'],
            'position'                      => ['required', 'string', 'max:255'],
        ], [
            'accept.accepted' => __('Tick the box to accept the agreement.'),
        ])->validate();

        if (!$current || $current->id !== (int) $validated['supplier_agreement_version_id']) {
            throw ValidationException::withMessages(['supplier_agreement_version_id' => __('The agreement has changed since this page was opened. Read the new version and accept it again.')]);
        }

        $alreadyAccepted = SupplierAgreementAcceptance::where('supplier_id', $link->supplier_id)
            ->where('supplier_agreement_version_id', $current->id)
            ->exists();
        if ($alreadyAccepted) {
            return back();
        }

        try {
            $this->handle($link->supplier, $current, [
                'supplier_legal_name'        => trim($validated['supplier_legal_name']),
                'authorised_person'          => trim($validated['authorised_person']),
                'position'                   => trim($validated['position']),
                'supplier_share_link_id'     => $link->id,
                'supplier_portal_profile_id' => ResolveSupplierPortalProfile::make()->handle($link, $request)->id,
                'ip'                         => $request->ip(),
                'country_code'               => mb_substr(strtoupper((string) $request->header('CF-IPCountry')), 0, 2) ?: null,
                'user_agent'                 => mb_substr((string) $request->userAgent(), 0, 1000) ?: null,
                'locale'                     => ShowSupplierPortalLogin::language($request),
            ]);
        } catch (UniqueConstraintViolationException) {
            return back();
        }

        return back();
    }
}
