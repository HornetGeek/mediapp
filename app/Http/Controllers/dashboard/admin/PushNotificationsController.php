<?php

namespace App\Http\Controllers\dashboard\admin;

use App\Http\Controllers\Controller;
use App\Models\PushNotificationCampaign;
use App\Models\Specialty;
use App\Services\DoctorSpecialtyPushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PushNotificationsController extends Controller
{
    public function index()
    {
        $specialties = Specialty::withCount('doctors')->orderBy('name')->get();
        $campaigns = PushNotificationCampaign::with(['specialty', 'sender'])
            ->latest()
            ->paginate(15);

        return view('dashboard.admin.push_notifications.index', compact('specialties', 'campaigns'));
    }

    public function send(Request $request, DoctorSpecialtyPushNotificationService $service)
    {
        $validator = Validator::make($request->all(), [
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ], [], [
            'specialty_id' => 'Specialty',
            'title' => 'Title',
            'body' => 'Body',
        ]);

        if ($validator->fails()) {
            flash()->addError('حدث خطأ أثناء إرسال الإشعار.');
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $campaign = $service->send(
            (int) Auth::id(),
            (int) $data['specialty_id'],
            $data['title'],
            $data['body']
        );

        flash()->addSuccess(sprintf(
            'تم إرسال الإشعار. الأطباء المستهدفون: %d، تم الإرسال: %d، فشل: %d.',
            $campaign->total_doctors,
            $campaign->sent_count,
            $campaign->failed_count
        ));

        return redirect()->route('admin.push-notifications.index');
    }
}
