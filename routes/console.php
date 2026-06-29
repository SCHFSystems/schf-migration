<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:schf-migration', function (): void {
    $this->info('SCHF Migration synthetic runtime');
})->purpose('Display SCHF Migration runtime information');
