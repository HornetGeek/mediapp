<?php

use App\Helpers\ApiResponse;
use App\Http\Controllers\API\Admin\AuthAdminController;
use App\Http\Controllers\API\Admin\CompaniessController;
use App\Http\Controllers\API\Admin\LinesController;
use App\Http\Controllers\API\AppVersionController;
use App\Http\Controllers\API\doctors\AuthDoctorsController;
use App\Http\Controllers\API\doctors\AvailableTimeController;
use App\Http\Controllers\API\doctors\DoctorsController;
use App\Http\Controllers\API\representatives\AuthRepController;
use App\Http\Controllers\API\representatives\RepFavoriteDoctorController;
use App\Http\Controllers\API\representatives\RepsController;
use App\Http\Controllers\API\Superadmin\AuthSuperAdminController;
use App\Http\Controllers\API\Superadmin\CompaniesController;
use App\Http\Controllers\API\Superadmin\MakeCompanyController;
use App\Http\Controllers\API\Superadmin\MakeDoctorsController;
use App\Http\Controllers\API\Superadmin\VisitTrackingController;
use App\Http\Controllers\Auth\ForgotPasswordCompanyController;
use App\Http\Controllers\Auth\ForgotPasswordDoctorController;
use App\Services\FirebaseNotificationService;
use App\Http\Controllers\FCMController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::get('/cron/run', function () {

    
//     if (request('token') !== env('CRON_TOKEN')) {
//         abort(403, 'Unauthorized');
//     }

//     // تشغيل cron Laravel
//     Artisan::call('schedule:run');

//     return ApiResponse::sendResponse(200, 'Cron executed successfully', []);;
// });


Route::post('/check-version', [AppVersionController::class, 'check']);
// ------------------ Auth SuperAdmin Routes


Route::prefix('super-admin')->group(function () {
    Route::post('login', [AuthSuperAdminController::class, 'login']);

    Route::middleware(['auth:sanctum', 'ability:super-admin'])->group(function () {
        Route::post('logout', [AuthSuperAdminController::class, 'logout']);

        Route::controller(VisitTrackingController::class)->group(function () {
            // Visit Tracking
            Route::get('visits-track', 'VisitsTrack');
            // Visit Tracking Filter
            Route::get('visits-track/filter', 'filterVisits');
            // download visit tracking  (CSV, PDF)
            Route::get('visits-track/csv/{book_id}', 'generateVisitsReportCSV');
            Route::get('visits-track/pdf/{book_id}', 'generateVisitReportPDF');

            // General statistics
            Route::get('statistics', 'getStatistics');
            Route::get('/monthly-report/download', 'downloadMonthlyReport');
            Route::get('/quarterly-report/download', 'downloadQuarterlyReport');
        });


        // Create Company
        Route::controller(MakeCompanyController::class)->group(function () {
            Route::post('/create-package', 'createPackage');
            Route::get('/packages', 'getPackages');
            Route::delete('/delete-package/{package_id}', 'deletePackage');
            Route::post('create-company', 'createCompany');
            Route::get('companies', 'getCompanies');
            Route::put('change-company-status/{company_id}', 'changeCompanyStatus');
            Route::delete('delete-company/{company_id}', 'deleteCompany');
            Route::get('/active-companies', 'getActiveCompanies');
            Route::get('/inactive-companies', 'getInactiveCompanies');
        });

        Route::controller(MakeDoctorsController::class)->group(function () {
            // Create Doctors
            Route::post('create-doctor', 'createDoctor');
            Route::get('specialities', 'get_Speciality');
            Route::get('doctors', 'getDoctors');
            Route::delete('delete-doctor/{doctor_id}', 'deleteDoctor');
        });
    });
});


// ------------------ Doctors Routes
Route::prefix('doctor')->group(function () {
    Route::post('register', [AuthDoctorsController::class, 'register']);
    Route::post('login', [AuthDoctorsController::class, 'login']);
    Route::post('/forgot-password', [ForgotPasswordDoctorController::class, 'sendResetCode']);
    Route::post('/verify-code', [ForgotPasswordDoctorController::class, 'verifyCode']);
    Route::post('/reset-password', [ForgotPasswordDoctorController::class, 'resetPassword']);
    Route::get('specialities', [DoctorsController::class, 'get_Speciality']);
    Route::middleware(['auth:sanctum', 'ability:doctor'])->group(function () {
        Route::post('logout', [AuthDoctorsController::class, 'logout']);
        // Route::post('/send-fcm', [AuthDoctorsController::class, 'send']);
        // Route::post('/update-fcm-token', [AuthDoctorsController::class, 'updateFcmToken']);

        // Route::get('/test-notification', function (FirebaseNotificationService $service) {
        //     $token = 'fT7AjATLTpu7iri5RiLkbB:APA91bGVY01_BinLmj5iKQuUFVel68G1h9p5dKgqNymI3jzKoapt1rZuKc6s2MmQdbuKQirl6PhMzGw9pTeZcpe3lbVhYPxJc1gwm2PJ4ramKXZq2fD4Lig';
        //     $title = 'Hello from Laravel';
        //     $body = 'This is a test notification';
        //     // $data = ['type' => 'test', 'id' => '123'];

        //     $response = $service->sendNotification($token, $title, $body);
        //     return response()->json($response);
        // });


        Route::controller(DoctorsController::class)->group(function () {
            Route::get('doctor-available-times', 'availableTimes');
            Route::get('profile', 'getDoctorProfile');
            Route::put('save-available-time', 'saveAvailableTimes');
            // Route::post('add-available-time', 'addAvailableTime');
            Route::get('/doctor/appointments', 'getDoctorAppointments');
            Route::put('/cancel-appointment/{book_id}', 'cancellationAppointment');
            Route::get('/filter-appointment/reps', 'filterAppointments');
            Route::delete('/delete-appointment/{book_id}', 'deleteAppointment');
            Route::get('notifications', 'getNotifications');
            Route::put('/notifications/{id}/read', 'markNotificationAsRead');
            Route::delete('/notifications/{id}', 'deleteNotification');
            Route::post('/availabilities/copy-last-month', 'copyLastMonthTimes');
            Route::post('/block/representative/{id}', 'blockRepresentative');
            Route::post('/block/company/{id}', 'blockCompany');
            Route::delete('/doctor-notifications/clear', 'clearAllNotifications');
            Route::delete('/unblock/company/{id}', 'unblockCompany');
            Route::delete('/unblock/representative/{id}', 'unblockRepresentative');
            Route::get('/blocked/companies', 'getBlockedCompanies');
            Route::get('/blocked/reps', 'getBlockedRepresentatives');
            Route::put('/edit-profile', 'updateDoctorProfile');
            Route::get('/contact-us', 'contactUs');
            Route::get('/appointments/cancelled', 'getCancelledAppointments');
            Route::get('/appointments/pending', 'getPendingAppointments');
            Route::get('/appointments/confirmed', 'getConfirmedAppointments');
            Route::put('/edit-status', 'editStatus');
            Route::get('/search/block-list', 'searchBlockList');
        });
    });
});


// ------------------ Company Routes
Route::prefix('company')->group(function () {
    Route::post('login', [AuthAdminController::class, 'login']);
    Route::post('/forgot-password', [ForgotPasswordCompanyController::class, 'sendResetCode']);
    Route::post('/verify-code', [ForgotPasswordCompanyController::class, 'verifyCode']);
    Route::post('/reset-password', [ForgotPasswordCompanyController::class, 'resetPassword']);
    Route::get('/reset-password', [ForgotPasswordCompanyController::class, 'counterTimeEndOtp']);
    Route::middleware(['auth:sanctum', 'ability:company'])->group(function () {
        Route::post('logout', [AuthAdminController::class, 'logout']);

        Route::controller(CompaniessController::class)->group(function () {
            // get company profile
            Route::get('profile', 'getCompanyProfile');
            // get reps profile
            Route::get('reps/profile', 'getRepresentativeProfile');
            // create Reps
            Route::post('create-rep', 'createReps');
            // Edit Reps
            Route::put('edit-rep', 'editReps');
            // delete Reps
            Route::delete('delete-rep/{rep_id}', 'deleteReps');
            // get Reps
            Route::get('reps', 'getReps');
            // filter Reps
            Route::get('filter-reps', 'filterReps');
            // create Lines and get Lines
            Route::post('create-line', 'createLine');
            Route::get('lines', 'getLines');
            // create areas
            Route::post('create-area', 'createArea');
            Route::get('areas', 'getAreas');

            // Notifications
            Route::get('notifications', 'getNotifications');
            Route::put('/notifications/{id}/read', 'markNotificationAsRead');
            Route::delete('/notifications/{id}', 'deleteNotification');
            Route::delete('/company-notifications/clear', 'clearAllNotifications');
        });
    });
});

// ------------------ Reps Routes
Route::prefix('reps')->group(function () {
    Route::post('login', [AuthRepController::class, 'login']);

    Route::middleware(['auth:sanctum', 'ability:representative'])->group(function () {
        Route::post('logout', [AuthRepController::class, 'logout']);
        // state of code 

        Route::controller(RepsController::class)->group(function () {
            Route::get('profile', 'getRepsProfile');
            Route::get('specialities', 'get_Speciality');
            Route::get('/doctors/all', 'getAvailableTimeForDoctor');
            Route::get('/doctors/search', 'filterDoctors');
            Route::get('/docotr/{id}', 'getDoctorProfile');
            Route::post('/booking', 'bookAppointment');
            Route::get('/booked/appointments', 'getRepsAppointments');
            Route::put('/cancel-appointment/{book_id}', 'cancellationBooking');
            Route::put('/completed-appointment/{book_id}', 'completedBooking');
            Route::delete('/delete-appointment/{book_id}', 'deleteAppointment');
            Route::get('notifications', 'getNotifications');
            Route::put('/notifications/{id}/read', 'markNotificationAsRead');
            Route::delete('/notifications/{id}', 'deleteNotification');
            Route::delete('/reps-notifications/clear', 'clearAllNotifications');
            Route::get('appointments/by-status', 'getAppointmentsByStatus');
            Route::put('appointment/change-status', 'changeAppointmentStatus');
            Route::get('/doctorsBySpeciality', 'getDoctorsBySpeciality');
            Route::get('/appointments/cancelled', 'getCancelledAppointments');
            Route::get('/appointments/pending', 'getPendingAppointments');
            Route::get('/appointments/confirmed', 'getConfirmedAppointments');
            Route::get('/appointments/lefting', 'getLeftingAppointments');
            Route::get('/appointments/filtration', 'filterAppointmentsByDateAndSpecialty');
            Route::get('/appointments-beforTwo-days', 'getAppointmentsNowAndBeforeTwoDay');
            Route::get('/appointments/suspended', 'getSuspendedAppointments');

        });

        // Favorite Doctors
        Route::controller(RepFavoriteDoctorController::class)->group(function () {
            Route::post('/add-favorite-doctor', 'addFavoriteDoctor');
            Route::delete('/remove-favorite-doctor', 'removeFavoriteDoctor');
            Route::get('/favorite-doctors', 'list');
            Route::get('/search/favorite-doctors', 'searchFavoriteDoctors');
        });
    });
});








// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
