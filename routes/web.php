<?php

declare(strict_types=1);

use App\Http\Controllers\Chat\StreamController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/chat/stream', [StreamController::class, 'stream'])->name('chat.stream');
});

require __DIR__.'/auth.php';
