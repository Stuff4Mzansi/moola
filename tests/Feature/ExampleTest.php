<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response for an authenticated user', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/')->assertOk();
});
