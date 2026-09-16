<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WellnessSessionController;
use App\Livewire\ActivityBrowser;
use App\Livewire\ActivityPicker;
use App\Livewire\ManageActivities;
use Illuminate\Support\Facades\Route;

// The week. One route, because it shows the picking list while the session is
// open and the result once the wheel has been spun - the same screen in two
// states rather than two screens.
Route::get('/', ActivityPicker::class)->middleware('auth')->name('week');

// The other half of the app: a filter-and-browse view for working out what
// is even possible, rather than committing the team to anything.
Route::get('/browse', ActivityBrowser::class)->middleware('auth')->name('browse');

// The pool itself: add, edit, retire or delete the activities everything
// else draws from.
Route::get('/activities', ManageActivities::class)->middleware('auth')->name('activities');

// Breeze's auth controllers all redirect to route('dashboard') after logging
// in. Keeping the name as an alias is cheaper than editing seven of them.
Route::redirect('/dashboard', '/')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/sessions/{session}/spin', [WellnessSessionController::class, 'spin'])
        ->name('sessions.spin');
    Route::post('/sessions/{session}/rained-off', [WellnessSessionController::class, 'rainedOff'])
        ->name('sessions.rained-off');
    Route::post('/sessions/{session}/skip', [WellnessSessionController::class, 'skip'])
        ->name('sessions.skip');
    Route::post('/sessions/{session}/reset', [WellnessSessionController::class, 'reset'])
        ->name('sessions.reset');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
