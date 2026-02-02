<?php

declare(strict_types=1);

use App\Http\Controllers\Chat\StreamController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth")->group(function () {
    // Rate limit: 15 chat requests per minute per user to prevent API abuse
    Route::post("/chat/stream", [StreamController::class, "stream"])
        ->middleware("throttle:15,1")
        ->name("chat.stream");
});

require __DIR__ . "/auth.php";
