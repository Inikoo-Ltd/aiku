<?php


use Illuminate\Support\Facades\Route;
use App\Actions\CRM\Agent\UI\ShowAgent;

Route::get('/', ShowAgent::class)->name('show');
// Route::get('/create', CreateTag::class)->name('create');
// Route::get('/{tag}/edit', EditTag::class)->name('edit');
// Route::post('/store', StoreTag::class)->name('store');
// Route::patch('/update/{tag:id}', UpdateTag::class)->name('update')->withoutScopedBindings();
// Route::delete('/delete/{tag:id}', DeleteTag::class)->name('delete')->withoutScopedBindings();
