<?php

namespace App\Http\Controllers\API\Superadmin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthSuperAdminController extends Controller
{
    //

    public function login(Request $request) {
        $validatedData = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required'],
        ], [], [
            'email' => 'Email',
            'password' => 'Password',
        ]);

        if($validatedData->fails()) {
            return ApiResponse::sendResponse(422, 'Validation Error', $validatedData->messages()->all());
        }

        if(Auth::attempt(['email'=>$request->email, 'password'=>$request->password])) {
            $user = Auth::user();
            $data['token'] = $user->createToken('super-admin-token', ['super-admin'])->plainTextToken;
            $data['name'] = $user->name;
            $data['email'] = $user->email;

            return ApiResponse::sendResponse(200, 'SuperAdmin Login Successfully', $data);
        } else {
            return ApiResponse::sendResponse(401, 'SuperAdmin Credientials dosen\'t exist', []);
        }
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return ApiResponse::sendResponse(200, 'SuperAdmin Logged Out Successfully', []);
    }
    
}
