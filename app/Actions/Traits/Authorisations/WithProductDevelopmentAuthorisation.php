<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-16h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Traits\Authorisations;

use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithProductDevelopmentAuthorisation
{
    private ?ProductDevelopmentAccess $productDevelopmentAccess = null;

    public function productDevelopmentAccess(): ProductDevelopmentAccess
    {
        return $this->productDevelopmentAccess ??= new ProductDevelopmentAccess();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user          = $request->user();
        $this->canEdit = $user->authTo('goods.edit');

        $productDevelopment = $request->route('productDevelopment');
        if ($productDevelopment instanceof ProductDevelopment) {
            return $this->canViewProductDevelopment($user, $productDevelopment);
        }

        return $this->productDevelopmentAccess()->canSeeAny($user);
    }

    public function isProductDevelopmentStaff(User $user): bool
    {
        return $this->productDevelopmentAccess()->isStaff($user);
    }

    public function canViewProductDevelopment(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->productDevelopmentAccess()->canView($user, $productDevelopment);
    }

    public function canSeeProductDevelopmentCosts(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->productDevelopmentAccess()->canSeeCosts($user, $productDevelopment);
    }

    public function canCreateProductDevelopment(User $user): bool
    {
        return $user->authTo('goods.edit');
    }

    public function canEditProductDevelopment(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->productDevelopmentAccess()->canEdit($user, $productDevelopment);
    }

    /**
     * @return array<int, ProductDevelopmentMemberRoleEnum>
     */
    public function memberRoles(User $user, ProductDevelopment $productDevelopment): array
    {
        return $this->productDevelopmentAccess()->memberRoles($user, $productDevelopment);
    }

    public function canMoveProductDevelopmentStage(User $user, ProductDevelopment $productDevelopment): bool
    {
        if (!$this->productDevelopmentAccess()->isOpenForChanges($productDevelopment)) {
            return false;
        }

        if ($this->canEditProductDevelopment($user, $productDevelopment)) {
            return true;
        }

        $roles = array_map(fn (ProductDevelopmentMemberRoleEnum $role) => $role->value, $this->memberRoles($user, $productDevelopment));

        $currentStageRoles = $productDevelopment->stages
            ->where('position', $productDevelopment->current_position)
            ->map(fn ($stage) => $stage->role->value)
            ->all();

        return count(array_intersect($roles, $currentStageRoles)) > 0;
    }
}
