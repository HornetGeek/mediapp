<?php

namespace App\Http\Controllers\API\representatives;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\RepRegisterRequest;
use App\Http\Resources\RepsResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Representative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthRepController extends Controller
{
    //

    public function register(RepRegisterRequest $request)
    {
        $data = $request->validated();
        $isCatalogCompany = !empty($data['company_catalog_id']);

        $representative = Representative::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'company_id' => null,
            'company_catalog_id' => $data['company_catalog_id'] ?? null,
            'requested_company_name' => $data['requested_company_name'] ?? null,
            'requested_line_name' => $data['requested_line_name'],
            'registration_status' => $isCatalogCompany ? 'active' : 'pending',
            'daily_visits_limit' => $isCatalogCompany ? config('reps.self_registered_daily_visits_limit') : null,
            'status' => 'active',
            'fcm_token' => $data['fcm_token'] ?? null,
        ]);

        $representative->load(['company', 'companyCatalog']);

        return ApiResponse::sendResponse(
            201,
            $isCatalogCompany
                ? 'Representative registered successfully'
                : 'Representative registered and pending company approval',
            new RepsResource($representative)
        );
    }

    public function login(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required'],
            'fcm_token' => ['required', 'string'],
        ], [], [
            'email' => 'Email',
            'password' => 'Password',
            'fcm_token' => 'FCM Token',
        ]);

        if ($validatedData->fails()) {
            return ApiResponse::sendResponse(422, $validatedData->messages()->first(), []);
        }

        if (Auth::guard('representative')->attempt(['email' => $request->email, 'password' => $request->password])) {
            $representative = Auth::guard('representative')->user();
            $registrationStatus = $representative->registration_status ?? 'active';

            if ($registrationStatus === 'rejected') {
                return ApiResponse::sendResponse(403, 'Representative registration has been rejected', [
                    'error_code' => 'REP_REGISTRATION_REJECTED',
                ]);
            }

            if ($representative->company_id !== null) {
                $company = Company::findOrFail($representative->company_id);

                if ($company->status !== 'active') {
                    $reps_appointments = Appointment::where('representative_id', $representative->id)->where('status', 'pending')->get();

                    foreach($reps_appointments as $appointment) {
                        $appointment->update(['status' => 'cancelled']);
                    }

                    return ApiResponse::sendResponse(401, 'Representative is not authorized to access the system because the company subscription has expired', []);
                }
            }

            $representative->fcm_token = $request->fcm_token;
            $representative->save();
            $representative->load(['company', 'companyCatalog']);

            $data = [
                'token' => $representative->createToken('representative_token', ['representative'])->plainTextToken,
                'name' => $representative->name,
                'email' => $representative->email,
                'fcm_token' => $representative->fcm_token,
                'registration_status' => $registrationStatus,
                'can_book' => $registrationStatus === 'active',
                'requires_company_approval' => $registrationStatus === 'pending',
                'representative' => new RepsResource($representative),
            ];

            if ($registrationStatus === 'pending') {
                $data['error_code'] = 'REP_PENDING_APPROVAL';
            }

            return ApiResponse::sendResponse(200, 'representative login successfully', $data);

        } else {
            return ApiResponse::sendResponse(401, 'representative credentials do not exist', []);
        }
    }


    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {

            $user->fcm_token = null;
            $user->save();

            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

            return ApiResponse::sendResponse(200, 'Representative logged out successfully', []);
        }

        return ApiResponse::sendResponse(401, 'Unauthorized');
    }
}
