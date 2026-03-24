<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\dashboard\admin\AdminController;
use App\Http\Controllers\dashboard\admin\RepresentativesController;
use App\Http\Controllers\dashboard\AuthController;
use App\Http\Controllers\dashboard\super_admin\CompaniesController;
use App\Http\Controllers\dashboard\super_admin\DoctorsController;
use App\Http\Controllers\dashboard\super_admin\PackagesController;
use App\Http\Controllers\dashboard\super_admin\SpecialitiesController;
use App\Http\Controllers\dashboard\super_admin\SpecialtiesController;
use App\Http\Controllers\dashboard\super_admin\SuperadminController;
use App\Http\Controllers\dashboard\super_admin\VisitsTrackerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
// Route::get('/cron/run', function () {
//     \Artisan::call('schedule:run');
//     return 'OK';
// })->middleware('cron.secret');


Route::get('/', [AuthController::class, 'loginForm'])->name('login.form');
Route::post('login', [AuthController::class, 'login'])->name('login.store');

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::prefix('superadmin')->group(function () {
        Route::get('dashboard', [SuperadminController::class, 'index'])->name('superadmin.dashboard');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('email-feedback', [SuperadminController::class, 'storeEmailFedback'])->name('superadmin.email.feedback');

        Route::post('store/app-versions', [SuperadminController::class, 'storeAppVersions'])->name('superadmin.app.versions');

        // ===== Packages Routes =====
        Route::controller(PackagesController::class)->group(function () {
            Route::get('packages', 'index')->name('packages.index');
            Route::post('packages/store', 'store')->name('packages.store');
            Route::put('packages/update/{id}', 'update')->name('packages.update');
            Route::get('packages/delete/{id}', 'destroy')->name('packages.delete');
        });

        // ===== Companies Routes =====
        Route::controller(CompaniesController::class)->group(function () {
            Route::get('companies', 'index')->name('companies.index');
            Route::post('companies/store', 'store')->name('companies.store');
            Route::put('companies/update/{id}', 'update')->name('companies.update');
            Route::get('companies/delete/{id}', 'destroy')->name('companies.delete');

            Route::get('companies/status/', 'getCompaniesByStatus')->name('companies.status');
        });

        
        // ===== Specialties Routes =====
        Route::controller(SpecialtiesController::class)->group(function () {
            Route::get('specialties', 'index')->name('specialties.index');
            Route::post('specialties/store', 'store')->name('specialties.store');
            Route::put('specialties/update/{id}', 'update')->name('specialties.update');
            Route::get('specialties/delete/{id}', 'destroy')->name('specialties.delete');
        });
        
        // ===== Doctors Routes =====
        Route::controller(DoctorsController::class)->group(function () {
            Route::get('doctors', 'index')->name('doctors.index');
            Route::post('doctors/store', 'store')->name('doctors.store');
            Route::put('doctors/update/{id}', 'update')->name('doctors.update');
            Route::get('doctors/delete/{id}', 'destroy')->name('doctors.delete');
        });

        Route::controller(VisitsTrackerController::class)->group(function () {
            Route::get('visits', 'VisitsTrack')->name('visits.index');
            Route::get('visits/delete/{id}', 'destroy')->name('visits.delete');
            Route::get('visits/report/csv/', 'generateVisitsReportCSV')->name('visits.report.csv');
            Route::get('visits/report/pdf/', 'generateVisitReportPDF')->name('visits.report.pdf');
            Route::get('visits/report/csv/{bookId}', 'generateVisitsReportByIdCSV')->name('visitsId.report.csv');
            Route::get('visits/report/pdf/{bookId}', 'generateVisitReportByIdPDF')->name('visitsId.report.pdf');
        });


    });
});

// Route::middleware(['auth', 'role:admin'])->group(function () {
//     Route::prefix('admin')->group(function () {
//         Route::get('dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
//         Route::post('logout', [AuthController::class, 'logout'])->name('logout');

//         // ===== Representatives Routes =====
//         Route::controller(RepresentativesController::class)->group(function () {
//             Route::get('representatives', 'index')->name('representatives.index');
//             Route::post('representatives/store', 'store')->name('representatives.store');
//             Route::put('representatives/update/{id}', 'update')->name('representatives.update');
//             Route::get('representatives/delete/{id}', 'destroy')->name('representatives.delete');
//         });
//     });
// });


// Route::get('/', function () {
//     return view('welcome');
// });

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

// require __DIR__ . '/auth.php';
