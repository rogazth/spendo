<?php

test('home route redirects', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect();
});
