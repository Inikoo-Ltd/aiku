<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jun 2026 19:25:55 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\Agent\UI\ShowGroupAgents;
use App\Actions\Chat\ChatSession\UI\RedirectToOrgChatInbox;
use App\Actions\Chat\ChatSession\UI\ShowGroupChatDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowGroupChatDashboard::class)->name('dashboard');
Route::get('/agents', ShowGroupAgents::class)->name('agents.show');
Route::get('/inbox', RedirectToOrgChatInbox::class)->name('inbox');
