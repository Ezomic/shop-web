<?php

declare(strict_types=1);

it('serves the storefront homepage', function () {
    $this->get('/')->assertStatus(200);
});
