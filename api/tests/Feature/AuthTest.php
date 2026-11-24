<?php

use App\Models\User;

test('a user can register and gets a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Sara Haddad',
        'email' => 'Sara@Example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'initials', 'color', 'email']])
        ->assertJsonPath('user.email', 'sara@example.com')
        ->assertJsonPath('user.initials', 'SH');
});

test('login fails with a wrong password', function () {
    User::factory()->create(['email' => 'sara@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'sara@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('a token gives access to /me and logout revokes it', function () {
    User::factory()->create(['email' => 'sara@example.com']);
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'sara@example.com', 'password' => 'password'])->json('token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'sara@example.com');
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('protected routes need a token', function () {
    $this->getJson('/api/v1/boards')->assertUnauthorized();
});
