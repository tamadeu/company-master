<?php

test('https forwarded requests generate only secure asset urls', function () {
    $response = $this
        ->withServerVariables([
            'HTTP_HOST' => 'erpgame.internal:8080',
            'HTTP_X_FORWARDED_HOST' => 'companymaster.com.br',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])
        ->get('/login');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('https://companymaster.com.br/build/assets/')
        ->not->toContain('http://companymaster.com.br/build/assets/');
});
