<?php

use App\Actions\CRM\ChatSession\UI\ShowChatDashboard;
use Illuminate\Support\Facades\Route;

Route::name('chat.')->prefix('chat')->group(function () {
    Route::get('/dashboard', ShowChatDashboard::class)->name('dashboard');
});
