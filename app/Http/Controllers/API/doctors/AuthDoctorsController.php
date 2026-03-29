<?php

namespace App\Http\Controllers\API\doctors;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorLoginRequest;
use App\Http\Requests\DoctorsRequest;
use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Specialty;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class AuthDoctorsController extends Controller
{
    //
    public function register(DoctorsRequest  $request)
    {
        
        $doctor = Doctors::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'address_1' => $request->address_1,
            'specialty_id' => $request->specialty_id,
            'fcm_token' => $request->fcm_token,
        ]);
        

        
        // if ($request->has('availabilities')) {
        //     foreach ($request->availabilities as $availability) {
        //         $doctor->availableTimes()->create([
        //             'date' => $availability['date'],
        //             'start_time' => $availability['start_time'],
        //             'end_time' => $availability['end_time'],
        //             'status' => $availability['status'],
        //         ]);
        //     }
        // }

        
        $data['token'] = $doctor->createToken('doctor-token', ['doctor'])->plainTextToken;
        $data['name'] = $doctor->name;
        $data['email'] = $doctor->email;
        $data['fcm_token'] = $doctor->fcm_token;

        return ApiResponse::sendResponse(201, 'Doctor Created Successfully', $data);
    }

    public function login(DoctorLoginRequest $request)
    {

        if (Auth::guard('doctor')->attempt(['email' => $request->email, 'password' => $request->password])) {
            $doctor = Auth::guard('doctor')->user();
            $doctor->fcm_token = $request->fcm_token;
            $doctor->save();
            $data['token'] = $doctor->createToken('doctor-token', ['doctor'])->plainTextToken;
            $data['name'] = $doctor->name;
            $data['email'] = $doctor->email;

            return ApiResponse::sendResponse(200, 'Doctor login successfully', $data);
        } else {
            return ApiResponse::sendResponse(401, 'Doctor credentials do not exist', []);
        }
    }


    public function logout()
    {
        $doctor = auth()->user();
        if ($doctor) {
            $doctor->fcm_token = null;
            $doctor->save();

            $currentToken = $doctor->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

            return ApiResponse::sendResponse(200, 'Doctor logged out successfully', []);
        }


        return ApiResponse::sendResponse(400, 'No active session found', []);
    }
}
