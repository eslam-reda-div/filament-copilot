<?php

use EslamRedaDiv\FilamentCopilot\Http\Controllers\StreamController;

it('StreamController class exists', function () {
    expect(class_exists(StreamController::class))->toBeTrue();
});

it('StreamController has stream method', function () {
    $controller = new StreamController;

    expect(method_exists($controller, 'stream'))->toBeTrue();
});


