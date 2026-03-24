<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordOtpMail;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordCompanyController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $otp = rand(100000, 999999);
        $expiryMinutes = 3;

        if (!Company::where('email', $request->email)->exists()) {
            return ApiResponse::sendResponse(404, 'Email not registered.', []);
        }

        $now = Carbon::now();
        $expiresAt = $now->addMinutes($expiryMinutes);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $otp,
                'created_at' => Carbon::now()
            ]
        );
        
        // Send the OTP to the user's email
        $company = Company::where('email', $request->email)->first();

        Mail::to($request->email)
            ->send(new ResetPasswordOtpMail($otp, $company));
        // Mail::raw("رمز التحقق لإعادة تعيين كلمة المرور هو: $otp", function ($message) use ($request) {
        //     $message->to($request->email)->subject('Verification code to reset your password');
        // });

        return ApiResponse::sendResponse(200, 'The verification code has been successfully sent to your email address.', [
            'expires_otp_in_seconds' => $expiryMinutes * 60,
            'expire_at' => $expiresAt->toDateTimeString()
        ]);

        // return response()->json(['message' => 'Reset code sent successfully.']);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:companies,email',
            'token' => 'required|digits:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return ApiResponse::sendResponse(400, 'Verification code expired.', [
                'expires_in_seconds' => 0
            ]);
        }

        //create OTP time
        $expiresAt = Carbon::parse($record->created_at)->addMinutes(3);
        $now = Carbon::now();


        $secondsLeft = $now->diffInSeconds($expiresAt, false); // false => for return minus
        $secondsLeft = $secondsLeft > 0 ? $secondsLeft : 0;

        if ($now->greaterThan($expiresAt)) {
            return ApiResponse::sendResponse(400, 'Verification code expired.', [
                'expires_in_seconds' => 0,
                'expired' => true
            ]);
        }

        return ApiResponse::sendResponse(200, 'The verification code is correct.', [
            'expires_in_seconds' => $secondsLeft,
            'expired' => false
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:companies,email',
            'password' => 'required|min:8|confirmed',
        ]);


        // Update the doctor's password
        DB::table('companies')->where('email', $request->email)->update([
            'password' => bcrypt($request->password),
        ]);

        // Delete the token record
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return ApiResponse::sendResponse(200, 'Password successfully reset.', []);
    }
}
