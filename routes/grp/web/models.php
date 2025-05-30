<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 10:25:11 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

use App\Actions\Accounting\InvoiceCategory\StoreInvoiceCategory;
use App\Actions\Accounting\InvoiceCategory\UpdateInvoiceCategory;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProviderAccount;
use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\Accounting\PaymentAccount\UpdatePaymentAccount;
use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Billables\Rental\UpdateRental;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Collection\AttachCollectionToModels;
use App\Actions\Catalogue\Collection\DetachModelFromCollection;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Catalogue\Product\AttachImagesToProduct;
use App\Actions\Catalogue\Product\DeleteImagesFromProduct;
use App\Actions\Catalogue\Product\DeleteProduct;
use App\Actions\Catalogue\Product\MoveFamilyProductToOtherFamily;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UploadImagesToProduct;
use App\Actions\Catalogue\ProductCategory\AttachFamiliesToSubDepartment;
use App\Actions\Catalogue\ProductCategory\DetachFamilyToSubDepartment;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreSubDepartment;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Comms\Email\PublishEmail;
use App\Actions\Comms\Email\UpdateEmailUnpublishedSnapshot;
use App\Actions\Comms\EmailTemplate\UpdateEmailTemplate;
use App\Actions\Comms\EmailTemplate\UploadImagesToEmailTemplate;
use App\Actions\Comms\Mailshot\SendMailshotTest;
use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\Comms\Mailshot\UpdateMailshot;
use App\Actions\Comms\Outbox\PublishOutbox;
use App\Actions\Comms\Outbox\ToggleOutbox;
use App\Actions\Comms\Outbox\UpdateOutbox;
use App\Actions\Comms\Outbox\UpdateWorkshopOutbox;
use App\Actions\Comms\OutboxHasSubscribers\DeleteOutboxHasSubscriber;
use App\Actions\Comms\OutboxHasSubscribers\StoreManyOutboxHasSubscriber;
use App\Actions\CRM\Customer\AddDeliveryAddressToCustomer;
use App\Actions\CRM\Customer\ApproveCustomer;
use App\Actions\CRM\Customer\DeleteCustomerDeliveryAddress;
use App\Actions\CRM\Customer\DeletePortfolio;
use App\Actions\CRM\Customer\RejectCustomer;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateBalanceCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\CRM\Customer\UpdateCustomerAddress;
use App\Actions\CRM\Customer\UpdateCustomerDeliveryAddress;
use App\Actions\CRM\Prospect\ImportShopProspects;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\CRM\WebUser\UpdateWebUser;
use App\Actions\Dispatching\Shipment\DetachShipmentFromPalletReturn;
use App\Actions\Dispatching\Shipment\UI\CreateShipmentInPalletReturnInFulfilment;
use App\Actions\Dispatching\Shipment\UI\CreateShipmentInPalletReturnInWarehouse;
use App\Actions\Dropshipping\Aiku\StoreMultipleManualPortfolios;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Fulfilment\Fulfilment\StoreFulfilmentFromUI;
use App\Actions\Fulfilment\Fulfilment\UpdateFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomerNote;
use App\Actions\Fulfilment\FulfilmentCustomer\UpdateFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentTransaction\DeleteFulfilmentTransaction;
use App\Actions\Fulfilment\FulfilmentTransaction\StoreFulfilmentTransaction;
use App\Actions\Fulfilment\FulfilmentTransaction\UpdateFulfilmentTransaction;
use App\Actions\Fulfilment\Pallet\AttachPalletsToReturn;
use App\Actions\Fulfilment\Pallet\AttachPalletToReturn;
use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Fulfilment\Pallet\DeletePallet;
use App\Actions\Fulfilment\Pallet\DeleteStoredPallet;
use App\Actions\Fulfilment\Pallet\ImportPalletReturnItem;
use App\Actions\Fulfilment\Pallet\PickWholePalletInPalletReturn;
use App\Actions\Fulfilment\Pallet\ReturnPallet;
use App\Actions\Fulfilment\Pallet\SetPalletAsDamaged;
use App\Actions\Fulfilment\Pallet\SetPalletAsLost;
use App\Actions\Fulfilment\Pallet\SetPalletAsNotReceived;
use App\Actions\Fulfilment\Pallet\SetPalletRental;
use App\Actions\Fulfilment\Pallet\StoreMultiplePalletsFromDelivery;
use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Actions\Fulfilment\Pallet\UndoBookedInPallet;
use App\Actions\Fulfilment\Pallet\UndoNotReceivedPallet;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\Pallet\UpdatePalletLocation;
use App\Actions\Fulfilment\PalletDelivery\CancelPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\DeleteBookedInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\DeletePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ImportPalletsInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ImportPalletsInPalletDeliveryWithStoredItems;
use App\Actions\Fulfilment\PalletDelivery\Pdf\PdfPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAsBookedIn;
use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SubmitAndConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDelivery;
use App\Actions\Fulfilment\PalletReturn\AddAddressToPalletReturn;
use App\Actions\Fulfilment\PalletReturn\DeleteDispatchedPalletReturn;
use App\Actions\Fulfilment\PalletReturn\DeletePalletReturn;
use App\Actions\Fulfilment\PalletReturn\DeletePalletReturnAddress;
use App\Actions\Fulfilment\PalletReturn\DetachPalletFromReturn;
use App\Actions\Fulfilment\PalletReturn\DispatchPalletReturn;
use App\Actions\Fulfilment\PalletReturn\Pdf\PdfPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickedPalletReturnWithStoredItems;
use App\Actions\Fulfilment\PalletReturn\RevertPalletReturnToInProcess;
use App\Actions\Fulfilment\PalletReturn\SwitchPalletReturnDeliveryAddress;
use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturn;
use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturnDeliveryAddress;
use App\Actions\Fulfilment\PalletReturnItem\NotPickedPalletFromReturn;
use App\Actions\Fulfilment\PalletReturnItem\PickNewPalletReturnItem;
use App\Actions\Fulfilment\PalletReturnItem\PickPalletReturnItemInPalletReturnWithStoredItem;
use App\Actions\Fulfilment\PalletReturnItem\SyncPalletReturnItem;
use App\Actions\Fulfilment\PalletReturnItem\UndoPalletReturnItem;
use App\Actions\Fulfilment\PalletReturnItem\UndoPickingPalletFromReturn;
use App\Actions\Fulfilment\PalletReturnItem\UndoStoredItemPick;
use App\Actions\Fulfilment\RecurringBill\ConsolidateRecurringBill;
use App\Actions\Fulfilment\RecurringBill\UpdateRecurringBilling;
use App\Actions\Fulfilment\RecurringBillTransaction\DeleteRecurringBillTransaction;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\Fulfilment\RecurringBillTransaction\UpdateRecurringBillTransaction;
use App\Actions\Fulfilment\RentalAgreement\UpdateRentalAgreement;
use App\Actions\Fulfilment\Space\StoreSpace;
use App\Actions\Fulfilment\Space\UpdateSpace;
use App\Actions\Fulfilment\StoredItem\AttachStoredItemToReturn;
use App\Actions\Fulfilment\StoredItem\DeleteStoredItem;
use App\Actions\Fulfilment\StoredItem\MoveStoredItem;
use App\Actions\Fulfilment\StoredItem\ResetAuditStoredItemToPallet;
use App\Actions\Fulfilment\StoredItem\SyncStoredItemPallet;
use App\Actions\Fulfilment\StoredItem\SyncStoredItemToPallet;
use App\Actions\Fulfilment\StoredItem\SyncStoredItemToPalletAudit;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\StockFamily\UpdateStockFamily;
use App\Actions\Helpers\AwsEmail\SendIdentityEmailVerification;
use App\Actions\Helpers\Brand\AttachBrandToModel;
use App\Actions\Helpers\Brand\DeleteBrand;
use App\Actions\Helpers\Brand\DetachBrandFromModel;
use App\Actions\Helpers\Brand\StoreBrand;
use App\Actions\Helpers\Brand\UpdateBrand;
use App\Actions\Helpers\GoogleDrive\AuthorizeClientGoogleDrive;
use App\Actions\Helpers\GoogleDrive\CallbackClientGoogleDrive;
use App\Actions\Helpers\Media\AttachAttachmentToModel;
use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use App\Actions\Helpers\Tag\AttachTagsToModel;
use App\Actions\Helpers\Tag\DeleteTag;
use App\Actions\Helpers\Tag\DetachTagFromModel;
use App\Actions\Helpers\Tag\StoreTag;
use App\Actions\Helpers\Tag\UpdateTag;
use App\Actions\HumanResources\ClockingMachine\DeleteClockingMachine;
use App\Actions\HumanResources\ClockingMachine\StoreClockingMachine;
use App\Actions\HumanResources\ClockingMachine\UpdateClockingMachine;
use App\Actions\HumanResources\Employee\DeleteEmployee;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\HumanResources\JobPosition\DeleteJobPosition;
use App\Actions\HumanResources\JobPosition\StoreJobPosition;
use App\Actions\HumanResources\JobPosition\UpdateJobPosition;
use App\Actions\HumanResources\Workplace\DeleteWorkplace;
use App\Actions\HumanResources\Workplace\StoreWorkplace;
use App\Actions\HumanResources\Workplace\UpdateWorkplace;
use App\Actions\Masters\MasterProductCategory\StoreMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\UploadImageMasterProductCategory;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Purge\StorePurge;
use App\Actions\Ordering\Purge\UpdatePurge;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToCancelled;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToNotReceived;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSettled;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Actions\Production\Artefact\ImportArtefact;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrder\UpdateJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\ManufactureTask\UpdateManufactureTask;
use App\Actions\Production\RawMaterial\ImportRawMaterial;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\SupplyChain\SupplierProduct\ImportSupplierProducts;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SysAdmin\Group\UpdateGroupSettings;
use App\Actions\SysAdmin\Guest\DeleteGuest;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Guest\UpdateGuest;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\UI\Notification\MarkAllNotificationAsRead;
use App\Actions\UI\Notification\MarkNotificationAsRead;
use App\Actions\UI\Profile\GetProfileAppLoginQRCode;
use App\Actions\UI\Profile\UpdateProfile;
use App\Actions\Web\Banner\DeleteBanner;
use App\Actions\Web\Banner\PublishBanner;
use App\Actions\Web\Banner\ShutdownBanner;
use App\Actions\Web\Banner\StoreBanner;
use App\Actions\Web\Banner\UpdateBanner;
use App\Actions\Web\Banner\UpdateBannerState;
use App\Actions\Web\Banner\UpdateUnpublishedBannerSnapshot;
use App\Actions\Web\Banner\UploadImagesToBanner;
use App\Actions\Web\ModelHasContent\StoreModelHasContent;
use App\Actions\Web\ModelHasWebBlocks\BulkUpdateModelHasWebBlocks;
use App\Actions\Web\ModelHasWebBlocks\DeleteModelHasWebBlocks;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\ModelHasWebBlocks\UploadImagesToModelHasWebBlocks;
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Redirect\UpdateRedirect;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\ReorderWebBlocks;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Actions\Web\Website\AutosaveWebsiteMarginal;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\PublishWebsiteMarginal;
use App\Actions\Web\Website\PublishWebsiteProductTemplate;
use App\Actions\Web\Website\StoreWebsite;
use App\Actions\Web\Website\UpdateWebsite;
use App\Actions\Web\Website\UploadImagesToWebsite;
use App\Stubs\UIDummies\ImportDummy;
use Illuminate\Support\Facades\Route;

