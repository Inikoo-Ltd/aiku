<?php

use App\Actions\CRM\ChatSession\UI\IndexChatSessions;
use App\Actions\CRM\ChatSession\UI\ShowChatSession;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexChatSessions::class)->name('index');
Route::get('{chatSession}', ShowChatSession::class)->name('show');
