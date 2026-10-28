<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesFeedback;
use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentSales;
use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Actions\SupplyChain\SupplierShareLink\LocateSupplierShareLinkView;
use App\Actions\SupplyChain\SupplierShareLink\ResolveSupplierPortalProfile;
use App\Actions\SupplyChain\SupplierSubmission\StoreSupplierPortalSubmission;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Http\Resources\SupplyChain\SupplierPortalPurchaseOrdersResource;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a supplier sees through its private link: how its products sell, valued at its own prices and in its own
 * currency, where that puts it in its country, the notes purchasing shared with it, and its purchase orders. Built from the link alone, with no account and no
 * staff layout, so nothing else in Aiku can be reached from it.
 */
class ShowSupplierShare
{
    use AsAction;

    /**
     * @return array<string, string|null>
     */
    private function company(Supplier $supplier): array
    {
        $supplier->loadMissing(['address', 'currency']);

        return [
            'name'         => $supplier->name,
            'contact_name' => $supplier->contact_name,
            'email'        => $supplier->email,
            'phone'        => $supplier->phone,
            'location'     => collect([$supplier->address?->locality, $supplier->address?->country?->name])->filter()->implode(', ') ?: null,
            'currency'     => $supplier->currency?->code,
        ];
    }

    /**
     * The agent that follows the supplier for us, with the person to talk to there.
     *
     * @return array<string, string|null>|null
     */
    private function agent(Supplier $supplier): ?array
    {
        $organisation = $supplier->agent?->organisation;
        if (!$organisation) {
            return null;
        }

        return [
            'name'         => $supplier->agent->name,
            'contact_name' => $organisation->contact_name,
            'email'        => $organisation->email,
            'phone'        => $organisation->phone,
        ];
    }

    /**
     * Everything the supplier sees once in, the same whether the supplier or staff previewing it is looking.
     *
     * @return array<string, mixed>
     */
    public function portal(Supplier $supplier): array
    {
        $valuation = GetAgentSalesProducts::make()->valuedBy(null, null, [])->atSupplierPrices($supplier);

        return [
            'needs_code'           => false,
            'company'              => $this->company($supplier),
            'agent'                => $this->agent($supplier),
            'summary'              => Inertia::defer(fn () => GetSupplierPortalSummary::make()->handle($supplier)),
            'details'              => Inertia::defer(fn () => GetSupplierPortalDetails::make()->handle($supplier), 'details'),
            'valuation'            => $valuation->valuation($supplier),
            'open_purchase_orders' => IndexSupplierPortalPurchaseOrders::make()->openCount($supplier),
            'purchase_orders'      => SupplierPortalPurchaseOrdersResource::collection(IndexSupplierPortalPurchaseOrders::make()->handle($supplier, 'purchase_orders'))
                ->additional(IndexSupplierPortalPurchaseOrders::make()->facets($supplier, 'purchase_orders')),
            'sales'                => Inertia::defer(fn () => ShowAgentSales::make()->handle($supplier, null, $valuation)),
            'feedback'             => Inertia::defer(fn () => GetAgentSalesFeedback::run($supplier), 'feedback'),
        ];
    }

    /**
     * @param  array<string, string>  $submit
     *
     * @return array<string, mixed>
     */
    public function forms(array $submit, string $update): array
    {
        return [
            'submit'         => $submit,
            'update'         => $update,
            'document_types' => collect(SupplyChainAttachmentScopeEnum::labels())->except(['Price list', 'Catalogue'])->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'max_files'      => StoreSupplierPortalSubmission::MAX_FILES,
            'max_file_mb'    => (int) (StoreSupplierPortalSubmission::MAX_FILE_KB / 1024),
        ];
    }

    public function asController(string $token, Request $request): Response
    {
        $link     = SupplierShareLink::findLiveByToken($token);
        $supplier = $link->supplier;
        $page     = [
            'title'    => $supplier->name,
            'supplier' => ['code' => $supplier->code, 'name' => $supplier->name],
            'group'    => $link->group->name,
            'language' => ShowSupplierPortalLogin::language($request),
            'routes'   => [
                'send_code' => route('grp.supplier_share.send_code', $token),
                'verify'    => route('grp.supplier_share.verify', $token),
                'profile'   => route('grp.supplier_share.profile.update', $token),
                'search'    => route('grp.supplier_share.search', $token),
            ],
        ];

        if ($link->needsCode()) {
            $response = Inertia::render('SupplyChain/SupplierShare', [
                ...$page,
                'needs_code' => true,
                'email'      => $link->maskedEmail(),
                'code_sent'  => (bool) session('code_sent'),
            ]);
        } else {
            $viewedKey = 'supplier_share_viewed.'.$link->id;
            if (!$request->session()->has($viewedKey)) {
                $request->session()->put($viewedKey, true);
                $view = $link->views()->create([
                    'ip'           => $request->ip(),
                    'user_agent'   => substr((string) $request->userAgent(), 0, 1000),
                    'country_code' => mb_substr(strtoupper((string) $request->header('CF-IPCountry')), 0, 2) ?: null,
                    'created_at'   => now(),
                ]);
                LocateSupplierShareLinkView::dispatch($view);
                $link->update(['last_viewed_at' => now(), 'number_views' => $link->number_views + 1]);
            }

            $response = Inertia::render('SupplyChain/SupplierShare', [
                ...$page,
                ...$this->portal($supplier),
                'profile'   => ResolveSupplierPortalProfile::make()->toArray(ResolveSupplierPortalProfile::make()->handle($link, $request)),
                'submitted' => session('submitted'),
                'forms'     => $this->forms(
                    collect(SupplierSubmissionTypeEnum::cases())->mapWithKeys(fn (SupplierSubmissionTypeEnum $type) => [$type->value => route('grp.supplier_share.submit', [$token, $type->value])])->all(),
                    route('grp.supplier_share.submission.update', [$token, 0])
                ),
            ])->table(IndexSupplierPortalPurchaseOrders::make()->tableStructure('purchase_orders'));
        }

        return $response->toResponse($request)->withHeaders([
            'Cache-Control'   => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag'    => 'noindex, nofollow',
        ]);
    }
}
