<?php

use App\Actions\Chat\ChatSession\UI\IndexChatSessions;
use App\Actions\Chat\ChatSession\UI\ShowChatSession;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexChatSessions::class)->name('index');
Route::get('{chatSession}', ShowChatSession::class)->name('show');
