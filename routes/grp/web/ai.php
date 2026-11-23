<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\UI\IndexChatAuraQuestions;
use App\Actions\Helpers\AI\UI\ShowAiDashboard;
use App\Actions\Helpers\AI\UI\ShowAiFeature;
use App\Actions\Web\ShoppingAssistant\UI\ShowShoppingAssistantConversation;
use App\Actions\Web\ShoppingAssistant\UI\ShowShoppingAssistantPerformance;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowAiDashboard::class)->name('dashboard');
Route::get('/features/{feature}', ShowAiFeature::class)->name('features.show');
Route::get('/shopping-assistant', ShowShoppingAssistantPerformance::class)->name('shopping_assistant.show');
Route::get('/shopping-assistant/conversations/{conversation:ulid}', ShowShoppingAssistantConversation::class)->name('shopping_assistant.conversations.show');
Route::get('/aura/questions', IndexChatAuraQuestions::class)->name('aura.questions');
