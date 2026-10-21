<?php

test('the root path displays welcome', function () {
    actingAsUser();

    $response = $this->get('/');

    $response->assertOk()->assertSee('Mulai Kasir');
});
