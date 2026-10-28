<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff see the supplier portal exactly as the supplier does, without a link: no visit is counted, no profile is
 * made, and anything sent from it is refused, so the supplier's activity stays the supplier's.
 */
class ShowSupplierPortalPreview extends OrgAction
{
    use WithSupplyChainAuthorisation;

    public function asController(Supplier $supplier, ActionRequest $request): Response
    {
        $this->initialisationFromGroup($supplier->group, $request);
        $blocked = fn (string $path) => route('grp.supply-chain.suppliers.portal_preview.blocked', [$supplier, $path]);

        return Inertia::render('SupplyChain/SupplierShare', [
            'title'    => $supplier->name,
            'supplier' => ['code' => $supplier->code, 'name' => $supplier->name],
            'group'    => $supplier->group->name,
            'language' => ShowSupplierPortalLogin::language($request),
            'preview'  => true,
            'routes'   => [
                'send_code' => $blocked('code'),
                'verify'    => $blocked('verify'),
                'profile'   => $blocked('profile'),
                'search'    => route('grp.supply-chain.suppliers.portal_preview.search', $supplier),
            ],
            ...ShowSupplierShare::make()->portal($supplier),
            'profile'  => [
                'name'         => null,
                'phone'        => null,
                'theme'        => 0,
                'avatar'       => null,
                'display_name' => $supplier->contact_name ?? $supplier->name,
                'company_logo' => $supplier->imageSources(160, 160),
            ],
            'forms'    => ShowSupplierShare::make()->forms(
                collect(SupplierSubmissionTypeEnum::cases())->mapWithKeys(fn (SupplierSubmissionTypeEnum $type) => [$type->value => $blocked('submissions/'.$type->value)])->all(),
                $blocked('submissions/0/update')
            ),
        ])->table(IndexSupplierPortalPurchaseOrders::make()->tableStructure('purchase_orders'))
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

    public function blocked(): never
    {
        throw ValidationException::withMessages(['preview' => __('This is a preview: nothing is sent or saved.')]);
    }
}