Route::patch('/profile', UpdateProfile::class)->name('profile.update');

Route::get('/profile/app-login-qrcode', GetProfileAppLoginQRCode::class)->name('profile.app-login-qrcode');


Route::patch('notification/{notification}', MarkNotificationAsRead::class)->name('notifications.read');
Route::patch('notifications', MarkAllNotificationAsRead::class)->name('notifications.all.read');




Route::prefix('employee/{employee:id}')->name('employee.')->group(function () {
    Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inEmployee'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inEmployee'])->name('attachment.detach')->withoutScopedBindings();
    Route::patch('', UpdateEmployee::class)->name('update');
    Route::delete('', DeleteEmployee::class)->name('.delete');
});

Route::prefix('workplace/{workplace:id}')->name('workplace.')->group(function () {
    Route::patch('', UpdateWorkplace::class)->name('update');
    Route::delete('', DeleteWorkplace::class)->name('delete');
    Route::post('clocking-machine', StoreClockingMachine::class)->name('clocking_machine.store');
});


Route::prefix('position/{jobPosition:id}')->name('job_position.')->group(function () {
    Route::patch('', UpdateJobPosition::class)->name('update');
    Route::delete('', DeleteJobPosition::class)->name('delete');
});

Route::prefix('clocking-machine/{clockingMachine:id}')->name('clocking_machine..')->group(function () {
    Route::patch('', UpdateClockingMachine::class)->name('update');
    Route::delete('', DeleteClockingMachine::class)->name('delete');
});



