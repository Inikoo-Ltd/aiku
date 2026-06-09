<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jun 2026 19:25:55 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\ChatSession\UI\ShowGroupChatDashboard;
use App\Actions\CRM\Agent\UI\ShowGroupAgents;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowGroupChatDashboard::class)->name('dashboard');
Route::get('/agents', ShowGroupAgents::class)->name('agents.show');
