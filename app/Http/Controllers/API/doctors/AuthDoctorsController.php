<?php

namespace App\Http\Controllers\API\doctors;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorLoginRequest;
use App\Http\Requests\DoctorsRequest;
use App\Models\Doctors;
use App\Services\DoctorAvailabilityCreationService;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    public function googleAuth(Request $request, GoogleIdTokenVerifier $googleVerifier)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'],
        ], [], [
            'id_token' => 'Google ID Token',
            'fcm_token' => 'FCM Token',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->errors()->first(), []);
        }

        $googlePayload = $this->verifiedGooglePayload((string) $request->input('id_token'), $googleVerifier);
        if ($googlePayload === null) {
            return ApiResponse::sendResponse(401, 'Invalid Google token', []);
        }

        $googleId = (string) $googlePayload['sub'];
        $email = strtolower((string) $googlePayload['email']);
        $doctor = Doctors::where('google_id', $googleId)->first()
            ?: Doctors::where('email', $email)->first();

        if (!$doctor) {
            $doctor = Doctors::create([
                'name' => $googlePayload['name'] ?? $email,
                'email' => $email,
                'google_id' => $googleId,
                'google_avatar' => $googlePayload['picture'] ?? null,
                'password' => Hash::make(Str::random(40)),
                'fcm_token' => $request->input('fcm_token'),
            ]);
        } elseif (!empty($doctor->google_id) && $doctor->google_id !== $googleId) {
            return ApiResponse::sendResponse(409, 'Google account is already linked to another doctor account', []);
        } else {
            $this->linkGoogleDoctor($doctor, $googlePayload, $request->input('fcm_token'));
        }

        return ApiResponse::sendResponse(200, 'Doctor login successfully', $this->doctorTokenPayload($doctor));
    }

    public function googleRegister(
        Request $request,
        GoogleIdTokenVerifier $googleVerifier,
        DoctorAvailabilityCreationService $availabilityCreation
    )
    {
        $validator = Validator::make($request->all(), [
            'id_token' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_1' => ['nullable', 'string', 'max:255'],
            'specialty_id' => ['nullable', 'exists:specialties,id'],
            'fcm_token' => ['nullable', 'string'],
            'available_times' => ['sometimes', 'array'],
            'available_times.*' => ['array'],
            'available_times.*.date' => ['required_with:available_times'],
            'available_times.*.start_time' => ['required_with:available_times', 'string'],
            'available_times.*.end_time' => ['required_with:available_times', 'string'],
            'available_times.*.ends_next_day' => ['nullable', 'boolean'],
            'available_times.*.max_reps_per_range' => ['nullable', 'integer', 'min:1'],
            'available_times.*.visit_time_type' => ['sometimes', 'required', 'string', 'in:before,after,between'],
        ], [], [
            'id_token' => 'Google ID Token',
            'phone' => 'Phone',
            'address_1' => 'Address 1',
            'specialty_id' => 'Specialty',
            'fcm_token' => 'FCM Token',
            'available_times' => 'Available Times',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->errors()->first(), []);
        }

        $googlePayload = $this->verifiedGooglePayload((string) $request->input('id_token'), $googleVerifier);
        if ($googlePayload === null) {
            return ApiResponse::sendResponse(401, 'Invalid Google token', []);
        }

        $googleId = (string) $googlePayload['sub'];
        $email = strtolower((string) $googlePayload['email']);

        $existingDoctor = Doctors::where('google_id', $googleId)->first()
            ?: Doctors::where('email', $email)->first();

        if ($existingDoctor && !empty($existingDoctor->google_id) && $existingDoctor->google_id !== $googleId) {
            return ApiResponse::sendResponse(409, 'Google account is already linked to another doctor account', []);
        }

        try {
            $doctor = DB::transaction(function () use (
                $request,
                $googlePayload,
                $email,
                $googleId,
                $availabilityCreation,
                $existingDoctor
            ) {
                $doctor = $existingDoctor ?: new Doctors([
                    'name' => $googlePayload['name'] ?? $email,
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                ]);

                $doctor->google_id = $googleId;
                $doctor->google_avatar = $googlePayload['picture'] ?? $doctor->google_avatar;
                if (!$doctor->exists || trim((string) $doctor->name) === '') {
                    $doctor->name = $googlePayload['name'] ?? $email;
                }
                if (!$doctor->exists || trim((string) $doctor->email) === '') {
                    $doctor->email = $email;
                }

                $normalizedPhone = $this->normalizeOptionalPhone($request->input('phone'));
                if ($normalizedPhone !== null) {
                    $doctor->phone = $normalizedPhone;
                }

                $normalizedAddress = $this->normalizeOptionalString($request->input('address_1'));
                if ($normalizedAddress !== null) {
                    $doctor->address_1 = $normalizedAddress;
                }

                if ($request->filled('specialty_id')) {
                    $doctor->specialty_id = $request->input('specialty_id');
                }

                if ($request->filled('fcm_token')) {
                    $doctor->fcm_token = $request->input('fcm_token');
                }

                $doctor->save();

                $preparedAvailabilities = $availabilityCreation->prepareAvailabilityPayloads(
                    $doctor,
                    (array) $request->input('available_times', [])
                );
                if (isset($preparedAvailabilities['error'])) {
                    throw new \InvalidArgumentException($preparedAvailabilities['error']);
                }

                foreach ($preparedAvailabilities['payloads'] as $availabilityPayload) {
                    $availabilityCreation->createAvailabilityRows($doctor, $availabilityPayload);
                }

                return $doctor;
            });
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::sendResponse(422, $exception->getMessage(), []);
        }

        $statusCode = $existingDoctor ? 200 : 201;
        $message = $existingDoctor ? 'Doctor profile updated successfully' : 'Doctor Created Successfully';

        return ApiResponse::sendResponse($statusCode, $message, $this->doctorTokenPayload($doctor));
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

    private function verifiedGooglePayload(string $idToken, GoogleIdTokenVerifier $googleVerifier): ?array
    {
        $payload = $googleVerifier->verify($idToken);
        if (!is_array($payload)) {
            return null;
        }

        if (empty($payload['sub']) || empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (($payload['email_verified'] ?? false) !== true) {
            return null;
        }

        return $payload;
    }

    private function linkGoogleDoctor(Doctors $doctor, array $googlePayload, ?string $fcmToken): void
    {
        $doctor->google_id = (string) $googlePayload['sub'];
        $doctor->google_avatar = $googlePayload['picture'] ?? $doctor->google_avatar;
        if ($fcmToken !== null) {
            $doctor->fcm_token = $fcmToken;
        }
        $doctor->save();
    }

    private function doctorTokenPayload(Doctors $doctor): array
    {
        $missingFields = $doctor->missingProfileFields();

        return [
            'token' => $doctor->createToken('doctor-token', ['doctor'])->plainTextToken,
            'name' => $doctor->name,
            'email' => $doctor->email,
            'fcm_token' => $doctor->fcm_token,
            'profile_required' => !empty($missingFields),
            'missing_fields' => $missingFields,
        ];
    }

    private function googleUserPayload(array $googlePayload): array
    {
        return [
            'google_id' => (string) $googlePayload['sub'],
            'name' => $googlePayload['name'] ?? null,
            'email' => strtolower((string) $googlePayload['email']),
            'picture' => $googlePayload['picture'] ?? null,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);
        $prefix = str_starts_with($trimmed, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        $normalized = $prefix . $digits;

        return preg_replace('/^(\+?20|0020)0(?=1)/', '$1', $normalized) ?? $normalized;
    }

    private function normalizeOptionalPhone($phone): ?string
    {
        if (!is_string($phone)) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        return $this->normalizePhone($trimmed);
    }

    private function normalizeOptionalString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
