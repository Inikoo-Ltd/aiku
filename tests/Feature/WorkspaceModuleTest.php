<?php

use App\Models\HumanResources\Employee;
use App\Models\Workspace\Note;
use App\Models\Workspace\Task;

test('tasks can be created', function () {
    $task = Task::create([
        'title' => 'Test Task',
        'status' => 'Pending',
    ]);
    expect($task->id)->not->toBeNull();
});

test('notes can be created', function () {
    $employee = Employee::factory()->create();
    $note = Note::create([
        'title' => 'Test Note',
        'employee_id' => $employee->id,
    ]);
    expect($note->id)->not->toBeNull();
});
