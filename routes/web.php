<?php

use App\Http\Controllers\Aeat\AeatFiscalDataController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('aeat.fiscal-data.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('aeat/fiscal-data')->name('aeat.fiscal-data.')->group(function () {
        Route::get('/', [AeatFiscalDataController::class, 'index'])->name('index');
        Route::post('/certificate-profiles', [AeatFiscalDataController::class, 'storeCertificateProfile'])->name('certificate-profiles.store');
        Route::post('/requests', [AeatFiscalDataController::class, 'storeRequest'])->name('requests.store');
        Route::post('/requests/{aeatFiscalDataRequest}/pin', [AeatFiscalDataController::class, 'submitClavePin'])->name('requests.pin');
        Route::post('/requests/{aeatFiscalDataRequest}/retry', [AeatFiscalDataController::class, 'retry'])->name('requests.retry');
        Route::get('/files/{aeatFiscalDataFile}', [AeatFiscalDataController::class, 'download'])->name('files.download');
    });
});

require __DIR__.'/auth.php';