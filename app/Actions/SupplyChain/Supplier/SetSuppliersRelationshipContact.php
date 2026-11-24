<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 15:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\OrgAction;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sets, or clears with null, the staff member who owns the relationship with each selected supplier. The supplier
 * portal labels that person Main among "Your contacts".
 */
class SetSuppliersRelationshipContact extends OrgAction
{
    /**
     * @param  array{suppliers: array<int, int>, relationship_contact_user_id: int|null}  $modelData
     */
    public function handle(Group $group, array $modelData): int
    {
        return Supplier::where('group_id', $group->id)
            ->whereIn('id', $modelData['suppliers'])
            ->update(['relationship_contact_user_id' => $modelData['relationship_contact_user_id'] ?? null]);
    }

    public static function userRule(Group $group): Exists
    {
        return Rule::exists('users', 'id')->where('group_id', $group->id)->where('status', true);
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function userOptions(Group $group): array
    {
        return DB::table('users')
            ->where('group_id', $group->id)
            ->where('status', true)
            ->orderByRaw("coalesce(nullif(contact_name, ''), username)")
            ->get(['id', 'username', 'contact_name'])
            ->map(fn ($user) => [
                'value' => $user->id,
                'label' => $user->contact_name ? "$user->contact_name ($user->username)" : $user->username,
            ])
            ->all();
    }

    public function rules(): array
    {
        return [
            'suppliers'                    => ['required', 'array', 'min:1', 'max:500'],
            'suppliers.*'                  => ['integer', Rule::exists('suppliers', 'id')->where('group_id', $this->group->id)],
            'relationship_contact_user_id' => ['present', 'nullable', 'integer', self::userRule($this->group)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(GroupPermissionsEnum::SUPPLY_CHAIN_EDIT->value);
    }

    public function asController(ActionRequest $request): int
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $this->validatedData);
    }

    public function jsonResponse(int $updatedCount): JsonResponse
    {
        return response()->json(['updated' => $updatedCount]);
    }
}
