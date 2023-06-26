<?php
use Illuminate\Support\Facades\Route;

Route::post('poster-webhook', '\App\Poster\WebhookController')->name('webhook.poster');
