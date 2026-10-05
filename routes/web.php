<?php

use App\Http\Controllers\DownloadTaskAttachmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tasks/{task}/attachments/{media}/download', DownloadTaskAttachmentController::class)
    ->middleware('auth')
    ->name('tasks.attachments.download');
