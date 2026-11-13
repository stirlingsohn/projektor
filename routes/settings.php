<?php

use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\Settings\BrandingController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('settings/profile/avatar', [AvatarController::class, 'store'])->name('avatar.store');
    Route::delete('settings/profile/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
    Route::get('users/{user}/avatar', [AvatarController::class, 'show'])->name('users.avatar');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('settings/branding', [BrandingController::class, 'update'])->name('branding.update');
    Route::delete('settings/branding/logo', [BrandingController::class, 'destroyLogo'])->name('branding.logo.destroy');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});

Route::get('branding/logo', [BrandingController::class, 'logo'])->name('branding.logo');
