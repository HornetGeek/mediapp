<?php

namespace App\Http\Controllers\API\representatives;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthRepController extends Controller
{
    //



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
            $company = Company::findOrFail($representative->company_id);
            if ($company->status === 'active') {
                $representative->fcm_token = $request->fcm_token;
                $representative->save();
                $data['token'] = $representative->createToken('representative_token', ['representative'])->plainTextToken;
                $data['name'] = $representative->name;
                $data['email'] = $representative->email;
                $data['fcm_token'] = $representative->fcm_token;

                return ApiResponse::sendResponse(200, 'representative login successfully', $data);
            } else {
                $reps_appointments = Appointment::where('representative_id', $representative->id)->where('status', 'pending')->get();

                foreach($reps_appointments as $appointment) {
                    $appointment->update(['status' => 'cancelled']);
                }
                return ApiResponse::sendResponse(401, 'Representative is not authorized to access the system because the company subscription has expired', []);
            }

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

            $user->currentAccessToken()->delete();

            return ApiResponse::sendResponse(200, 'Representative logged out successfully', []);
        }

        return ApiResponse::sendResponse(401, 'Unauthorized');
    }
}
