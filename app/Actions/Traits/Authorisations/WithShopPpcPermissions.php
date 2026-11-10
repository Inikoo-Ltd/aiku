<?php

namespace App\Actions\Traits\Authorisations;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

trait WithShopPpcPermissions
{
    public function isShopPpc(User $user, Shop $shop): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', 'User')
            ->where('model_has_roles.model_id', $user->permissionsHolder()->id)
            ->where('roles.name', RolesEnum::getRoleName(RolesEnum::SHOP_PPC->value, $shop))
            ->exists();
    }

    public function canEditGoogleAds(User $user, Shop $shop): bool
    {
        return $user->authTo([
            "crm.{$shop->id}.edit",
            "marketing.{$shop->id}.edit",
            "supervisor-marketing.{$shop->id}",
        ]) || $this->isShopPpc($user, $shop);
    }

    public function isGroupAdmin(User $user): bool
    {
        return $user->hasRole([RolesEnum::GROUP_ADMIN->value, RolesEnum::SYSTEM_ADMIN->value]);
    }

    public function isShopAdmin(User $user, Shop $shop): bool
    {
        return $this->isGroupAdmin($user) || $user->authTo([
            "org-admin.{$shop->organisation_id}",
            "shop-admin.{$shop->id}",
        ]);
    }

    public function canViewSeo(User $user, Shop $shop): bool
    {
        return $this->isShopAdmin($user, $shop)
            || $this->isShopPpc($user, $shop)
            || $user->authTo([
                "ppc.{$shop->organisation_id}.view",
                "seo.{$shop->organisation_id}.view",
                "org-supervisor.{$shop->organisation_id}.seo",
            ]);
    }

    public function canEditSeo(?Authenticatable $user, Shop $shop): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $this->isShopAdmin($user, $shop)
            || $this->isShopPpc($user, $shop)
            || $user->authTo([
                "ppc.{$shop->organisation_id}.edit",
                "seo.{$shop->organisation_id}.edit",
                "org-supervisor.{$shop->organisation_id}.seo",
            ]);
    }

    /**
     * The web edit permissions WithWebEditAuthorisation asks of grp.models.webpage.break_cache.
     */
    public function canBreakWebpageCache(?Authenticatable $user, Shop $shop): bool
    {
        return $user instanceof User && $user->authTo([
            'group-webmaster.view',
            "supervisor-web.{$shop->id}",
            "web.{$shop->id}.edit",
        ]);
    }

    /**
     * The group-wide AI Assistant report on WebOps: group admins and group webmasters only, as it
     * shows every shop's customers. Staff of one shop use the report in that website's analytics.
     */
    public function canViewGroupShoppingAssistant(User $user): bool
    {
        return $this->isGroupAdmin($user) || $user->authTo('group-webmaster.view');
    }

    public function canViewGroupSeo(User $user): bool
    {
        if ($this->isGroupAdmin($user)) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', 'User')
            ->where('model_has_roles.model_id', $user->permissionsHolder()->id)
            ->where(fn ($query) => $query
                ->where('roles.name', 'like', RolesEnum::SHOP_PPC->value.'-%')
                ->orWhere('roles.name', 'like', RolesEnum::PPC_CLERK->value.'-%')
                ->orWhere('roles.name', 'like', RolesEnum::PPC_SUPERVISOR->value.'-%')
                ->orWhere('roles.name', 'like', RolesEnum::SEO_CLERK->value.'-%')
                ->orWhere('roles.name', 'like', RolesEnum::SEO_SUPERVISOR->value.'-%')
                ->orWhere('roles.name', 'like', RolesEnum::ORG_ADMIN->value.'-%')
                ->orWhere('roles.name', 'like', RolesEnum::SHOP_ADMIN->value.'-%'))
            ->exists();
    }
}
