<?php

use App\Http\Controllers\Api\StringController;
use Illuminate\Support\Facades\Route;

// String endpoints without CSRF protection
Route::prefix('strings')->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class
])->group(function () {
    Route::post('/', [StringController::class, 'store']);
    Route::get('/filter-by-natural-language', [StringController::class, 'filterByNaturalLanguage']);
    Route::get('/', [StringController::class, 'index']);
    Route::get('/{value}', [StringController::class, 'show']);
    Route::delete('/{value}', [StringController::class, 'destroy']);
});

// Health check endpoint
Route::get('/', function () {
    return response()->json([
        'message' => 'String Analyzer API',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /strings' => 'Create/analyze string',
            'GET /strings/{value}' => 'Get specific string',
            'GET /strings' => 'Get all strings with filtering',
            'GET /strings/filter-by-natural-language' => 'Natural language filtering',
            'DELETE /strings/{value}' => 'Delete string'
        ]
    ]);
});
