<?php

namespace App\Http\Controllers\dashboard\admin;

use App\Http\Controllers\Controller;
use App\Models\PushNotificationCampaign;
use App\Models\Specialty;
use App\Services\DoctorSpecialtyPushNotificationService;
use App\Services\VideoDurationService;
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

    public function send(Request $request, DoctorSpecialtyPushNotificationService $service, VideoDurationService $videoDurationService)
    {
        $validator = Validator::make($request->all(), [
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'delivery_type' => ['nullable', 'in:both,push_only,in_app_only'],
            'display_type' => ['nullable', 'in:list,modal'],
            'is_skippable' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'prohibits:video'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:30720', 'prohibits:image'],
        ], [], [
            'specialty_id' => 'Specialty',
            'title' => 'Title',
            'body' => 'Body',
            'delivery_type' => 'Delivery Type',
            'display_type' => 'Display Type',
            'is_skippable' => 'Skippable',
            'image' => 'Image',
            'video' => 'Video',
        ]);

        if ($validator->fails()) {
            flash()->addError('حدث خطأ أثناء إرسال الإشعار.');
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $deliveryType = $data['delivery_type'] ?? 'both';
        if ($deliveryType === 'push_only' && $request->hasFile('video')) {
            flash()->addError('حدث خطأ أثناء إرسال الإشعار.');
            return redirect()->back()->withErrors([
                'video' => 'Video can only be used with in-app notifications.',
            ])->withInput();
        }

        if ($request->hasFile('video')) {
            $durationError = $videoDurationService->validateMaxDuration($request->file('video'), 20);
            if ($durationError !== null) {
                flash()->addError('حدث خطأ أثناء إرسال الإشعار.');
                return redirect()->back()->withErrors(['video' => $durationError])->withInput();
            }
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('notification-campaigns', 'public');
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('notification-campaigns', 'public');
        }

        $displayType = $data['display_type'] ?? 'list';
        if ($deliveryType === 'push_only') {
            $displayType = 'list';
        }

        $isSkippable = $displayType === 'modal'
            ? $request->boolean('is_skippable')
            : true;

        $campaign = $service->send(
            (int) Auth::id(),
            (int) $data['specialty_id'],
            $data['title'],
            $data['body'],
            $imagePath,
            $videoPath,
            $displayType,
            $isSkippable,
            $deliveryType
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
