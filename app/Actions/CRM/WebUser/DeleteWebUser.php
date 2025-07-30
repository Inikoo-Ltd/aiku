<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateWebUsers;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateWebUsers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebUsers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebUsers;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\WebUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;


class DeleteWebUser extends OrgAction
{
    use WithCRMEditAuthorisation;

    protected WebUser $webUser;


    /**
     * @throws \Throwable
     */
    public function handle(WebUser $webUser, bool $forceDelete = false): WebUser
    {
        if ($forceDelete) {
            $webUser = DB::transaction(function () use ($webUser) {
                DB::table('web_user_requests')->where('web_user_id', $webUser->id)->delete();
                DB::table('web_user_password_resets')->where('web_user_id', $webUser->id)->delete();
                DB::table('web_user_stats')->where('web_user_id', $webUser->id)->delete();
                $webUser->forceDelete();
                return $webUser;
            });
        } else {
            $webUser->delete();
        }

        Event::dispatch(new AuditCustom($webUser));
        GroupHydrateWebUsers::dispatch($webUser->group);
        OrganisationHydrateWebUsers::dispatch($webUser->organisation);
        ShopHydrateWebUsers::dispatch($webUser->shop);
        CustomerHydrateWebUsers::dispatch($webUser->customer);

        return $webUser;
    }

    /**
     * @throws \Throwable
     */
    public function action(WebUser $webUser, bool $forceDelete = false): WebUser
    {
        $this->webUser = $webUser;

        return $this->handle($webUser, $forceDelete);
    }

    /**
     * @throws \Throwable
     */
    public function asController(WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->webUser = $webUser;
        $this->initialisation($webUser->organisation, $request);

        return $this->handle($webUser, true);
    }

    public function htmlResponse(WebUser $webUser): RedirectResponse
    {
        if ($webUser->shop->type === ShopTypeEnum::FULFILMENT) {
            return redirect()->route(
                'grp.org.fulfilments.show.crm.customers.show.web_users.index',
                [
                    'organisation' => $webUser->organisation->slug,
                    'fulfilment' => $webUser->shop->fulfilment->slug,
                    'shop' => $webUser->shop->slug,
                    'fulfilmentCustomer' => $webUser->customer->fulfilmentCustomer->slug,
                ]
            );
        } else {
            return redirect()->route(
                'grp.org.shops.show.crm.customers.show.web_users.index',
                [
                    'organisation' => $webUser->organisation->slug,
                    'shop'         => $webUser->shop->slug,
                    'customer'     => $webUser->customer->slug,
                ]
            );
        }
    }
}
