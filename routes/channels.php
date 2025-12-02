<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 16:41:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Facades\Broadcast;
use App\Models\CRM\Livechat\ChatAssignment;

Broadcast::channel('shopify.upload-product.{shopifyUserId}', function (WebUser|ShopifyUser $user, int|string $shopifyUserId) {
    return true;
});

Broadcast::channel('shopify.{shopifyUserId}.upload-product.{portfolioId}', function (int|string $shopifyUserId, int|string $portfolioId) {
    return true;
});

Broadcast::channel('woo.{wooCommerceUserId}.upload-product.{portfolioId}', function (int|string $wooCommerceUserId, int|string $portfolioId) {
    return true;
});

Broadcast::channel('ebay.{ebayUserId}.upload-product.{portfolioId}', function (int|string $ebayUser, int|string $portfolioId) {
    return true;
});

Broadcast::channel('amazon.{amazonUserId}.upload-product.{portfolioId}', function (int|string $amazonUser, int|string $portfolioId) {
    return true;
});

Broadcast::channel('magento.{amazonUserId}.upload-product.{portfolioId}', function (int|string $magentoUser, int|string $portfolioId) {
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

Broadcast::channel("app.general", function () {
    return true;
});

Broadcast::channel("grp.dn.{deliveryNoteId}", function () {
    return true;
});

Broadcast::channel("translate.{randomString}.channel", function () {
    return true;
});

Broadcast::channel("upload-portfolio-to-r2.{randomString}", function () {
    return true;
});


Broadcast::channel('chat-session.{ulid}', function () {

    // $session = ChatSession::where('ulid', $ulid)->first();
    // if (! $session) return false;

    // if ($user instanceof User) {

    //     $agent = ChatAgent::where('user_id', $user->id)->first();
    //     if (!$agent) return false;

    //     return ChatAssignment::where('chat_session_id', $session->id)
    //         ->where('chat_agent_id', $agent->id)
    //         ->exists();
    // }

    // $guestIdentifier = request()->header('X-Guest-Identifier');

    // if (!$session->web_user_id && $session->guest_identifier) {
    //     return $session->guest_identifier === $guestIdentifier;
    // }

    // if ($user instanceof WebUser) {
    //     return $session->web_user_id === $user->id;
    // }

    return true;
});