<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 16:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier\Json;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\Supplier\SetSuppliersRelationshipContact;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class GetSuppliersRelationshipContactOptions extends OrgAction
{
    /**
     * @param  array{suppliers: array<int, int>}  $modelData
     * @return array{options: list<array{value: int, label: string, organisations: list<string>, suppliers_count: int}>, suppliers_count: int}
     */
    public function handle(Group $group, array $modelData): array
    {
        $suppliers = Supplier::where('group_id', $group->id)->whereIn('id', $modelData['suppliers'])->get();

        return [
            'options'         => SetSuppliersRelationshipContact::contactOptions($suppliers),
            'suppliers_count' => $suppliers->count(),
        ];
    }

    public function rules(): array
    {
        return [
            'suppliers'   => ['required', 'array', 'min:1', 'max:500'],
            'suppliers.*' => ['integer', Rule::exists('suppliers', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return SetSuppliersRelationshipContact::canBeSetBy($request->user(), (array) $request->input('suppliers', []));
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $this->validatedData);
    }

    /**
     * @param  array{options: list<array<string, mixed>>, suppliers_count: int}  $result
     */
    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json($result);
    }
}
