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
use App\Actions\SupplyChain\SupplierAgreement\UI\GetSupplierPortalAgreement;
use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Actions\SupplyChain\SupplierShareLink\LocateSupplierShareLinkView;
use App\Actions\SupplyChain\SupplierShareLink\ResolveSupplierPortalProfile;
use App\Actions\SupplyChain\SupplierSubmission\StoreSupplierPortalSubmission;
use App\Enums\SupplyChain\SupplierPortalContact\SupplierPortalContactDesignationEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Http\Resources\SupplyChain\SupplierPortalPriceChangesResource;
use App\Http\Resources\SupplyChain\SupplierPortalPurchaseOrdersResource;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
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
     * @return array<string, mixed>
     */
    private function company(Supplier $supplier): array
    {
        $supplier->loadMissing(['address', 'currency']);

        return [
            'name'            => $supplier->officialName(),
            'contact_name'    => $supplier->contact_name,
            'email'           => $supplier->email,
            'phone'           => $supplier->phone,
            'contact_website' => $supplier->contact_website,
            'address'         => $supplier->address?->formatted_address ?: null,
            'location'        => collect([$supplier->address?->locality, $supplier->address?->country?->name])->filter()->implode(', ') ?: null,
            'currency'        => $supplier->currency?->code,
            'contacts'        => $this->contacts($supplier),
        ];
    }

    /**
     * The people at the supplier we write to, the main email first.
     *
     * @return array<int, array<string, string|null>>
     */
    private function contacts(Supplier $supplier): array
    {
        $mainEmail = mb_strtolower(trim((string) $supplier->email));

        return SupplierPortalContact::where('supplier_id', $supplier->id)
            ->authorised()
            ->orderBy('id')
            ->get()
            ->sortBy(fn (SupplierPortalContact $contact) => $contact->email === $mainEmail ? 0 : 1)
            ->map(fn (SupplierPortalContact $contact) => [
                'name'        => $contact->name,
                'designation' => $contact->designation?->label(),
                'email'       => $contact->email,
                'mobile'      => $contact->phone,
                'whatsapp'    => $contact->whatsapp,
                'wechat'      => $contact->wechat,
            ])
            ->values()
            ->all();
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
    public function portal(Supplier $supplier, ?string $token = null): array
    {
        $valuation = GetAgentSalesProducts::make()->valuedBy(null, null, [])->atSupplierPrices($supplier);

        return [
            'needs_code'           => false,
            'company'              => $this->company($supplier),
            'agent'                => $this->agent($supplier),
            'owner'                => GetSupplierPortalDetails::make()->owner($supplier),
            'summary'              => Inertia::defer(fn () => GetSupplierPortalSummary::make()->handle($supplier)),
            'details'              => Inertia::defer(fn () => GetSupplierPortalDetails::make()->handle($supplier), 'details'),
            'valuation'            => $valuation->valuation($supplier),
            'open_purchase_orders' => IndexSupplierPortalPurchaseOrders::make()->openCount($supplier),
            'purchase_order_stages' => Inertia::defer(fn () => IndexSupplierPortalPurchaseOrderStages::make()->handle($supplier), 'stages'),
            'purchase_orders'      => SupplierPortalPurchaseOrdersResource::collection(IndexSupplierPortalPurchaseOrders::make()->handle($supplier, 'purchase_orders'))
                ->additional(IndexSupplierPortalPurchaseOrders::make()->facets($supplier, 'purchase_orders')),
            'price_changes'        => SupplierPortalPriceChangesResource::collection(IndexSupplierPortalPriceChanges::make()->handle($supplier, 'price_changes'))
                ->additional(IndexSupplierPortalPriceChanges::make()->facets($supplier, 'price_changes')),
            'sales'                => Inertia::defer(fn () => ShowAgentSales::make()->handle($supplier, null, $valuation)),
            'feedback'             => Inertia::defer(fn () => GetAgentSalesFeedback::run($supplier), 'feedback'),
            'agreement'            => GetSupplierPortalAgreement::make()->handle(
                $supplier,
                $token === null ? route('grp.supply-chain.suppliers.portal_preview.blocked', [$supplier, 'agreement']) : route('grp.supplier_share.agreement.accept', $token)
            ),
            'labels_print'         => $this->labelsPrint($token),
            'products_route'       => $token === null ? route('grp.supply-chain.suppliers.portal_preview.products', $supplier) : route('grp.supplier_share.products', $token),
            'product_websites_route' => $token === null
                ? route('grp.supply-chain.suppliers.portal_preview.product_websites', [$supplier, 'supplierProductId' => 0])
                : route('grp.supplier_share.product_websites', ['token' => $token, 'supplierProduct' => 0]),
            'purchase_order_route' => $token === null
                ? route('grp.supply-chain.suppliers.portal_preview.purchase_order', [$supplier, 'purchaseOrderId' => 0])
                : route('grp.supplier_share.purchase_order', ['token' => $token, 'purchaseOrder' => 0]),
        ];
    }

    /**
     * The supplier prints barcode labels through its own link; staff previewing the page through the staff routes.
     *
     * @return array{plan_route: array{name: string, parameters: array<string, string>}, pdf_route: array{name: string, parameters: array<string, string>}}
     */
    private function labelsPrint(?string $token): array
    {
        if ($token === null) {
            return [
                'plan_route' => ['name' => 'grp.supply-chain.supplier_products.labels.plan', 'parameters' => []],
                'pdf_route'  => ['name' => 'grp.supply-chain.supplier_products.labels', 'parameters' => []],
            ];
        }

        return [
            'plan_route' => ['name' => 'grp.supplier_share.labels.plan', 'parameters' => ['token' => $token]],
            'pdf_route'  => ['name' => 'grp.supplier_share.labels', 'parameters' => ['token' => $token]],
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
            'designations'   => collect(SupplierPortalContactDesignationEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
        ];
    }

    public function asController(string $token, Request $request): Response
    {
        $link     = SupplierShareLink::findLiveByToken($token);
        $supplier = $link->supplier;
        $page     = [
            'title'    => $supplier->officialName(),
            'supplier' => ['code' => $supplier->code, 'name' => $supplier->officialName()],
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
                ...$this->portal($supplier, $token),
                'profile'   => ResolveSupplierPortalProfile::make()->toArray(ResolveSupplierPortalProfile::make()->handle($link, $request)),
                'submitted' => session('submitted'),
                'forms'     => $this->forms(
                    collect(SupplierSubmissionTypeEnum::cases())->mapWithKeys(fn (SupplierSubmissionTypeEnum $type) => [$type->value => route('grp.supplier_share.submit', [$token, $type->value])])->all(),
                    route('grp.supplier_share.submission.update', [$token, 0])
                ),
            ])->table(IndexSupplierPortalPurchaseOrders::make()->tableStructure('purchase_orders'))
                ->table(IndexSupplierPortalPriceChanges::make()->tableStructure($supplier, 'price_changes'));
        }

        return $response->toResponse($request)->withHeaders([
            'Cache-Control'   => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag'    => 'noindex, nofollow',
        ]);
    }
}
