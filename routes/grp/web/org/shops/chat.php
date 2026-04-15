<?php

use App\Actions\CRM\ChatSession\UI\ShowShopChatDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowShopChatDashboard::class)->name('dashboard');
