<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppVersion;
use App\Models\Company;
use App\Models\Doctors;
use App\Models\FeedbackEmail;
use App\Models\User;
use Illuminate\Http\Request;

class SuperadminController extends Controller
{
    //

    public function index()
    {
        $confirmedVisits = Appointment::where('status', 'confirmed')->count();
        // $pendingVisits = Appointment::where('status', 'pending')->count();
        $cancelledVisits = Appointment::where('status', 'cancelled')->count();
        $totalDoctors = Doctors::count();
        $totalCompanies = Company::count();
        $feedback_email = FeedbackEmail::first();
        $versions = AppVersion::pluck('version', 'app_type');
        $forced   = AppVersion::pluck('is_forced', 'app_type');
        $data = [
            'total_doctors' => $totalDoctors,
            'total_companies' => $totalCompanies,
            // 'pending_visits' => $pendingVisits,
            'confirmed_visits' => $confirmedVisits,
            'cancelled_visits' => $cancelledVisits,
            'feedback_email' => $feedback_email ? $feedback_email->email_feedback : null,
            'versions' => $versions,
            'forced' => $forced
        ];

        return view('dashboard.super_admin.index', compact('data'));
    }

    public function storeEmailFedback(Request $request)
    {
        $request->validate([
            'email_feedback' => 'required|email',
        ]);
        if (FeedbackEmail::first()) {
            FeedbackEmail::where('id', 1)->update(['email_feedback' => $request->email_feedback]);
            return redirect()->back()->with('success', 'Update email feedback successfully!');
        }
        FeedbackEmail::create(
            ['email_feedback' => $request->email_feedback]
        );


        return redirect()->back()->with('success', 'Save email feedback successfully!');
    }

    public function storeAppVersions(Request $request)
    {
        $request->validate([
            'apps.*.version' => 'required|string',
            'apps.*.is_forced' => 'required|boolean',
        ]);

        foreach ($request->apps as $appType => $data) {

            AppVersion::updateOrCreate(
                [
                    'app_type' => $appType,
                ],
                [
                    'version' => $data['version'],
                    'is_forced' => $data['is_forced'],
                ]
            );
        }

        return redirect()->back()->with('success', 'App versions updated successfully!');
    }
}
