<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget;

use App\Actions\OrgAction;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;

class UpdateShopSalesTarget extends OrgAction
{
    public static function canEdit(User $user, Shop $shop): bool
    {
        return $user->hasRole(RolesEnum::GROUP_ADMIN->value) || $user->authTo('org-admin.'.$shop->organisation_id);
    }

    public function handle(Shop $shop, array $modelData, ?User $user = null): ShopSalesTarget
    {
        $month = Carbon::parse(Arr::get($modelData, 'month', now('UTC')))->startOfMonth()->toDateString();

        return ShopSalesTarget::updateOrCreate(
            ['shop_id' => $shop->id, 'month' => $month],
            [
                'group_id'            => $shop->group_id,
                'organisation_id'     => $shop->organisation_id,
                'target_org_currency' => $modelData['target_org_currency'],
                'set_by_user_id'      => $user?->id,
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canEdit($request->user(), $this->shop);
    }

    public function rules(): array
    {
        return [
            'target_org_currency' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'month'               => ['sometimes', 'date_format:Y-m'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ShopSalesTarget
    {
        $this->shop = $shop;
        $this->initialisation($organisation, $request);

        return $this->handle($shop, $this->validatedData, $request->user());
    }

    public function action(Shop $shop, array $modelData): ShopSalesTarget
    {
        $this->asAction = true;
        $this->shop     = $shop;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
