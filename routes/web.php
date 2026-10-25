<?php

use Illuminate\Support\Facades\Route;

// La aplicación es solo API: la raíz lleva a la documentación interactiva.
Route::redirect('/', '/api/documentation');
