<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 16:41:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Models\CRM\WebUser;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('shopify.upload-product.{shopifyUserId}', function (WebUser|ShopifyUser $user, int|string $shopifyUserId) {
    return true;
});

Broadcast::channel('grp.personal.{userID}', function (User $user, int $userID) {
    return $userID === $user->id;
});

Broadcast::channel('grp.download-progress.{userID}', function (User $user, int $userID) {
    return $userID === $user->id;
});

Broadcast::channel('grp.{groupID}.general', function (User $user, int $groupID) {
    return $user->group_id === $groupID;
});

Broadcast::channel('grp.{groupID}.fulfilmentCustomer.{userID}', function (User $user, int $groupID, int $userID) {
    return $user->id === $userID;
});

Broadcast::channel('grp.live.users', function (User $user) {
    return [
        'id'    => $user->id,
        'alias' => $user->slug,
        'name'  => $user->contact_name,
    ];
});

Broadcast::channel('retina.{websiteId}.website', function (Webuser $webUser, int|string $websiteId) {
    return $websiteId === $webUser->website_id;
});

Broadcast::channel('retina.{customerID}.customer', function (Webuser $webUser, int|string $customerID) {
    return $customerID === $webUser->customer_id;
});

Broadcast::channel('retina.personal.{webUserID}', function (Webuser $webUser, int|string $webUserID) {
    return $webUserID === $webUser->id;
});


Broadcast::channel('webpage.{webpage}.preview', function (User $user) {
    return true;
});


Broadcast::channel("header-footer.{website}.preview", function (User $user) {
    return true;
});
