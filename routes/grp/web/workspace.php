<?php

use App\Actions\Workspace\Note\DeleteNote;
use App\Actions\Workspace\Note\StoreNote;
use App\Actions\Workspace\Note\UI\IndexNotes;
use App\Actions\Workspace\Note\UpdateNote;
use App\Actions\Workspace\Task\DeleteTask;
use App\Actions\Workspace\Task\StoreTask;
use App\Actions\Workspace\Task\UI\IndexTasks;
use App\Actions\Workspace\Task\UpdateTask;
use Illuminate\Support\Facades\Route;

Route::name('workspace.')->prefix('workspace')->group(function () {
    Route::name('tasks.')->prefix('tasks')->group(function () {
        Route::get('/', IndexTasks::class)->name('index');
        Route::post('/', StoreTask::class)->name('store');
        Route::put('/{task}', UpdateTask::class)->name('update');
        Route::delete('/{task}', DeleteTask::class)->name('destroy');
    });

    Route::name('notes.')->prefix('notes')->group(function () {
        Route::get('/', IndexNotes::class)->name('index');
        Route::post('/', StoreNote::class)->name('store');
        Route::put('/{note}', UpdateNote::class)->name('update');
        Route::delete('/{note}', DeleteNote::class)->name('destroy');
    });
});
