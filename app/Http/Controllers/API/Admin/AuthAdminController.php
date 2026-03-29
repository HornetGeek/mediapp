<?php

namespace App\Http\Controllers\API\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyAdminLoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthAdminController extends Controller
{
    //

    public function login(CompanyAdminLoginRequest $request)
    {


        if (Auth::guard('company')->attempt(['email' => $request->email, 'password' => $request->password])) {
            $company = Auth::guard('company')->user();

            if ($request->filled('fcm_token')) {
                $company->fcm_token = $request->fcm_token;
                $company->save();
            }

            if ($company->status != 'active') {
                Auth::guard('company')->logout();
                return ApiResponse::sendResponse(403, 'Company is not active', []);
            }

            $data = [
                'token' => $company->createToken('company-token', ['company'])->plainTextToken,
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'email' => $company->email,
                    'num_of_visits_day' => $company->visits_per_day,
                    'num_of_reps' => $company->num_of_reps,
                    'fcm_token' => $company->fcm_token,
                ]
            ];




            return ApiResponse::sendResponse(200, 'Company login successfully', $data);
        } else {
            return ApiResponse::sendResponse(401, 'Invalid company credentials', []);
        }
    }
    public function logout(Request $request)
    {
        $company = $request->user();
        if ($company) {
            $company->fcm_token = null;
            $company->save();

            $currentToken = $company->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }
        }

        //or using tokens instead of currentAccessToken
        // $request->user()->tokens()->delete(); // this will delete all tokens of the user
        return ApiResponse::sendResponse(200, 'Company Logged Out Successfully', []);
    }
}
