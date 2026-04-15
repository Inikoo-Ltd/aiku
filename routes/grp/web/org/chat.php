<?php

use App\Actions\CRM\ChatSession\UI\ShowChatDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowChatDashboard::class)->name('dashboard');
