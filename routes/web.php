<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDependencyController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('projects', ProjectController::class)->only(['show', 'store', 'update', 'destroy']);
    Route::resource('projects.lists', TaskListController::class)->shallow()->only(['show', 'store', 'update', 'destroy']);

    Route::resource('projects.documents', DocumentController::class)->shallow()->only(['show', 'store', 'update', 'destroy']);
    Route::post('projects/{project}/attachments', [AttachmentController::class, 'store'])->name('projects.attachments.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::put('lists/{list}/statuses', [StatusController::class, 'sync'])->name('lists.statuses.sync');
    Route::post('lists/{list}/tasks', [TaskController::class, 'store'])->name('lists.tasks.store');
    Route::put('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::post('tasks/{task}/dependencies', [TaskDependencyController::class, 'store'])->name('tasks.dependencies.store');
    Route::delete('tasks/{task}/dependencies/{dependsOn}', [TaskDependencyController::class, 'destroy'])->name('tasks.dependencies.destroy');
    Route::resource('tasks', TaskController::class)->only(['update', 'destroy']);
    Route::post('tasks/{task}/timer', [TimeEntryController::class, 'start'])->name('timer.start');
    Route::delete('timer', [TimeEntryController::class, 'stop'])->name('timer.stop');
    Route::post('tasks/{task}/time-entries', [TimeEntryController::class, 'store'])->name('tasks.time-entries.store');
    Route::resource('time-entries', TimeEntryController::class)->only(['update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
