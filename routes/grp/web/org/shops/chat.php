<?php

use App\Actions\Chat\Agent\UI\ShowShopAgents;
use App\Actions\Chat\ChatSession\ExportChatConversations;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\UI\ShowOrgChatConversation;
use App\Actions\Chat\ChatSession\UI\ShowOrgChatInbox;
use App\Actions\Chat\ChatSession\UI\ShowShopChatConversations;
use App\Actions\Chat\ChatSession\UI\ShowShopChatDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowShopChatDashboard::class)->name('dashboard');
Route::get('/inbox', [ShowOrgChatInbox::class, 'inShop'])->name('inbox');
Route::get('/dashboard-visitors', [GetChatDashboardVisitors::class, 'inShop'])->name('dashboard-visitors');
Route::get('/agents', ShowShopAgents::class)->name('agents.show');
Route::get('/conversations/export', [ExportChatConversations::class, 'inShop'])->name('conversations.export');
Route::get('/conversations', ShowShopChatConversations::class)->name('conversations.show');
Route::get('/conversations/{chatSession}', [ShowOrgChatConversation::class, 'inShop'])->name('conversations.detail');
