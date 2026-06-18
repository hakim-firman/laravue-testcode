<?php

use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/events')->name('home');

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/data', [EventController::class, 'data'])->name('events.data');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::post('events/{event}/attendees', [AttendeeController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('events.attendees.store');

Route::get('events-visual-1', [EventController::class, 'visualOne'])->name('events.visual1');
Route::get('events-visual-2', [EventController::class, 'visualTwo'])->name('events.visual2');

Route::inertia('dashboard', 'Dashboard')->name('dashboard');

require __DIR__.'/settings.php';
