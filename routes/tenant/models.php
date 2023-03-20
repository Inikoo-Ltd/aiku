<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 10:25:11 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */


use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Actions\Inventory\Stock\UpdateStock;
use App\Actions\Inventory\StockFamily\UpdateStockFamily;
use App\Actions\Inventory\Warehouse\UpdateWarehouse;
use App\Actions\Inventory\WarehouseArea\UpdateWarehouseArea;
use App\Actions\Mailroom\Outbox\UpdateOutbox;
use App\Actions\Marketing\Department\UpdateDepartment;
use App\Actions\Marketing\Family\UpdateFamily;
use App\Actions\Marketing\Product\UpdateProduct;
use App\Actions\Procurement\Agent\UpdateAgent;
use App\Actions\Procurement\Supplier\UpdateSupplier;
use App\Actions\Sales\Customer\UpdateCustomer;
use App\Actions\SysAdmin\User\UpdateUser;
use Illuminate\Support\Facades\Route;

Route::patch('/customer/{customer}', UpdateCustomer::class)->name('customer.update');

Route::patch('/product/{product}', UpdateProduct::class)->name('product.update');

Route::patch('/family/{family}', UpdateFamily::class)->name('family.update');

Route::patch('/department/{department}', UpdateDepartment::class)->name('department.update');

Route::patch('/employee/{employee}', UpdateEmployee::class)->name('employee.update');

Route::patch('/warehouse/{warehouse}', UpdateWarehouse::class)->name('warehouse.update');

Route::patch('/areas/{warehouseArea}', UpdateWarehouseArea::class)->name('warehouse_area.update');

Route::patch('/location/{location}', UpdateLocation::class)->name('location.update');

Route::patch('/stock/{stock}', UpdateStock::class)->name('stock.update');

Route::patch('/stock-family/{stockFamily:slug}', UpdateStockFamily::class)->name('stock-family.update');

Route::patch('/agent/{agent}', UpdateAgent::class)->name('agent.update');

Route::patch('/supplier/{supplier}', UpdateSupplier::class)->name('supplier.update');

Route::patch('/payment/{payment}', UpdatePayment::class)->name('payment.update');

Route::patch('/user/{user}', UpdateUser::class)->name('user.update');

Route::patch('/outbox/{outbox}', UpdateOutbox::class)->name('outbox.update');
