<?php

use App\Models\Invitation;
use App\Models\User;

test('public user data has initials from up to two words', function (string $name, string $initials) {
    $user = new User(['name' => $name]);
    $user->id = 3;

    expect($user->toPublic())
        ->toMatchArray(['id' => 3, 'name' => $name, 'initials' => $initials])
        ->toHaveKey('color');
})->with([
    'two words' => ['Sara Haddad', 'SH'],
    'three words' => ['Omar Abu Khalil', 'OA'],
    'one word' => ['Lina', 'L'],
    'arabic' => ['ديما خالد', 'دخ'],
]);

test('avatar colour is stable for the same user', function () {
    $a = new User(['name' => 'A']);
    $a->id = 11;
    $b = new User(['name' => 'B']);
    $b->id = 11;

    expect($a->toPublic()['color'])->toBe($b->toPublic()['color']);
});

test('an invitation is usable only while pending and not expired', function () {
    $invitation = new Invitation;

    $invitation->expires_at = now()->addDay();
    expect($invitation->isUsable())->toBeTrue();

    $invitation->accepted_at = now();
    expect($invitation->isUsable())->toBeFalse();

    $invitation->accepted_at = null;
    $invitation->expires_at = now()->subMinute();
    expect($invitation->isUsable())->toBeFalse();
});
