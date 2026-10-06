<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Tasks\Json\GetStaffTask;
use App\Actions\Tasks\Json\GetStaffTaskConversation;
use App\Actions\Tasks\Json\GetStaffTaskOptions;
use App\Actions\Tasks\Json\GetStaffTaskQuickLook;
use App\Actions\Tasks\Json\GetStaffTasks;
use App\Actions\Tasks\DecideStaffTaskEta;
use App\Actions\Tasks\ProposeStaffTaskEta;
use App\Actions\Tasks\RequestStaffTaskHelp;
use App\Actions\Tasks\UpdateStaffTaskContent;
use App\Actions\Tasks\RemoveStaffTaskDepartment;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Tasks\StoreStaffTasksFromList;
use App\Actions\Tasks\SyncStaffTaskCollaborators;
use App\Actions\Tasks\ToggleStaffTaskSubscription;
use App\Actions\Tasks\UI\ShowStaffTask;
use App\Actions\Tasks\UI\ShowStaffTaskAttachment;
use App\Actions\Tasks\UI\ShowStaffTasks;
use App\Actions\Tasks\UI\ShowStaffTasksBoard;
use App\Actions\Tasks\UI\ShowStaffTasksEtaMap;
use App\Actions\Tasks\UI\ShowStaffTasksReports;
use App\Actions\Tasks\UI\IndexStaffTasks;
use App\Actions\Tasks\UpdateStaffTask;
use App\Actions\Tasks\UpdateStaffTaskSubtasks;
use App\Actions\Helpers\TicketProject\AssignWorkToProject;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowStaffTasks::class)->name('index');
Route::get('/all', IndexStaffTasks::class)->name('list_all');
Route::get('/board', ShowStaffTasksBoard::class)->name('board');
Route::get('/reports', ShowStaffTasksReports::class)->name('reports');
Route::get('/eta-map', ShowStaffTasksEtaMap::class)->name('eta_map');
Route::get('/list', GetStaffTasks::class)->name('list');
Route::get('/options', GetStaffTaskOptions::class)->name('options');
Route::post('/', StoreStaffTask::class)->name('store');
Route::post('/import', StoreStaffTasksFromList::class)->name('import');
Route::patch('/{staffTask}', UpdateStaffTask::class)->name('update');
Route::get('/{staffTask}/details', GetStaffTask::class)->name('details');
Route::get('/{staffTask}/attachments/{media:ulid}', ShowStaffTaskAttachment::class)->name('attachments.show')->withoutScopedBindings();
Route::get('/{staffTask}/conversation', GetStaffTaskConversation::class)->name('conversation');
Route::patch('/{staffTask}/collaborators', SyncStaffTaskCollaborators::class)->name('collaborators.update');
Route::patch('/{staffTask}/content', UpdateStaffTaskContent::class)->name('content.update');
Route::post('/{staffTask}/department/removal', RemoveStaffTaskDepartment::class)->name('department.remove');
Route::patch('/{staffTask}/project', [AssignWorkToProject::class, 'inStaffTask'])->name('project.update');
Route::patch('/{staffTask}/subtasks', UpdateStaffTaskSubtasks::class)->name('subtasks.update');
Route::post('/{staffTask}/eta-proposal', ProposeStaffTaskEta::class)->name('eta_proposal.store');
Route::post('/{staffTask}/eta-proposal/decision', DecideStaffTaskEta::class)->name('eta_proposal.decide');
Route::post('/{staffTask}/help', RequestStaffTaskHelp::class)->name('help.store');
Route::post('/{staffTask}/subscription', ToggleStaffTaskSubscription::class)->name('subscription.toggle');
Route::get('/{staffTask}/quick-look', GetStaffTaskQuickLook::class)->name('quick_look');
Route::get('/{staffTask}', ShowStaffTask::class)->name('show');
