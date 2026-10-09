<?php

namespace App\Actions\Traits\Authorisations;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
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

    public function canEditSeo(User $user, Shop $shop): bool
    {
        return $user->authTo([
            "web.{$shop->id}.edit",
            "group-webmaster.edit",
        ]) || $this->isShopPpc($user, $shop);
    }
}