Route::patch('fulfilment/{fulfilment:id}', UpdateFulfilment::class)->name('fulfilment.update');
Route::patch('customer/{customer:id}', UpdateCustomer::class)->name('customer.update')->withoutScopedBindings();
Route::patch('customer-balance/{customer:id}', UpdateBalanceCustomer::class)->name('customer_balance.update')->withoutScopedBindings();
Route::patch('customer/delivery-address/{customer:id}', UpdateCustomerDeliveryAddress::class)->name('customer.delivery-address.update')->withoutScopedBindings();

Route::patch('master-product/{masterProductCategory:id}', UpdateMasterProductCategory::class)->name('master_product.update')->withoutScopedBindings();
Route::post('master-product/{masterProductCategory:id}/image', UploadImageMasterProductCategory::class)->name('master_product_image.upload')->withoutScopedBindings();

Route::post('master-product/{masterDepartment:id}/master-sub-departments/store', StoreMasterSubDepartment::class)->name('master_product.master_sub_departments.store')->withoutScopedBindings();

Route::prefix('delivery-note/{deliveryNote:id}')->name('delivery_note.')->group(function () {
    Route::post('shipment', UpdateStockFamily::class)->name('update')->withoutScopedBindings();
});

Route::prefix('stock-family')->name('stock-family.')->group(function () {
    Route::patch('{stockFamily:id}/update', UpdateStockFamily::class)->name('update');
    Route::post('', StoreStockFamily::class)->name('store');
    Route::post('{stockFamily:id}/stock', [StoreStock::class, 'inStockFamily'])->name('stock.store');
});


Route::name('stock.')->prefix('/stock')->group(function () {
    Route::post('/', StoreStock::class)->name('store');
    Route::patch('/{stock:id}', UpdateStock::class)->name('update');
});


Route::prefix('department/{productCategory:id}')->name('department.')->group(function () {
    Route::post('sub-department', StoreSubDepartment::class)->name('sub_department.store');
});



Route::prefix('sub-department/{productCategory:id}')->name('sub-department.')->group(function () {
    Route::patch('', [UpdateProductCategory::class, 'inSubDepartment'])->name('update');
    Route::post('family', [StoreProductCategory::class, 'inSubDepartment'])->name('family.store');
});
Route::prefix('sub-department/{subDepartment}')->name('sub-department.')->group(function () {
    Route::post('families/attach', AttachFamiliesToSubDepartment::class)->name('families.attach');
    Route::delete('family/{family}/detach', DetachFamilyToSubDepartment::class)->name('family.detach');
});

Route::delete('portfolio/{portfolio:id}', DeletePortfolio::class)->name('portfolio.delete')->withoutScopedBindings();
Route::patch('portfolio/{portfolio:id}', UpdatePortfolio::class)->name('portfolio.update')->withoutScopedBindings();

