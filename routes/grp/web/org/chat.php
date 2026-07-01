<?php

use App\Actions\Chat\ChatSession\ExportChatConversations;
use App\Actions\Chat\ChatSession\GetActiveChatSessions;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\GetChatVisitorsByCountry;
use App\Actions\Chat\ChatSession\UI\ShowChatConversations;
use App\Actions\Chat\ChatSession\UI\ShowChatDashboard;
use App\Actions\Chat\ChatSession\UI\ShowOrgChatConversation;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowChatDashboard::class)->name('dashboard');
Route::get('/visitors-by-country', GetChatVisitorsByCountry::class)->name('visitors-by-country');
Route::get('/active-sessions', GetActiveChatSessions::class)->name('active-sessions');
Route::get('/dashboard-visitors', GetChatDashboardVisitors::class)->name('dashboard-visitors');
Route::get('/conversations', ShowChatConversations::class)->name('conversations.show');
Route::get('/conversations/export', ExportChatConversations::class)->name('conversations.export');
Route::get('/conversations/{chatSession}', ShowOrgChatConversation::class)->name('conversations.detail');
