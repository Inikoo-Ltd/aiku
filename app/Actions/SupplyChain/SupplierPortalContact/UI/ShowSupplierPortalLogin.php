<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact\UI;

use App\Actions\SupplyChain\SupplierPortalContact\SendSupplierPortalLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class ShowSupplierPortalLogin
{
    use AsAction;

    /**
     * The languages most of our suppliers read, by the country Cloudflare sees the visitor in.
     */
    public const array LANGUAGE_BY_COUNTRY = [
        'CN' => 'zh-Hans',
        'HK' => 'zh-Hans',
        'ID' => 'id',
        'IN' => 'hi',
        'NP' => 'ne',
        'SK' => 'sk',
        'CZ' => 'cs',
        'ES' => 'es',
    ];

    /**
     * Every language the portal can be read in.
     *
     * @return array<int, string>
     */
    public static function languages(): array
    {
        return array_values(array_unique(['en', ...self::LANGUAGE_BY_COUNTRY]));
    }

    /**
     * The language the visitor picked last time, else the usual one in the country Cloudflare sees them in.
     */
    public static function language(Request $request): string
    {
        $chosen = $request->cookie('supplier_portal_language');
        if ($chosen === 'en' || in_array($chosen, self::LANGUAGE_BY_COUNTRY, true)) {
            return $chosen;
        }

        return self::LANGUAGE_BY_COUNTRY[strtoupper((string) $request->header('CF-IPCountry'))] ?? 'en';
    }

    public function asController(Request $request): Response
    {
        $group = group();

        return Inertia::render('SupplyChain/SupplierPortalLogin', [
            'title'          => __('Supplier sign in'),
            'link_requested' => (bool) session('link_requested'),
            'days'           => SendSupplierPortalLink::DAYS,
            'company'        => $group->name,
            'language'       => self::language($request),
            'routes'         => ['send' => route('grp.supplier_portal.send_link')],
        ])->withViewData([
            'socialMeta' => [
                'title'       => __('Supplier sign in').' - '.$group->name,
                'site_name'   => $group->name,
                'description' => __('Enter the email your company has with :company. We email you a private link to your page. No password needed.', ['company' => $group->name]),
                'image'       => asset('art/supplier-portal-og.png'),
                'image_alt'   => 'AW Group: Ancient Wisdom, AW Artisan, AW Gifts',
                'icon'        => $group->imageSources(64, 64)['png'] ?? null,
                'url'         => route('grp.supplier_portal.login'),
            ],
        ])->toResponse($request)->withHeaders([
            'Cache-Control'   => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag'    => 'noindex, nofollow',
        ]);
    }
}