Route::name('org.')->prefix('org/{organisation:id}')->group(function () {

    Route::post("google-drive.authorize", [AuthorizeClientGoogleDrive::class, 'authorize'])->name('google_drive.authorize');
    Route::get("google-drive.callback", CallbackClientGoogleDrive::class)->name('google_drive.callback');
    Route::patch("settings", UpdateOrganisation::class)->name('settings.update');


    Route::post('employee', StoreEmployee::class)->name('employee.store');
    Route::post('position', StoreJobPosition::class)->name('jon_position.store');
    Route::post('working-place', StoreWorkplace::class)->name('workplace.store');
    Route::post('clocking-machine', [StoreClockingMachine::class, 'inOrganisation'])->name('clocking-machine.store');

    Route::post('invoice-category', StoreInvoiceCategory::class)->name('invoice-category.store');


    Route::post('shop', StoreShop::class)->name('shop.store');
    Route::patch('shop/{shop:id}', UpdateShop::class)->name('shop.update')->withoutScopedBindings();
    Route::post('fulfilment', StoreFulfilmentFromUI::class)->name('fulfilment.store');


    Route::prefix('fulfilment/{fulfilment:id}/rentals')->name('fulfilment.rentals.')->group(function () {
        Route::post('/', StoreRental::class)->name('store');
        Route::patch('{rental:id}', [UpdateRental::class, 'inFulfilment'])->name('update')->withoutScopedBindings();
    });

    Route::prefix('fulfilment/{fulfilment:id}/services')->name('fulfilment.services.')->group(function () {
        Route::post('/', [StoreService::class, 'inFulfilment'])->name('store');
    });

    Route::prefix('fulfilment/{fulfilment:id}/goods')->name('fulfilment.goods.')->group(function () {
        Route::post('/', [StoreProduct::class, 'inFulfilment'])->name('store');
    });

    Route::prefix('/shop/{shop:id}/catalogue/collections')->name('catalogue.collections.')->group(function () {
        Route::post('/', StoreCollection::class)->name('store');
        Route::patch('{collection:id}', UpdateCollection::class)->name('update')->withoutScopedBindings();
    });

    Route::prefix('/shop/{shop:id}/catalogue/departments')->name('catalogue.departments.')->group(function () {
        Route::post('/', StoreProductCategory::class)->name('store')->withoutScopedBindings();
        Route::patch('{productCategory:id}', UpdateProductCategory::class)->name('update')->withoutScopedBindings();
        Route::post('family/store/{productCategory:id}', [StoreProductCategory::class, 'inDepartment'])->name('family.store')->withoutScopedBindings();
        Route::post('sub-department/store/{productCategory:id}', [StoreProductCategory::class, 'inDepartment'])->name('sub-department.store')->withoutScopedBindings();

    });



    Route::prefix('/shop/{shop:id}/catalogue/families')->name('catalogue.families.')->group(function () {
        Route::post('{family:id}/product/store', [StoreProduct::class, 'inFamily'])->name('product.store')->withoutScopedBindings();
        Route::patch('{productCategory:id}', UpdateProductCategory::class)->name('update')->withoutScopedBindings();
    });

    Route::post('/shop/{shop:id}/customer', StoreCustomer::class)->name('shop.customer.store');


    Route::post('/shop/{shop:id}/product/', [StoreProduct::class, 'inShop'])->name('show.product.store');
    Route::delete('/shop/{shop:id}/product/{product:id}', [DeleteProduct::class, 'inShop'])->name('shop.product.delete');

    Route::post('product/{product:id}/images', UploadImagesToProduct::class)->name('product.images.store')->withoutScopedBindings();
    Route::post('product/{product:id}/images/attach', AttachImagesToProduct::class)->name('product.images.attach')->withoutScopedBindings();
    Route::delete('product/{product:id}/images/{media:id}/media', DeleteImagesFromProduct::class)->name('product.images.delete')->withoutScopedBindings();

    Route::patch('/payment-account/{paymentAccount:id}', UpdatePaymentAccount::class)->name('payment-account.update')->withoutScopedBindings();
    Route::post('/payment-account', StorePaymentAccount::class)->name('payment-account.store');
    Route::post('/payment-service-provider/{paymentServiceProvider:id}', StoreOrgPaymentServiceProvider::class)->name('payment-service-provider.store')->withoutScopedBindings();

    Route::post('/payment-service-provider/{paymentServiceProvider:id}/account', StoreOrgPaymentServiceProviderAccount::class)->name('payment-service-provider-account.store')->withoutScopedBindings();
});

Route::name('fulfilment-transaction.')->prefix('fulfilment_transaction/{fulfilmentTransaction:id}')->group(function () {
    Route::patch('', UpdateFulfilmentTransaction::class)->name('update');
    Route::delete('', DeleteFulfilmentTransaction::class)->name('delete');
});

Route::name('recurring-bill.')->prefix('recurring-bill/{recurringBill:id}')->group(function () {
    Route::patch('', UpdateRecurringBilling::class)->name('update');
    Route::patch('consolidate', ConsolidateRecurringBill::class)->name('consolidate');
    Route::post('transaction/{historicAsset:id}', StoreRecurringBillTransaction::class)->name('transaction.store')->withoutScopedBindings();
});

Route::patch('recurring-bill-transaction/{recurringBillTransaction:id}', UpdateRecurringBillTransaction::class)->name('recurring_bill_transaction.update');
Route::delete('recurring-bill-transaction/{recurringBillTransaction:id}', DeleteRecurringBillTransaction::class)->name('recurring_bill_transaction.delete');

Route::name('product.')->prefix('product')->group(function () {
    Route::post('/product/', StoreProduct::class)->name('store');
    Route::patch('/{product:id}/update', UpdateProduct::class)->name('update');
    Route::delete('/{product:id}/delete', DeleteProduct::class)->name('delete');
    Route::patch('/{product:id}/move-family', MoveFamilyProductToOtherFamily::class)->name('move_family');
    Route::post('/{product:id}/content', [StoreModelHasContent::class, 'inProduct'])->name('content.store');
});




Route::name('pallet-delivery.')->prefix('pallet-delivery/{palletDelivery:id}')->group(function () {
    Route::patch('/', UpdatePalletDelivery::class)->name('update');
    Route::post('submit-and-confirm', SubmitAndConfirmPalletDelivery::class)->name('submit_and_confirm');
    Route::post('cancel', CancelPalletDelivery::class)->name('cancel');

    Route::post('confirm', ConfirmPalletDelivery::class)->name('confirm');
    Route::post('received', ReceivePalletDelivery::class)->name('received');
    Route::post('booking', StartBookingPalletDelivery::class)->name('booking');
    Route::post('booked-in', SetPalletDeliveryAsBookedIn::class)->name('booked-in');
    Route::delete('booked-in-delete', DeleteBookedInPalletDelivery::class)->name('booked-in-delete');

    Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inPalletDelivery'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inPalletDelivery'])->name('attachment.detach')->withoutScopedBindings();

    Route::post('pallet-upload', ImportPalletsInPalletDelivery::class, )->name('pallet.upload');
    Route::post('pallet-upload-with-stored-items', ImportPalletsInPalletDeliveryWithStoredItems::class, )->name('pallet.upload.with-stored-items');
    Route::post('pallet', StorePalletCreatedInPalletDelivery::class)->name('pallet.store');
    Route::post('multiple-pallet', StoreMultiplePalletsFromDelivery::class)->name('multiple-pallets.store');

    Route::post('transaction', [StoreFulfilmentTransaction::class,'inPalletDelivery'])->name('transaction.store');
    Route::patch('delete', DeletePalletDelivery::class)->name('delete');

    Route::get('pdf', PdfPalletDelivery::class)->name('pdf');
});



