<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/bookings', [BookingController::class, 'index'])->middleware('permission:bookings.view_own');
    Route::get('/bookings/history', [BookingController::class, 'history'])->middleware('permission:bookings.view_own');
    Route::post('/bookings', [BookingController::class, 'store'])->middleware('permission:bookings.create');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    Route::post('/rooms', [RoomController::class, 'store'])->middleware('permission:rooms.manage');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->middleware('permission:rooms.manage');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->middleware('permission:rooms.manage');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:users.manage');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->middleware('permission:users.manage');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
});
