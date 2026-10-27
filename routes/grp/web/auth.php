<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 10 Aug 2022 15:52:49 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

use App\Actions\Comms\Unsubscribe\UnsubscribeMailshot;
use App\Actions\Helpers\Redirects\RedirectUnsubscribe;
use App\Actions\Helpers\Redirects\ShowUnsubscribeError;
use App\Actions\SysAdmin\UI\Auth\Login;
use App\Actions\SysAdmin\UI\Auth\Logout;
use App\Actions\SysAdmin\UI\Auth\ShowLogin;
use App\Actions\SysAdmin\UI\Auth\ShowResetPassword;
use App\Actions\SysAdmin\User\PasswordResetLink;
use App\Actions\SysAdmin\User\UI\ShowSetNewPassword;
use App\Actions\SysAdmin\User\UpdateUserPassword;
use App\Actions\SysAdmin\User\UpdateUserPasswordViaEmail;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrderLabels;
use App\Actions\SupplyChain\SupplierPortalContact\SendSupplierPortalLink;
use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Actions\SupplyChain\SupplierShareLink\SendSupplierShareCode;
use App\Actions\SupplyChain\SupplierSubmission\StoreSupplierPortalSubmission;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierShare;
use App\Actions\SupplyChain\SupplierShareLink\VerifySupplierShareCode;
use App\Http\Middleware\LogUserRequestMiddleware;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;

Route::post('redirect-unsubscribe/{encryptedDispatchedEmailID}', UnsubscribeMailshot::class)->name('one_click_unsubscribe');

Route::prefix('supplier-share/{token}')->as('supplier_share.')->where(['token' => '[A-Za-z0-9]{48}'])
    ->middleware('throttle:supplier-share')->withoutMiddleware(LogUserRequestMiddleware::class)->group(function () {
        Route::get('', ShowSupplierShare::class)->name('show');
        Route::post('code', SendSupplierShareCode::class)->middleware('throttle:supplier-share-code')->name('send_code');
        Route::post('verify', VerifySupplierShareCode::class)->middleware('throttle:supplier-share-verify')->name('verify');
        Route::post('submissions/{type}', StoreSupplierPortalSubmission::class)->middleware('throttle:supplier-portal-submit')->where('type', '[a-z_]+')->name('submit');
        Route::post('submissions/{submission}/update', [StoreSupplierPortalSubmission::class, 'inSubmission'])->middleware('throttle:supplier-portal-submit')->whereNumber('submission')->name('submission.update');
    });

Route::get('purchase-order-labels/{purchaseOrder:id}', PdfPurchaseOrderLabels::class)->middleware(['signed:relative', 'throttle:supplier-share'])
    ->withoutMiddleware(LogUserRequestMiddleware::class)->name('purchase_order_labels');

Route::prefix('supplier-login')->as('supplier_portal.')->middleware('throttle:supplier-share')->withoutMiddleware(LogUserRequestMiddleware::class)->group(function () {
    Route::get('', ShowSupplierPortalLogin::class)->name('login');
    Route::post('', SendSupplierPortalLink::class)->middleware('throttle:supplier-portal-link')->name('send_link');
});

Route::middleware('guest')->group(function () {
    Route::get('resetpassword', ShowResetPassword::class)->name('reset.password');
    Route::get('login', ShowLogin::class)->name('login.show');
    Route::post('login', Login::class)->name('login.store');

    Route::get('passkeys/login/options', [PasskeyLoginController::class, 'index'])->middleware('throttle:6,1')->name('passkey.login-options');
    Route::post('passkeys/login', [PasskeyLoginController::class, 'store'])->middleware('throttle:6,1')->name('passkey.login');

    Route::get('reset-password', ShowSetNewPassword::class)->name('email.reset-password.show');
    Route::post('reset/password/link', PasswordResetLink::class)->middleware('throttle:6,1')->name('password.email');
    Route::patch('reset/password/email', UpdateUserPasswordViaEmail::class)->middleware('throttle:6,1')->name('reset-password.email.update');

    Route::get('redirect-unsubscribe/{encryptedDispatchedEmailID}', RedirectUnsubscribe::class)->name('redirect_unsubscribe');
    Route::get('unsubscribe-error', ShowUnsubscribeError::class)->name('unsubscribe-error');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', Logout::class)->name('logout');

    Route::middleware('two_fa')->group(function () {
        Route::get('user/passkeys/options', [PasskeyRegistrationController::class, 'index'])->name('passkey.registration-options');
        Route::post('user/passkeys', [PasskeyRegistrationController::class, 'store'])->name('passkey.store');
        Route::delete('user/passkeys/{passkey}', [PasskeyRegistrationController::class, 'destroy'])->name('passkey.destroy');
    });
    Route::get('reset/password', ShowSetNewPassword::class)->name('reset-password.edit');
    Route::patch('reset/password', UpdateUserPassword::class)->name('reset-password.update');
});
