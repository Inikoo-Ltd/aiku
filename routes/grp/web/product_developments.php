<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-17h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use App\Actions\Goods\ProductDevelopment\Json\CheckProductDevelopmentSkuCode;
use App\Actions\Goods\ProductDevelopment\Json\SearchProductDevelopmentOptions;
use App\Actions\Goods\ProductDevelopment\Design\DownloadProductDevelopmentArtwork;
use App\Actions\Goods\ProductDevelopment\Design\DownloadProductDevelopmentDesignSection;
use App\Actions\Goods\ProductDevelopment\UI\ShowProductDevelopmentArtwork;
use App\Actions\Goods\ProductDevelopment\Json\CheckProductDevelopmentMemberAccess;
use App\Actions\Goods\ProductDevelopment\Json\GetProductDevelopmentWaitingBadge;
use App\Actions\Goods\ProductDevelopment\Json\GetProductDevelopmentArtworkComments;
use App\Actions\Goods\ProductDevelopment\Json\GetProductDevelopmentItemLabelContent;
use App\Actions\Goods\ProductDevelopment\Json\GetProductDevelopmentSampleComments;
use App\Actions\Goods\ProductDevelopment\Json\TranslateProductDevelopmentLabel;
use App\Actions\Goods\ProductDevelopment\Json\SearchProductDevelopmentPurchaseOrders;
use App\Actions\Goods\ProductDevelopment\Json\SearchProductDevelopmentSupplierProducts;
use App\Actions\Goods\ProductDevelopment\UI\CreateProductDevelopment;
use App\Actions\Goods\ProductDevelopment\UI\IndexProductDevelopments;
use App\Actions\Goods\ProductDevelopment\UI\ShowProductDevelopment;
use App\Actions\Goods\ProductDevelopment\UI\ShowProductDevelopmentDesign;
use App\Actions\Goods\ProductDevelopment\UI\ShowProductDevelopmentsWaitingOnMe;
use App\Actions\Goods\ProductDevelopment\UI\ShowProductDevelopmentsBoard;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexProductDevelopments::class)->name('index');
Route::get('/board', ShowProductDevelopmentsBoard::class)->name('board');
Route::get('/create', CreateProductDevelopment::class)->name('create');
Route::get('/waiting-on-me', ShowProductDevelopmentsWaitingOnMe::class)->name('waiting_on_me');
Route::get('/json/member-access', CheckProductDevelopmentMemberAccess::class)->name('json.member_access');
Route::get('/json/waiting-badge', GetProductDevelopmentWaitingBadge::class)->name('json.waiting_badge');
Route::get('/json/sku-code', CheckProductDevelopmentSkuCode::class)->name('json.sku_code');
Route::get('/json/{scope}', SearchProductDevelopmentOptions::class)->name('json.search')->whereIn('scope', SearchProductDevelopmentOptions::SCOPES);
Route::get('/{productDevelopment:slug}/json/purchase-orders', SearchProductDevelopmentPurchaseOrders::class)->name('json.purchase_orders');
Route::get('/{productDevelopment:slug}/json/label-content/{item:id}', GetProductDevelopmentItemLabelContent::class)->name('json.label_content')->withoutScopedBindings();
Route::get('/{productDevelopment:slug}/json/label-translate/{item:id}', TranslateProductDevelopmentLabel::class)->name('json.label_translate')->withoutScopedBindings();
Route::get('/{productDevelopment:slug}/json/artwork/{artwork:id}/comments', GetProductDevelopmentArtworkComments::class)->name('json.artwork_comments')->withoutScopedBindings();
Route::get('/{productDevelopment:slug}/json/sample/{sample:id}/comments', GetProductDevelopmentSampleComments::class)->name('json.sample_comments')->withoutScopedBindings();
Route::get('/{productDevelopment:slug}/json/supplier-products', SearchProductDevelopmentSupplierProducts::class)->name('json.supplier_products');
Route::get('/{productDevelopment:slug}/artwork/{artwork:id}/pdf', DownloadProductDevelopmentArtwork::class)->name('artwork.pdf')->withoutScopedBindings();
Route::get('/{productDevelopment:slug}/artwork/{artwork:id}', ShowProductDevelopmentArtwork::class)->name('artwork')->withoutScopedBindings();
Route::get('/{productDevelopment:slug}/design-section/{section:id}/zip', DownloadProductDevelopmentDesignSection::class)->name('design_section.zip')->withoutScopedBindings();
Route::get('/{productDevelopment:slug}/design/{round?}', ShowProductDevelopmentDesign::class)->name('design')->whereIn('round', ['concept']);
Route::get('/{productDevelopment:slug}', ShowProductDevelopment::class)->name('show');
