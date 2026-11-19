<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 11:26
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierProduct\Json;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\SupplierProduct\CopySupplierProductsToSupplier;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetSupplierProductsToCopy extends OrgAction
{
    use WithAgentOrganisation;

    private bool $isInGroup = false;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->isInGroup) {
            return CopySupplierProductsToSupplier::make()->canEditFreeSuppliers($request->user());
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(Supplier $supplier, Supplier $sourceSupplier): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereAnyWordStartWith('supplier_products.name', $value);
            });
        });

        $codesInSupplier = DB::table('supplier_products as existing')
            ->select(DB::raw(1))
            ->where('existing.supplier_id', $supplier->id)
            ->whereRaw('lower(existing.code) = lower(supplier_products.code)');

        return QueryBuilder::for(SupplierProduct::class)
            ->where('supplier_products.supplier_id', $sourceSupplier->id)
            ->where('supplier_products.state', '!=', SupplierProductStateEnum::DISCONTINUED)
            ->leftJoin('currencies', 'currencies.id', 'supplier_products.currency_id')
            ->select([
                'supplier_products.id',
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.state',
                'supplier_products.cost',
                'currencies.code as currency_code',
            ])
            ->selectRaw('exists('.$codesInSupplier->toSql().') as already_in_supplier', $codesInSupplier->getBindings())
            ->defaultSort('supplier_products.code')
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, 50)
            ->withQueryString();
    }

    public function rules(): array
    {
        return [
            'source_supplier' => ['required', 'integer'],
        ];
    }

    /**
     * The source supplier comes in the query string, not the route: it may belong to another agent the user edits,
     * and EnsureOrganisationIsAuthorised answers 404 for any route record outside the organisation's agent.
     */
    public function asController(Organisation $organisation, Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        $agentIds       = CopySupplierProductsToSupplier::make()->getAccessibleAgents($request->user())->pluck('id');
        $sourceSupplier = Supplier::whereIn('agent_id', $agentIds)->find($this->validatedData['source_supplier']);

        abort_unless(
            $sourceSupplier
            && $this->getOrganisationAgent($organisation)
            && $agentIds->contains($supplier->agent_id),
            404
        );

        return $this->handle($supplier, $sourceSupplier);
    }

    public function inGroup(Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->isInGroup = true;
        $this->initialisationFromGroup($supplier->group, $request);

        $copySupplierProducts = CopySupplierProductsToSupplier::make();
        $sourceSupplier       = Supplier::where('group_id', $supplier->group_id)->find($this->validatedData['source_supplier']);

        abort_unless(
            $sourceSupplier
            && $copySupplierProducts->canEditSupplier($supplier, $request->user())
            && $copySupplierProducts->canEditSupplier($sourceSupplier, $request->user()),
            404
        );

        return $this->handle($supplier, $sourceSupplier);
    }

    public function jsonResponse(LengthAwarePaginator $supplierProducts): AnonymousResourceCollection
    {
        return JsonResource::collection($supplierProducts);
    }
}
