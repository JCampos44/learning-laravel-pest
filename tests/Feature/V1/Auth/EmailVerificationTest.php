<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

uses(LazilyRefreshDatabase::class);

it('verifies the email when the signed link is visited', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->get($url)
        ->assertOk()
        ->assertJson([
            'message' => 'Email verified successfully.',
        ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class, function (Verified $event) use ($user) {
        return $event->user->getKey() === $user->getKey();
    });
});
