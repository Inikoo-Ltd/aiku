<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Models\SupplyChain\SupplierPortalProfile;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Who is looking at the supplier page. An email that logged in by itself is that person. A direct link is given
 * to one person, so it is theirs too, and a cookie recognises them when they come back on the same device. A
 * profile never crosses to another supplier, whatever the cookie says.
 */
class ResolveSupplierPortalProfile
{
    use AsObject;

    public const string COOKIE = 'supplier_portal_profile';

    public function handle(SupplierShareLink $link, Request $request): SupplierPortalProfile
    {
        $fromCookie = SupplierPortalProfile::where('ulid', (string) $request->cookie(self::COOKIE))
            ->where('supplier_id', $link->supplier_id)
            ->first();

        if ($link->supplier_portal_contact_id) {
            $profile = SupplierPortalProfile::where('supplier_portal_contact_id', $link->supplier_portal_contact_id)->first();
            if (!$profile && $fromCookie && !$fromCookie->supplier_portal_contact_id) {
                $fromCookie->update(['supplier_portal_contact_id' => $link->supplier_portal_contact_id]);
                $profile = $fromCookie;
            }
            $profile ??= SupplierPortalProfile::create([
                'group_id'                   => $link->group_id,
                'supplier_id'                => $link->supplier_id,
                'supplier_portal_contact_id' => $link->supplier_portal_contact_id,
                'name'                       => $link->portalContact?->name,
            ]);
        } else {
            $profile = $fromCookie
                ?? SupplierPortalProfile::where('supplier_share_link_id', $link->id)->latest('id')->first()
                ?? SupplierPortalProfile::create([
                    'group_id'               => $link->group_id,
                    'supplier_id'            => $link->supplier_id,
                    'supplier_share_link_id' => $link->id,
                ]);
        }

        Cookie::queue(self::COOKIE, $profile->ulid, 60 * 24 * 365);

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(SupplierPortalProfile $profile): array
    {
        $supplier = $profile->supplier;

        return [
            'name'         => $profile->name,
            'phone'        => $profile->phone,
            'theme'        => (int) $profile->theme,
            'avatar'       => $profile->imageSources(160, 160),
            'display_name' => $profile->name ?? $profile->portalContact?->name ?? $supplier->contact_name ?? $supplier->name,
            'company_logo' => $supplier->imageSources(160, 160),
        ];
    }
}
