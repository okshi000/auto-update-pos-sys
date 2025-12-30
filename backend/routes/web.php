<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Serve SPA for all non-API routes
Route::get('/{any?}', function () {
    $indexPath = public_path('index.html');
    
    if (File::exists($indexPath)) {
        return response(File::get($indexPath), 200)
            ->header('Content-Type', 'text/html');
    }
    
    // Fallback to Laravel welcome if SPA not built
    return view('welcome');
})->where('any', '^(?!api).*$');
