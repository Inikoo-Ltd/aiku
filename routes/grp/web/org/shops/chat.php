<?php

use App\Actions\Chat\ChatSession\UI\ShowShopChatDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowShopChatDashboard::class)->name('dashboard');
