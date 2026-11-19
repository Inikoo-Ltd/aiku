<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Actions\SupplyChain\SupplierSubmission\ImportSupplierPortalProductProposals;
use App\Actions\Traits\Authorisations\SupplierPortalPermissions;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff see the supplier portal exactly as the supplier does, without a link: no visit is counted, no profile is
 * made, and anything sent from it is refused, so the supplier's activity stays the supplier's.
 */
class ShowSupplierPortalPreview extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $supplier = $request->route('supplier');

        return $supplier instanceof Supplier && SupplierPortalPermissions::canView($request->user(), $supplier);
    }

    public function asController(Supplier $supplier, ActionRequest $request): Response
    {
        $this->initialisationFromGroup($supplier->group, $request);
        $blocked  = fn (string $path) => route('grp.supply-chain.suppliers.portal_preview.blocked', [$supplier, $path]);
        $language = ShowSupplierPortalLogin::language($request);

        return Inertia::render('SupplyChain/SupplierShare', [
            'title'    => $supplier->officialName(),
            'supplier' => ['code' => $supplier->code, 'name' => $supplier->officialName()],
            'group'    => $supplier->group->name,
            'language' => $language,
            'preview'  => true,
            'routes'   => [
                'send_code' => $blocked('code'),
                'verify'    => $blocked('verify'),
                'profile'   => $blocked('profile'),
                'search'    => route('grp.supply-chain.suppliers.portal_preview.search', $supplier),
            ],
            ...ShowSupplierShare::make()->portal($supplier, null, $language),
            'profile'  => [
                'name'         => null,
                'phone'        => null,
                'theme'        => 0,
                'avatar'       => null,
                'display_name' => $supplier->contact_name ?? $supplier->officialName(),
                'company_logo' => $supplier->imageSources(160, 160),
            ],
            'forms'    => ShowSupplierShare::make()->forms(
                collect(SupplierSubmissionTypeEnum::cases())->mapWithKeys(fn (SupplierSubmissionTypeEnum $type) => [$type->value => $blocked('submissions/'.$type->value)])->all(),
                $blocked('submissions/0/update'),
                route('grp.supply-chain.suppliers.portal_preview.product_proposals.template', $supplier),
                route('grp.supply-chain.suppliers.portal_preview.product_proposals.preview', $supplier),
                $blocked('product-proposals/import'),
                $supplier->currency?->code
            ),
        ])->table(IndexSupplierPortalPurchaseOrders::make()->tableStructure('purchase_orders'))
            ->table(IndexSupplierPortalPriceChanges::make()->tableStructure($supplier, 'price_changes'))
            ->toResponse($request);
    }

    public function search(Supplier $supplier, ActionRequest $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            return response()->json(['products' => [], 'orders' => []]);
        }

        return response()->json(SearchSupplierPortal::make()->handle($supplier, $query));
    }

    public function products(Supplier $supplier, ActionRequest $request): JsonResponse
    {
        $products = IndexSupplierPortalProducts::make();

        return $products->fromRequest($supplier, null, Validator::make($request->query(), $products->rules())->validate());
    }

    public function productWebsites(Supplier $supplier, int $supplierProductId, ActionRequest $request): JsonResponse
    {
        return response()->json(GetSupplierPortalProductWebsites::make()->handle($supplier, $supplierProductId));
    }

    public function purchaseOrder(Supplier $supplier, int $purchaseOrderId, ActionRequest $request): JsonResponse
    {
        return response()->json(ShowSupplierPortalPurchaseOrder::make()->handle($supplier, $purchaseOrderId));
    }

    public function productProposalTemplate(Supplier $supplier, ActionRequest $request): BinaryFileResponse
    {
        return DownloadSupplierPortalProductProposalTemplate::make()->handle($supplier);
    }

    public function productProposalPreview(Supplier $supplier, ActionRequest $request): JsonResponse
    {
        $validated = Validator::make($request->allFiles(), ImportSupplierPortalProductProposals::fileRules())->validate();

        return response()->json(['rows' => ImportSupplierPortalProductProposals::make()->previewRows($supplier, $validated['file'])]);
    }

    public function blocked(): never
    {
        throw ValidationException::withMessages(['preview' => __('This is a preview: nothing is sent or saved.')]);
    }
}