Route::name('pallet-return.')->prefix('pallet-return/{palletReturn:id}')->group(function () {
    Route::post('pick-all-with-stored-items', PickedPalletReturnWithStoredItems::class)->name('pick_all_with_stored_items');
    Route::post('address', AddAddressToPalletReturn::class)->name('address.store');
    Route::patch('address/switch', SwitchPalletReturnDeliveryAddress::class)->name('address.switch');
    Route::patch('address/update', UpdatePalletReturnDeliveryAddress::class)->name('address.update');
    Route::delete('address/delete', DeletePalletReturnAddress::class)->name('address.delete');

    Route::post('dispatch', DispatchPalletReturn::class)->name('dispatch');
    Route::patch('delete', DeletePalletReturn::class)->name('delete');
    Route::delete('dispatched-delete', DeleteDispatchedPalletReturn::class)->name('dispatched-delete');

    Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inPalletReturn'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inPalletReturn'])->name('attachment.detach')->withoutScopedBindings();

    Route::post('transaction', [StoreFulfilmentTransaction::class,'inPalletReturn'])->name('transaction.store');
    Route::post('pallet', AttachPalletsToReturn::class)->name('pallet.store');
    Route::post('attach-pallet/{pallet:id}', AttachPalletToReturn::class)->name('pallet.attach')->withoutScopedBindings();
    Route::delete('detach-pallet/{pallet:id}', DetachPalletFromReturn::class)->name('pallet.detach');
    Route::post('pallet-stored-item/{palletStoredItem:id}', AttachStoredItemToReturn::class)->name('stored_item.store')->withoutScopedBindings();
    Route::post('pallet-stored-item/pick/{palletStoredItem:id}', PickNewPalletReturnItem::class)->name('pallet_return_item.new_pick')->withoutScopedBindings();
    Route::post('pallet-return-item-upload', [ImportPalletReturnItem::class, 'fromGrp'])->name('pallet-return-item.upload');

    Route::post('revert-to-in-process', RevertPalletReturnToInProcess::class)->name('revert-to-in-process');
    // This is wrong ImportPalletsInPalletDelivery is used when creating a pallet delivery
    Route::post('pallet-upload', ImportPalletsInPalletDelivery::class)->name('pallet.upload');
    Route::patch('/', UpdatePalletReturn::class)->name('update');
    Route::get('pdf', PdfPalletReturn::class)->name('pdf');


    Route::post('/shipment-from-fulfilment', CreateShipmentInPalletReturnInFulfilment::class)->name('shipment_from_fulfilment.store');
    Route::post('/shipment-from-warehouse', CreateShipmentInPalletReturnInWarehouse::class)->name('shipment_from_warehouse.store');

    Route::delete('/detach-shipment/{shipment}', DetachShipmentFromPalletReturn::class)->name('shipment.detach');


});

Route::name('pallet.')->prefix('pallet/{pallet:id}')->group(function () {
    Route::delete('', DeletePallet::class)->name('delete');
    Route::patch('', UpdatePallet::class)->name('update');
    Route::patch('rental', SetPalletRental::class)->name('rental.update');

    Route::patch('pallet-return-item', SyncPalletReturnItem::class)->name('pallet-return-item.sync');

    Route::delete('stored/delete', DeleteStoredPallet::class)->name('stored.delete');
    Route::post('stored-items', SyncStoredItemToPallet::class)->name('stored-items.update');
    Route::post('stored-items/audit/{storedItemAudit:id}', SyncStoredItemToPalletAudit::class)->name('stored-items.audit')->withoutScopedBindings();
    Route::delete('stored-items/reset', ResetAuditStoredItemToPallet::class)->name('stored-items.audit.reset');
    Route::patch('book-in', BookInPallet::class)->name('book_in');
    Route::patch('not-received', SetPalletAsNotReceived::class)->name('not-received');
    Route::patch('undo-not-received', UndoNotReceivedPallet::class)->name('undo-not-received');
    Route::patch('undo-booked-in', UndoBookedInPallet::class)->name('undo_book_in');
    Route::patch('return', ReturnPallet::class)->name('return');
    Route::patch('damaged', SetPalletAsDamaged::class)->name('damaged');
    Route::patch('lost', SetPalletAsLost::class)->name('lost');
    Route::patch('location', UpdatePalletLocation::class)->name('location.update');

});

Route::name('pallet-return-item.')->prefix('pallet-return-item/{palletReturnItem}')->group(function () {
    Route::patch('', PickWholePalletInPalletReturn::class)->name('set_as_picked');
    Route::patch('pick', PickPalletReturnItemInPalletReturnWithStoredItem::class)->name('pick');
    Route::patch('undo-picking-stored-item', UndoStoredItemPick::class)->name('undo-picking-stored-item');
    Route::patch('not-picked', NotPickedPalletFromReturn::class)->name('not-picked');
    Route::patch('undo-picking', UndoPickingPalletFromReturn::class)->name('undo-picking');
    Route::patch('undo', UndoPalletReturnItem::class)->name('undo-confirmed');
});

Route::patch('{storedItem:id}/stored-items/pallets', SyncStoredItemPallet::class)->name('stored-items.pallets.update');
Route::patch('{storedItem:id}/stored-items', MoveStoredItem::class)->name('stored-items.move');
Route::delete('{storedItem:id}/stored-items', DeleteStoredItem::class)->name('stored-items.delete');




Route::prefix('rental-agreement/{rentalAgreement:id}')->group(function () {
    Route::patch('', UpdateRentalAgreement::class)->name('rental-agreement.update');
});

Route::name('banner.')->prefix('banner/{banner:id}')->group(function () {
    Route::patch('publish', PublishBanner::class)->name('publish');
    Route::patch('layout', UpdateUnpublishedBannerSnapshot::class)->name('layout.update');
    Route::post('images', UploadImagesToBanner::class)->name('images.store');

});

