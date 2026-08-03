<?php

use App\Actions\Chat\Agent\UI\ShowShopAgents;
use App\Actions\Chat\ChatSession\ExportChatConversations;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\UI\ShowOrgChatConversation;
use App\Actions\Chat\ChatSession\UI\ShowShopChatConversations;
use App\Actions\Chat\ChatSession\UI\ShowShopChatDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [ShowShopChatDashboard::class, 'inFulfilment'])->name('dashboard');
Route::get('/dashboard-visitors', [GetChatDashboardVisitors::class, 'inFulfilment'])->name('dashboard-visitors');
Route::get('/agents', [ShowShopAgents::class, 'inFulfilment'])->name('agents.show');
Route::get('/conversations/export', [ExportChatConversations::class, 'inFulfilment'])->name('conversations.export');
Route::get('/conversations', [ShowShopChatConversations::class, 'inFulfilment'])->name('conversations.show');
Route::get('/conversations/{chatSession}', [ShowOrgChatConversation::class, 'inFulfilment'])->name('conversations.detail');
