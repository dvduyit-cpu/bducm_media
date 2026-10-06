<?php

use App\Http\Controllers\AccessRoleController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\MediaRequestController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureModuleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login.submit');
});
Route::middleware(['auth', EnsureActiveUser::class, EnsureModuleAccess::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/requests', [MediaRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [MediaRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [MediaRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{mediaRequest}', [MediaRequestController::class, 'show'])->name('requests.show');
    Route::get('/requests/{mediaRequest}/edit', [MediaRequestController::class, 'edit'])->name('requests.edit');
    Route::put('/requests/{mediaRequest}', [MediaRequestController::class, 'update'])->name('requests.update');
    Route::post('/requests/{mediaRequest}/transition', [MediaRequestController::class, 'transition'])->name('requests.transition');
    Route::post('/requests/{mediaRequest}/coordinate', [MediaRequestController::class, 'coordinate'])->name('requests.coordinate');
    Route::post('/requests/{mediaRequest}/progress', [MediaRequestController::class, 'progress'])->name('requests.progress');
    Route::post('/requests/{mediaRequest}/links', [MediaRequestController::class, 'saveLink'])->name('requests.links');
    Route::post('/requests/{mediaRequest}/attachments', [MediaRequestController::class, 'upload'])->name('requests.upload');
    Route::get('/attachments/{attachment}', [MediaRequestController::class, 'download'])->name('attachments.download');
    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');
    Route::get('/approvals', [DashboardController::class, 'approvals'])->name('approvals');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
    Route::get('/library', [DashboardController::class, 'library'])->name('library');
    Route::get('/notifications', [DashboardController::class, 'notifications'])->name('notifications');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::get('/help', [HelpController::class, 'index'])->name('help');
    Route::post('/settings', [AdminController::class, 'saveSettings'])->name('settings.save');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users', [AdminController::class, 'saveUser'])->name('users.store');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.destroy');
    Route::put('/users/{user}', [AdminController::class, 'saveUser'])->name('users.update');
    Route::get('/roles', [AccessRoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [AccessRoleController::class, 'store'])->name('roles.store');
    Route::put('/roles/{accessRole}', [AccessRoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{accessRole}', [AccessRoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('/departments', [AdminController::class, 'departments'])->name('departments');
    Route::post('/departments', [AdminController::class, 'saveDepartment'])->name('departments.store');
    Route::delete('/departments/{department}', [AdminController::class, 'deleteDepartment'])->name('departments.destroy');
    Route::put('/departments/{department}', [AdminController::class, 'saveDepartment'])->name('departments.update');
});
