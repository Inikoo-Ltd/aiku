<?php

use App\Actions\Chat\Agent\UI\ShowShopAgents;
use App\Actions\Chat\ChatSession\ExportChatConversations;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\UI\ShowOrgChatConversation;
use App\Actions\Chat\ChatSession\UI\ShowOrgChatInbox;
use App\Actions\Chat\ChatSession\UI\ShowShopChatConversations;
use App\Actions\Chat\ChatSession\UI\ShowShopChatDashboard;
use App\Actions\Chat\Whatsapp\Templates\DeleteWhatsappMessageTemplate;
use App\Actions\Chat\Whatsapp\Templates\RefreshWhatsappMessageTemplate;
use App\Actions\Chat\Whatsapp\Templates\StoreWhatsappMessageTemplate;
use App\Actions\Chat\Whatsapp\Templates\SyncWhatsappMessageTemplates;
use App\Actions\Chat\Whatsapp\Templates\UI\CreateWhatsappMessageTemplate;
use App\Actions\Chat\Whatsapp\Templates\UI\EditWhatsappMessageTemplate;
use App\Actions\Chat\Whatsapp\Templates\UpdateWhatsappMessageTemplate;
use App\Actions\Chat\Whatsapp\Templates\UpdateWhatsappTemplateHeaderMedia;
use App\Actions\Chat\Whatsapp\Templates\UpdateWhatsappTemplateTags;
use App\Actions\Chat\Whatsapp\Templates\UI\IndexWhatsappMessageTemplates;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowShopChatDashboard::class)->name('dashboard');
Route::get('/inbox', [ShowOrgChatInbox::class, 'inShop'])->name('inbox');
Route::get('/dashboard-visitors', [GetChatDashboardVisitors::class, 'inShop'])->name('dashboard-visitors');
Route::get('/agents', ShowShopAgents::class)->name('agents.show');
Route::get('/conversations/export', [ExportChatConversations::class, 'inShop'])->name('conversations.export');
Route::get('/conversations', ShowShopChatConversations::class)->name('conversations.show');
Route::get('/conversations/{chatSession}', [ShowOrgChatConversation::class, 'inShop'])->name('conversations.detail');
Route::get('/whatsapp-templates', [IndexWhatsappMessageTemplates::class, 'inShop'])->name('whatsapp_templates.index');
Route::get('/whatsapp-templates/create', CreateWhatsappMessageTemplate::class)->name('whatsapp_templates.create');
Route::post('/whatsapp-templates', StoreWhatsappMessageTemplate::class)->name('whatsapp_templates.store');
Route::get('/whatsapp-templates/{metaMessageTemplate}/edit', EditWhatsappMessageTemplate::class)->name('whatsapp_templates.edit');
Route::patch('/whatsapp-templates/{metaMessageTemplate}', UpdateWhatsappMessageTemplate::class)->name('whatsapp_templates.update');
Route::post('/whatsapp-templates/{metaMessageTemplate}/header-media', UpdateWhatsappTemplateHeaderMedia::class)->name('whatsapp_templates.header_media');
Route::patch('/whatsapp-templates/{metaMessageTemplate}/variables', UpdateWhatsappTemplateTags::class)->name('whatsapp_templates.variables');
Route::delete('/whatsapp-templates/{metaMessageTemplate}', DeleteWhatsappMessageTemplate::class)->name('whatsapp_templates.delete');
Route::post('/whatsapp-templates/{metaMessageTemplate}/refresh', RefreshWhatsappMessageTemplate::class)->name('whatsapp_templates.refresh');
Route::post('/whatsapp-templates/sync', SyncWhatsappMessageTemplates::class)->name('whatsapp_templates.sync');
