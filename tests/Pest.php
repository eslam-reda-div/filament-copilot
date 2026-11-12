<?php

use EslamRedaDiv\FilamentCopilot\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;

require_once __DIR__.'/Support/StreamingHelpers.php';

uses(TestCase::class)->in(__DIR__);

function createTestUser(): Authenticatable
{
    return (new class extends Authenticatable
    {
        protected $table = 'users';
    })::create([
        'name' => 'Test User',
        'email' => 'test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}
