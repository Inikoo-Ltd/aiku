<?php

use App\Actions\Chat\ChatSession\ExportChatConversations;
use App\Actions\Chat\ChatSession\GetActiveChatSessions;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\GetChatVisitorsByCountry;
use App\Actions\Chat\ChatSession\UI\ShowChatConversations;
use App\Actions\Chat\ChatSession\UI\ShowChatDashboard;
use App\Actions\Chat\ChatSession\UI\ShowOrgChatConversation;
use App\Actions\CRM\ChatAutomation\UI\ShowChatAutomations;
use App\Actions\CRM\ChatAutomation\UI\CreateChatAutomation;
use App\Actions\CRM\ChatAutomation\UI\EditChatAutomation;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowChatDashboard::class)->name('dashboard');
Route::get('/visitors-by-country', GetChatVisitorsByCountry::class)->name('visitors-by-country');
Route::get('/active-sessions', GetActiveChatSessions::class)->name('active-sessions');
Route::get('/dashboard-visitors', GetChatDashboardVisitors::class)->name('dashboard-visitors');
Route::get('/conversations', ShowChatConversations::class)->name('conversations.show');
Route::get('/conversations/export', ExportChatConversations::class)->name('conversations.export');
Route::get('/conversations/{chatSession}', ShowOrgChatConversation::class)->name('conversations.detail');

Route::get('/automations', ShowChatAutomations::class)->name('automations.show');
Route::get('/automations/create', CreateChatAutomation::class)->name('automations.create');
Route::get('/automations/{chatAutomation}/edit', EditChatAutomation::class)->name('automations.edit');
