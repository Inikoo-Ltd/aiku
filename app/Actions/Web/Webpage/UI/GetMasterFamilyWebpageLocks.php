<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterFamilyWebpageLocks
{
    use AsObject;

    /**
     * @return array{webpages: array<int, array<string, mixed>>, lock_route: array{name: string, parameters: array<string, int>}, unlock_route: array{name: string, parameters: array<string, int>}}
     */
    public function handle(MasterProductCategory $masterFamily, ?User $user): array
    {
        $webpages = $masterFamily->productCategories()
            ->whereNotNull('webpage_id')
            ->with(['shop', 'webpage.website', 'webpage.lockedBy'])
            ->get()
            ->filter(fn (ProductCategory $family) => $family->webpage)
            ->map(fn (ProductCategory $family) => [
                'id'             => $family->webpage->id,
                'code'           => $family->webpage->code,
                'shop_code'      => $family->shop->code,
                'shop_name'      => $family->shop->name,
                'website_domain' => $family->webpage->website?->domain,
                'is_locked'      => $family->webpage->isLocked(),
                'locked_by'      => $family->webpage->lockedBy?->contact_name ?: $family->webpage->lockedBy?->username,
                'lock_scope'     => $family->webpage->isLocked() ? Arr::get($family->webpage->lock_data, 'scope', 'webpage') : null,
                'can_manage'     => $family->webpage->canManageLockBy($user),
                'can_edit_lock'  => $family->webpage->canEditLockBy($user),
            ])
            ->sortBy('shop_code')
            ->values()
            ->all();

        return [
            'webpages'   => $webpages,
            'lock_route' => [
                'name'       => 'grp.models.master_product_category.lock_webpages',
                'parameters' => ['masterProductCategory' => $masterFamily->id],
            ],
            'unlock_route' => [
                'name'       => 'grp.models.master_product_category.unlock_webpages',
                'parameters' => ['masterProductCategory' => $masterFamily->id],
            ],
        ];
    }
}