Route::name('shop.')->prefix('shop/{shop:id}')->group(function () {
    Route::post('prospect/upload', [ImportShopProspects::class, 'inShop'])->name('prospects.upload');
    Route::post('website', StoreWebsite::class)->name('website.store');

    Route::name('webpage.')->prefix('webpage/{webpage:id}')->group(function () {
        Route::patch('', [UpdateWebpage::class, 'inShop'])->name('update')->withoutScopedBindings();
        Route::delete('', [DeleteWebpage::class, 'inShop'])->name('delete')->withoutScopedBindings();
    });

    Route::name('sender_email.')->prefix('sender-email')->group(function () {
        Route::post('verify', [SendIdentityEmailVerification::class, 'inShop'])->name('verify');
    });

    Route::prefix('website/{website:id}/banner')->name('website.banner.')->group(function () {


        Route::prefix('{banner:id}')->group(function () {

            Route::patch('', UpdateBanner::class)->name('update')->withoutScopedBindings();

            Route::patch('state/{state}', UpdateBannerState::class)->name('update-state')->withoutScopedBindings();
            Route::delete('', DeleteBanner::class)->name('delete')->withoutScopedBindings();
            Route::patch('shutdown', ShutdownBanner::class)->name('shutdown')->withoutScopedBindings();
            Route::patch('switch-on', PublishBanner::class)->name('switch-on')->withoutScopedBindings();
        });
    });

    Route::name('outboxes.')->prefix('outboxes/{outbox:id}')->group(function () {
        Route::patch('toggle', ToggleOutbox::class)->name('toggle')->withoutScopedBindings();
        Route::post('publish', PublishOutbox::class)->name('publish')->withoutScopedBindings();
        Route::patch('workshop', UpdateWorkshopOutbox::class)->name('workshop.update')->withoutScopedBindings();
        Route::post('send/test', SendMailshotTest::class)->name('send.test')->withoutScopedBindings();
    });
});

Route::name('fulfilment.')->prefix('fulfilment/{fulfilment:id}')->group(function () {
    Route::post('website', [StoreWebsite::class, 'inFulfilment'])->name('website.store');
    Route::post('fulfilment-customer', StoreFulfilmentCustomer::class)->name('fulfilment_customer.store');
    Route::patch('website/{website:id}', [UpdateWebsite::class, 'inFulfilment'])->name('website.update')->withoutScopedBindings();

    Route::post('website/{website:id}/webpage', [StoreWebpage::class, 'inFulfilment'])->name('webpage.store')->withoutScopedBindings();

    Route::name('outboxes.')->prefix('outboxes/{outbox:id}')->group(function () {
        Route::patch('/', UpdateOutbox::class)->name('update')->withoutScopedBindings();
        Route::patch('toggle', ToggleOutbox::class)->name('toggle')->withoutScopedBindings();
        Route::post('publish', PublishOutbox::class)->name('publish')->withoutScopedBindings();
        Route::patch('workshop', UpdateWorkshopOutbox::class)->name('workshop.update')->withoutScopedBindings();
        Route::post('send/test', SendMailshotTest::class)->name('send.test')->withoutScopedBindings();
    });

});

Route::name('fulfilment.')->prefix('fulfilment/{fulfilment}')->group(function () {
    Route::name('outboxes.')->prefix('outboxes/{outbox}')->group(function () {
        Route::post('subscriber', [StoreManyOutboxHasSubscriber::class, 'inFulfilment'])->name('subscriber.store')->withoutScopedBindings();
        Route::delete('subscriber/{outBoxHasSubscriber:id}', [DeleteOutboxHasSubscriber::class, 'inFulfilment'])->name('subscriber.delete')->withoutScopedBindings();
    });
});

Route::post('fulfilment-customer-note/{fulfilmentCustomer}', StoreFulfilmentCustomerNote::class)->name('fulfilment_customer_note.store');
Route::patch('fulfilment-customer/{fulfilmentCustomer:id}', UpdateFulfilmentCustomer::class)->name('fulfilment_customer.update');
Route::patch('customer/{customer:id}/approve', ApproveCustomer::class)->name('customer.approve');
Route::patch('customer/{customer:id}/reject', RejectCustomer::class)->name('customer.reject');

Route::prefix('fulfilment-customer-space/{fulfilmentCustomer:id}')->as('fulfilment_customer_space.')->group(function () {
    Route::post('', StoreSpace::class)->name('store');
    Route::patch('spaces/{space:id}', UpdateSpace::class)->name('update')->withoutScopedBindings();
});

Route::post('group/{group:id}/organisation', StoreOrganisation::class)->name('organisation.store');


Route::name('website.')->prefix('website/{website:id}')->group(function () {



    Route::post('publish/header', [PublishWebsiteMarginal::class, 'header'])->name('publish.header');
    Route::post('publish/footer', [PublishWebsiteMarginal::class, 'footer'])->name('publish.footer');

    Route::patch('autosave/header', [AutosaveWebsiteMarginal::class, 'header'])->name('autosave.header');
    Route::patch('autosave/footer', [AutosaveWebsiteMarginal::class, 'footer'])->name('autosave.footer');
    Route::patch('autosave/menu', [AutosaveWebsiteMarginal::class, 'menu'])->name('autosave.menu');
    Route::patch('autosave/department', [AutosaveWebsiteMarginal::class, 'department'])->name('autosave.department');
    Route::patch('autosave/sub_department', [AutosaveWebsiteMarginal::class, 'subDepartment'])->name('autosave.sub_department');
    Route::patch('autosave/family', [AutosaveWebsiteMarginal::class, 'family'])->name('autosave.family');
    Route::patch('autosave/product', [AutosaveWebsiteMarginal::class, 'product'])->name('autosave.product');

    Route::post('publish/menu', [PublishWebsiteMarginal::class, 'menu'])->name('publish.menu');
    Route::post('publish/department', [PublishWebsiteMarginal::class, 'department'])->name('publish.department');
    Route::post('publish/sub_department', [PublishWebsiteMarginal::class, 'subDepartment'])->name('publish.sub_department');
    Route::post('publish/family', [PublishWebsiteMarginal::class, 'family'])->name('publish.family');
    Route::post('publish/product', [PublishWebsiteMarginal::class, 'product'])->name('publish.product');

    Route::patch('/settings/update', PublishWebsiteProductTemplate::class)->name('settings.update');

    Route::patch('theme', [PublishWebsiteMarginal::class, 'theme'])->name('update.theme');


    Route::patch('', UpdateWebsite::class)->name('update');
    Route::post('launch', LaunchWebsite::class)->name('launch');
    Route::post('images/header', [UploadImagesToWebsite::class, 'header'])->name('header.images.store');
    Route::post('images/footer', [UploadImagesToWebsite::class, 'footer'])->name('footer.images.store');
    Route::post('images/favicon', [UploadImagesToWebsite::class, 'favicon'])->name('favicon.images.store');


    Route::post('/banner', StoreBanner::class)->name('banner.store');
});

