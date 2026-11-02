<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-20h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The final approval: the buyer and compliance each sign off the finished artwork. Once both have,
 * the artwork is locked and the development can move on to the supplier; until it does, a signer can
 * withdraw their sign-off, which unlocks it again.
 */
class SignOffProductDevelopment extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string SIGN = 'sign';
    public const string WITHDRAW = 'withdraw';

    public const array SIGNERS = [ProductDevelopmentMemberRoleEnum::BUYER, ProductDevelopmentMemberRoleEnum::COMPLIANCE];

    private ProductDevelopment $productDevelopment;

    private User $signer;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $request->route('productDevelopment'));
    }

    public function handle(ProductDevelopment $productDevelopment, User $user, array $modelData): ProductDevelopment
    {
        $role = ProductDevelopmentMemberRoleEnum::from($modelData['role']);
        $data = $productDevelopment->data ?? [];

        if ($modelData['operation'] === self::SIGN) {
            $data['sign_off'][$role->value] = [
                'user_id'  => $user->id,
                'name'     => $user->contact_name ?: $user->username,
                'at'       => now()->toIso8601String(),
                'as_owner' => !in_array($role, (new ProductDevelopmentAccess())->memberRoles($user, $productDevelopment), true),
            ];
        } else {
            unset($data['sign_off'][$role->value]);
        }

        $allSigned                 = collect(self::SIGNERS)->every(fn (ProductDevelopmentMemberRoleEnum $signer) => !empty($data['sign_off'][$signer->value] ?? null));
        $data['artwork_locked_at'] = $allSigned ? ($data['artwork_locked_at'] ?? now()->toIso8601String()) : null;

        $productDevelopment->updateQuietly(['data' => $data]);

        RecordProductDevelopmentActivity::record($productDevelopment, $modelData['operation'] === self::SIGN ? RecordProductDevelopmentActivity::SIGNED_OFF : RecordProductDevelopmentActivity::SIGN_OFF_WITHDRAWN, [], [
            'role'   => $role->value,
            'locked' => $allSigned,
        ]);

        CreateProductDevelopmentTask::make()->syncProgress($productDevelopment, $user);

        return $productDevelopment;
    }

    /**
     * @return array<string, array{user_id: int, name: string, at: string}|null>
     */
    public static function signOffs(ProductDevelopment $productDevelopment): array
    {
        return collect(self::SIGNERS)->mapWithKeys(fn (ProductDevelopmentMemberRoleEnum $role) => [$role->value => Arr::get($productDevelopment->data ?? [], 'sign_off.'.$role->value)])->all();
    }

    /**
     * Who signs off for a role: its members, or the owner when nobody holds it.
     */
    public static function maySignFor(User $user, ProductDevelopment $productDevelopment, ProductDevelopmentMemberRoleEnum $role): bool
    {
        if (in_array($role, (new ProductDevelopmentAccess())->memberRoles($user, $productDevelopment), true)) {
            return true;
        }

        return $productDevelopment->owner_id === $user->id && !self::isRoleHeld($productDevelopment, $role);
    }

    public static function isRoleHeld(ProductDevelopment $productDevelopment, ProductDevelopmentMemberRoleEnum $role): bool
    {
        return $productDevelopment->members()->where('role', $role->value)->exists();
    }

    public static function isFullySigned(ProductDevelopment $productDevelopment): bool
    {
        return collect(self::signOffs($productDevelopment))->every(fn ($signOff) => !empty($signOff));
    }

    public static function isOpenForSignOff(ProductDevelopment $productDevelopment): bool
    {
        return $productDevelopment->status === ProductDevelopmentStatusEnum::ACTIVE
            && $productDevelopment->current_position === ProductDevelopmentStageEnum::FINAL_APPROVAL->position();
    }

    public function rules(): array
    {
        return [
            'role'      => ['required', Rule::in(array_map(fn (ProductDevelopmentMemberRoleEnum $role) => $role->value, self::SIGNERS))],
            'operation' => ['required', Rule::in([self::SIGN, self::WITHDRAW])],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!self::isOpenForSignOff($this->productDevelopment)) {
            $validator->errors()->add('operation', __('Sign-off happens at the final approval stage'));

            return;
        }

        $role = ProductDevelopmentMemberRoleEnum::tryFrom((string) $this->get('role'));
        if ($role && !self::maySignFor($this->signer, $this->productDevelopment, $role)) {
            $validator->errors()->add('role', __('Only a member with the :role role can sign this off', ['role' => mb_strtolower(ProductDevelopmentMemberRoleEnum::labels()[$role->value])]));
        }
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopment
    {
        $this->productDevelopment = $productDevelopment;
        $this->signer             = $request->user();
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, User $user, array $modelData): ProductDevelopment
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->signer             = $user;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle($productDevelopment, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
