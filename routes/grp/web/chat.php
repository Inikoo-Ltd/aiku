<?php

use App\Actions\CRM\ChatSession\UI\ShowGroupChatDashboard;
use App\Actions\CRM\Agent\UI\ShowGroupAgents;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowGroupChatDashboard::class)->name('dashboard');
Route::get('/agents', ShowGroupAgents::class)->name('agents.show');