Route::name('webpage.')->prefix('webpage/{webpage:id}')->group(function () {
    Route::post('publish', PublishWebpage::class)->name('publish');
    Route::post('web-block', StoreModelHasWebBlock::class)->name('web_block.store');
    Route::post('reorder-web-blocks', ReorderWebBlocks::class)->name('reorder_web_blocks');
    Route::post('redirect', [StoreRedirect::class, 'inWebpage'])->name('redirect.store');
});

Route::name('redirect.')->prefix('redirect/{redirect:id}')->group(function () {
    Route::patch('', UpdateRedirect::class)->name('update');
});

Route::name('model_has_web_block.')->prefix('model-has-web-block')->group(function () {
    Route::patch('bulk', BulkUpdateModelHasWebBlocks::class)->name('bulk.update');
    Route::prefix('{modelHasWebBlocks:id}')->group(function () {
        Route::patch('', UpdateModelHasWebBlocks::class)->name('update');
        Route::delete('', DeleteModelHasWebBlocks::class)->name('delete');
        Route::post('images', UploadImagesToModelHasWebBlocks::class)->name('images.store');
    });
});

Route::patch('/web-user/{webUser:id}', UpdateWebUser::class)->name('web-user.update');

Route::name('customer.')->prefix('customer/{customer:id}')->group(function () {
    Route::post('', [StoreWebUser::class, 'inCustomer'])->name('web-user.store');
    Route::post('delivery-address', AddDeliveryAddressToCustomer::class)->name('address.store');
    Route::patch('address/update', UpdateCustomerAddress::class)->name('address.update');
    Route::delete('address/{address:id}/delete', [DeleteCustomerDeliveryAddress::class, 'inCustomer'])->name('delivery-address.delete')->withoutScopedBindings();
    Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inCustomer'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inCustomer'])->name('attachment.detach')->withoutScopedBindings();
    Route::post('order', [StoreOrder::class, 'inCustomer'])->name('order.store');
});

Route::name('customer_sales_channel.')->prefix('customer-sales-channel/{customerSalesChannel:id}')->group(function () {
    Route::post('portfolio-multiple-manual', StoreMultipleManualPortfolios::class)->name('portfolio.store_multiple_manual');
    Route::post('client', StoreCustomerClient::class)->name('client.store');
});

Route::post('{shop:id}/purge', StorePurge::class)->name('purge.store');
Route::patch('purge/{purge:id}/update', UpdatePurge::class)->name('purge.update');

Route::name('customer_client.')->prefix('customer-client/{customerClient:id}')->group(function () {
    Route::patch('/', UpdateCustomerClient::class)->name('update');
    Route::post('order', [StoreOrder::class, 'inCustomerClient'])->name('order.store');
});

Route::post('/supplier', StoreSupplier::class)->name('supplier.store');
Route::patch('/supplier/{supplier:id}', UpdateSupplier::class)->name('supplier.update');

Route::name('production.')->prefix('production/{production:id}')->group(function () {
    Route::post('job-order', StoreJobOrder::class)->name('job-order.store');
    Route::post('artefact-upload', ImportDummy::class)->name('artefacts.upload');
    Route::post('raw-materials-upload', ImportRawMaterial::class)->name('raw_materials.upload');
    Route::post('manufacture-tasks-upload', ImportDummy::class)->name('manufacture_tasks.upload');
    Route::post('raw-materials', StoreRawMaterial::class)->name('raw-materials.store');
    Route::patch('raw-materials/{rawMaterial:id}', UpdateRawMaterial::class)->name('raw-materials.update');
    Route::post('manufacture-tasks', StoreManufactureTask::class)->name('manufacture_tasks.store');
    Route::patch('manufacture-tasks/{manufactureTask:id}', UpdateManufactureTask::class)->name('manufacture_tasks.update');
    Route::post('artefacts', StoreArtefact::class)->name('artefacts.store');
    Route::patch('artefacts/{artefact:id}', UpdateArtefact::class)->name('artefacts.update');
    Route::post('artefact-upload', ImportArtefact::class)->name('artefact.import');
});

Route::patch('/job-order/{jobOrder:id}', UpdateJobOrder::class)->name('job-order.update');


Route::patch('stored-items/{storedItem:id}', UpdateStoredItem::class)->name('stored-items.update');

Route::patch('/group-settings', UpdateGroupSettings::class)->name('group-settings.update');

Route::patch('/{mailshot:id}/mailshot', UpdateMailshot::class)->name('shop.mailshot.update');

Route::name('email-templates.')->prefix('email-templates')->group(function () {
    Route::patch('{emailTemplate:id}/update', UpdateEmailTemplate::class)->name('content.update');
    Route::post('{emailTemplate:id}/images', UploadImagesToEmailTemplate::class)->name('images.store');
    Route::post('{emailTemplate:id}/publish', PublishEmail::class)->name('content.publish');
});

Route::patch('/guest/{guest:id}', UpdateGuest::class)->name('guest.update');
Route::post('/guest/', StoreGuest::class)->name('guest.store');
Route::delete('/guest/{guest:id}', DeleteGuest::class)->name('guest.delete');

Route::name('collection.')->prefix('collection/{collection:id}')->group(function () {
    Route::post('attach-models', AttachCollectionToModels::class)->name('attach-models');
    Route::delete('detach-models', DetachModelFromCollection::class)->name('detach-models');
});

