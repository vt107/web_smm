<?php

use App\Providers\AppServiceProvider;
use App\Providers\DemoServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    DemoServiceProvider::class,
];
