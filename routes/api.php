<?php

use App\Http\Controllers\WorkcolbeefProvisioningController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/workcolbeef/users/sync', WorkcolbeefProvisioningController::class);
