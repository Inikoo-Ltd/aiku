<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithGoodsInBookInAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        return $this->authToBookIn($request, 'incoming.%d.edit');
    }

    /**
     * Stock deliveries are not tied to a warehouse, so goods in staff of any of the organisation's warehouses may book them in.
     */
    protected function authToBookIn(ActionRequest $request, string $warehousePermission): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::userCanBookIn($request->user(), $this->organisation, $warehousePermission);
    }

    public static function userCanBookIn(User $user, Organisation $organisation, string $warehousePermission): bool
    {
        if ($user->authTo("procurement.$organisation->id.edit")) {
            return true;
        }

        $warehousePermissions = $organisation->warehouses()->pluck('id')
            ->map(fn (int $warehouseId) => sprintf($warehousePermission, $warehouseId))
            ->all();

        return $warehousePermissions && $user->authTo($warehousePermissions);
    }
}
