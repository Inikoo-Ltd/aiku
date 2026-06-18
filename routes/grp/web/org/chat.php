<?php

use App\Actions\CRM\ChatSession\GetActiveChatSessions;
use App\Actions\CRM\ChatSession\GetChatDashboardVisitors;
use App\Actions\CRM\ChatSession\GetChatVisitorsByCountry;
use App\Actions\CRM\ChatSession\UI\ShowChatConversations;
use App\Actions\CRM\ChatSession\UI\ShowChatDashboard;
use App\Actions\CRM\ChatSession\ExportChatConversations;
use App\Actions\CRM\ChatSession\UI\ShowOrgChatConversation;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowChatDashboard::class)->name('dashboard');
Route::get('/visitors-by-country', GetChatVisitorsByCountry::class)->name('visitors-by-country');
Route::get('/active-sessions', GetActiveChatSessions::class)->name('active-sessions');
Route::get('/dashboard-visitors', GetChatDashboardVisitors::class)->name('dashboard-visitors');
Route::get('/conversations', ShowChatConversations::class)->name('conversations.show');
Route::get('/conversations/export', ExportChatConversations::class)->name('conversations.export');
Route::get('/conversations/{chatSession}', ShowOrgChatConversation::class)->name('conversations.detail');
