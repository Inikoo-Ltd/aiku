<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 15:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalDetails;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sets, or clears with null, the staff member who owns the relationship with each selected supplier, chosen from the
 * contacts the supplier sees on its portal. The portal labels that person Main among "Your contacts".
 */
class SetSuppliersRelationshipContact extends OrgAction
{
    /**
     * A supplier only takes a staff member who is one of its portal contacts; the others are left as they are.
     *
     * @param  array{suppliers: array<int, int>, relationship_contact_user_id: int|null}  $modelData
     * @return array{updated: int, skipped: int}
     */
    public function handle(Group $group, array $modelData): array
    {
        $userId    = isset($modelData['relationship_contact_user_id']) ? (int) $modelData['relationship_contact_user_id'] : null;
        $suppliers = Supplier::where('group_id', $group->id)->whereIn('id', $modelData['suppliers'])->get();

        $supplierIds = $userId === null
            ? $suppliers->pluck('id')
            : $suppliers->filter(fn (Supplier $supplier) => in_array($userId, self::contactUserIds($supplier), true))->pluck('id');

        $updated = Supplier::whereIn('id', $supplierIds)->update(['relationship_contact_user_id' => $userId]);

        return ['updated' => $updated, 'skipped' => $suppliers->count() - $supplierIds->count()];
    }

    /**
     * @return list<int>
     */
    public static function contactUserIds(Supplier $supplier): array
    {
        return GetSupplierPortalDetails::make()->purchasingContacts($supplier)->pluck('user_id')->all();
    }

    /**
     * The portal contacts of the given suppliers, each with how many of them list that person.
     *
     * @param  Collection<int, Supplier>  $suppliers
     * @return list<array{value: int, label: string, organisations: list<string>, suppliers_count: int}>
     */
    public static function contactOptions(Collection $suppliers): array
    {
        return $suppliers
            ->flatMap(fn (Supplier $supplier) => GetSupplierPortalDetails::make()->purchasingContacts($supplier))
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => [
                'value'           => $rows->first()['user_id'],
                'label'           => $rows->first()['name'] ?? '',
                'organisations'   => $rows->pluck('organisations')->flatten()->unique()->values()->all(),
                'suppliers_count' => $rows->count(),
            ])
            ->sortBy([['suppliers_count', 'desc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    public static function portalContactRule(Supplier $supplier): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($supplier) {
            if ($value !== null && !in_array((int) $value, self::contactUserIds($supplier), true)) {
                $fail(__('Choose one of the contacts shown on this supplier\'s portal.'));
            }
        };
    }

    public function rules(): array
    {
        return [
            'suppliers'                    => ['required', 'array', 'min:1', 'max:500'],
            'suppliers.*'                  => ['integer', Rule::exists('suppliers', 'id')->where('group_id', $this->group->id)],
            'relationship_contact_user_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)->where('status', true)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(GroupPermissionsEnum::SUPPLY_CHAIN_EDIT->value);
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $this->validatedData);
    }

    /**
     * @param  array{updated: int, skipped: int}  $result
     */
    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json($result);
    }
}