Route::name('supplier.')->prefix('supplier/{supplier:id}')->group(function () {
    Route::post('supplier-product', StoreSupplierProduct::class)->name('supplier-product.store');
    Route::post('supplier-product/import', ImportSupplierProducts::class)->name('supplier-product.import');
    Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inSupplier'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inSupplier'])->name('attachment.detach')->withoutScopedBindings();
});

Route::name('purchase-order.')->prefix('purchase-order/{purchaseOrder:id}')->group(function () {
    Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inPurchaseOrder'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inPurchaseOrder'])->name('attachment.detach')->withoutScopedBindings();
});

Route::name('stock-delivery.')->prefix('stock-delivery/{stockDelivery:id}')->group(function () {
    Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inStockDelivery'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inStockDelivery'])->name('attachment.detach')->withoutScopedBindings();
});

Route::name('org-supplier.')->prefix('org-supplier/{orgSupplier:id}')->group(function () {
    Route::post('purchase-order/store', [StorePurchaseOrder::class, 'inOrgSupplier'])->name('purchase-order.store');
});
Route::name('org-agent.')->prefix('org-agent/{orgAgent:id}')->group(function () {
    Route::post('purchase-order/store', [StorePurchaseOrder::class, 'inOrgAgent'])->name('purchase-order.store');
});
Route::name('org-partner.')->prefix('org-partner/{orgPartner:id}')->group(function () {
    Route::post('purchase-order/store', [StorePurchaseOrder::class, 'inOrgPartner'])->name('purchase-order.store');
});

Route::name('purchase-order.')->prefix('purchase-order/{purchaseOrder:id}')->group(function () {
    Route::patch('update', UpdatePurchaseOrder::class)->name('update');
    Route::patch('submit', UpdatePurchaseOrderStateToSubmitted::class)->name('submit');
    Route::patch('confirm', UpdatePurchaseOrderStateToConfirmed::class)->name('confirm');
    Route::patch('settle', UpdatePurchaseOrderStateToSettled::class)->name('settle');
    Route::patch('cancel', UpdatePurchaseOrderStateToCancelled::class)->name('cancel');
    Route::patch('not-received', UpdatePurchaseOrderStateToNotReceived::class)->name('not-received');
    Route::post('transactions/{historicSupplierProduct:id}/{orgStock:id}/store', StorePurchaseOrderTransaction::class)->name('transaction.store')->withoutScopedBindings();
    Route::patch('transactions/{purchaseOrderTransaction:id}/update', UpdatePurchaseOrderTransaction::class)->name('transaction.update')->withoutScopedBindings();
    Route::delete('transactions/{purchaseOrderTransaction:id}/delete', DeletePurchaseOrderTransaction::class)->name('transaction.delete')->withoutScopedBindings();
});

Route::name('email.')->prefix('email/')->group(function () {
    Route::name('snapshot.')->prefix('snapshot/{snapshot:id}')->group(function () {
        Route::patch('/update', UpdateEmailUnpublishedSnapshot::class)->name('update');
    });
});

Route::name('rentals.')->prefix('rentals/')->group(function () {
    Route::patch('{rental:id}/update', UpdateRental::class)->name('update');
});


Route::name('invoice-category.')->prefix('invoice-category/')->group(function () {
    Route::patch('{invoiceCategory:id}/update', UpdateInvoiceCategory::class)->name('update');
});


Route::post('/outbox/{outbox:id}/mailshot', StoreMailshot::class)->name('outbox.mailshot.store');

Route::name('product_category.')->prefix('product_category/{productCategory:id}')->group(function () {
    Route::post('collection', [StoreCollection::class, 'inProductCategory'])->name('collection.store');
    Route::post('content', [StoreModelHasContent::class, 'inProductCategory'])->name('content.store');
});


Route::name('trade-unit.')->prefix('trade-unit/{tradeUnit}')->group(function () {
    Route::post('tags/store', [StoreTag::class, 'inTradeUnit'])->name('tags.store');
    Route::patch('tags/{tag:id}/update', [UpdateTag::class, 'inTradeUnit'])->name('tags.update');
    Route::delete('tags/{tag:id}/delete', [DeleteTag::class, 'inTradeUnit'])->name('tags.delete');
    Route::post('tags/attach', [AttachTagsToModel::class, 'inTradeUnit'])->name('tags.attach');
    Route::delete('tags/{tag:id}/detach', [DetachTagFromModel::class, 'inTradeUnit'])->name('tags.detach');

    Route::post('brands/store', [StoreBrand::class, 'inTradeUnit'])->name('brands.store');
    Route::delete('brands/{brand:id}/delete', [DeleteBrand::class, 'inTradeUnit'])->name('brands.delete')->withoutScopedBindings();
    Route::patch('brands/{brand:id}/update', [UpdateBrand::class, 'inTradeUnit'])->name('brands.update')->withoutScopedBindings();
    Route::post('brands/attach', [AttachBrandToModel::class, 'inTradeUnit'])->name('brands.attach');
    Route::delete('brands/{brand:id}/detach', [DetachBrandFromModel::class, 'inTradeUnit'])->name('brands.detach');
});

require __DIR__."/models/inventory/warehouse.php";
require __DIR__."/models/inventory/location_org_stock.php";
require __DIR__."/models/inventory/warehouse_area.php";
require __DIR__."/models/inventory/location.php";
require __DIR__."/models/ordering/order.php";
require __DIR__."/models/stock/stock.php";
require __DIR__."/models/accounting/invoice.php";
require __DIR__."/models/accounting/refund.php";
require __DIR__."/models/billables/billables.php";
require __DIR__."/models/billables/services.php";

require __DIR__."/models/hr/hr.php";
require __DIR__."/models/website/webpages.php";
require __DIR__."/models/supply_chain/agent.php";
require __DIR__."/models/sys_admin/user.php";
require __DIR__."/models/fulfilment/fulfilment_customer.php";
require __DIR__."/models/fulfilment/stored_item_audit.php";
require __DIR__."/models/fulfilment/stored_item_audit_delta.php";
