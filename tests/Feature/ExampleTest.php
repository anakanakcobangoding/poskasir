<?php

test('the root path redirects to the cashier page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('pos.index'));
});
