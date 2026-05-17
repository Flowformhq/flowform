<?php

declare(strict_types=1);

arch('Models do not depend on HTTP layer')
    ->expect('App\Models')
    ->not->toUse('App\Http');

arch('Models do not depend on Filament')
    ->expect('App\Models')
    ->not->toUse('App\Filament');

arch('Models do not depend on Console commands')
    ->expect('App\Models')
    ->not->toUse('App\Console');

arch('Services do not depend on Filament')
    ->expect('App\Services')
    ->not->toUse('App\Filament');

arch('Services do not depend on HTTP controllers')
    ->expect('App\Services')
    ->not->toUse('App\Http\Controllers');

arch('Controllers do not depend on Filament')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Filament');

arch('Console commands use services and models')
    ->expect('App\Console\Commands')
    ->toOnlyUse([
        'App\Services',
        'App\Models',
        'App\Console',
        'Illuminate',
        'Symfony',
    ]);
