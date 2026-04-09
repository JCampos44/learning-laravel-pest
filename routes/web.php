<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify', function () {
    return response()->json([
        'message' => 'Please verify your email address.',
    ]);
})->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (string $id, string $hash) {
    $user = User::query()->findOrFail($id);

    if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
        abort(403);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();

        event(new Verified($user));
    }

    return response()->json([
        'message' => 'Email verified successfully.',
    ]);
})->middleware('signed')->name('verification.verify');
