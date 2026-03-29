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
        $settings = AppVersion::whereIn('app_type', AppVersion::SUPPORTED_APP_TYPES)
            ->whereIn('platform', AppVersion::SUPPORTED_PLATFORMS)
            ->get()
            ->keyBy(fn (AppVersion $version) => "{$version->app_type}.{$version->platform}");

        $versions = [];
        $forced = [];
        foreach (AppVersion::SUPPORTED_APP_TYPES as $appType) {
            foreach (AppVersion::SUPPORTED_PLATFORMS as $platform) {
                $key = "{$appType}.{$platform}";
                $versions[$appType][$platform] = $settings[$key]->version ?? '';
                $forced[$appType][$platform] = (int) ($settings[$key]->is_forced ?? 0);
            }
        }

        $data = [
            'total_doctors' => $totalDoctors,
            'total_companies' => $totalCompanies,
            // 'pending_visits' => $pendingVisits,
            'confirmed_visits' => $confirmedVisits,
            'cancelled_visits' => $cancelledVisits,
            'feedback_email' => $feedback_email ? $feedback_email->email_feedback : null,
            'versions' => $versions,
            'forced' => $forced,
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
            'apps' => 'required|array',
            'apps.company' => 'required|array',
            'apps.company.both' => 'required|array',
            'apps.company.android' => 'required|array',
            'apps.company.ios' => 'required|array',
            'apps.doctor' => 'required|array',
            'apps.doctor.both' => 'required|array',
            'apps.doctor.android' => 'required|array',
            'apps.doctor.ios' => 'required|array',
            'apps.company.both.version' => 'required|string',
            'apps.company.android.version' => 'required|string',
            'apps.company.ios.version' => 'required|string',
            'apps.doctor.both.version' => 'required|string',
            'apps.doctor.android.version' => 'required|string',
            'apps.doctor.ios.version' => 'required|string',
            'apps.company.both.is_forced' => 'required|boolean',
            'apps.company.android.is_forced' => 'required|boolean',
            'apps.company.ios.is_forced' => 'required|boolean',
            'apps.doctor.both.is_forced' => 'required|boolean',
            'apps.doctor.android.is_forced' => 'required|boolean',
            'apps.doctor.ios.is_forced' => 'required|boolean',
        ]);

        foreach (AppVersion::SUPPORTED_APP_TYPES as $appType) {
            foreach (AppVersion::SUPPORTED_PLATFORMS as $platform) {
                $platformData = $request->input("apps.$appType.$platform");

                AppVersion::updateOrCreate(
                    [
                        'app_type' => $appType,
                        'platform' => $platform,
                    ],
                    [
                        'version' => $platformData['version'],
                        'is_forced' => $platformData['is_forced'],
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'App versions updated successfully!');
    }
}
