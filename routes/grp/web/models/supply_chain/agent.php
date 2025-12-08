<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Nov 2024 14:30:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\SupplyChain\Agent\DeleteAgent;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Agent\UpdateAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use Illuminate\Support\Facades\Route;

Route::post('/agent/', StoreAgent::class)->name('agent.store');
Route::patch('/agent/{agent:id}', UpdateAgent::class)->name('agent.update');
Route::delete('/agent/{agent:id}', DeleteAgent::class)->name('agent.delete');
Route::post('/agent/{agent:id}/supplier', [StoreSupplier::class, 'inAgent'])->name('agent.supplier.store');
