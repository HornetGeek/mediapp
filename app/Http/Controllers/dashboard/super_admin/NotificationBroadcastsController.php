<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationBroadcastJob;
use App\Models\NotificationBroadcast;
use App\Models\Specialty;
use App\Services\VideoDurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationBroadcastsController extends Controller
{
    public function index()
    {
        $broadcasts = NotificationBroadcast::latest()->paginate(15);
        $specialtiesById = Specialty::pluck('name', 'id');

        return view('dashboard.super_admin.notification_broadcasts.index', compact('broadcasts', 'specialtiesById'));
    }

    public function create()
    {
        $specialties = Specialty::orderBy('name')->get();

        return view('dashboard.super_admin.notification_broadcasts.create', compact('specialties'));
    }

    public function store(Request $request, VideoDurationService $videoDurationService)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'prohibits:video'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:30720', 'prohibits:image'],
            'delivery_type' => ['nullable', 'in:both,push_only,in_app_only'],
            'display_type' => ['nullable', 'in:list,modal'],
            'is_skippable' => ['nullable', 'boolean'],
            'target_type' => ['required', 'in:all,specialties'],
            'specialty_ids' => ['required_if:target_type,specialties', 'array'],
            'specialty_ids.*' => ['integer', 'exists:specialties,id'],
        ], [], [
            'title' => 'Title',
            'body' => 'Body',
            'image' => 'Image',
            'video' => 'Video',
            'delivery_type' => 'Delivery Type',
            'display_type' => 'Display Type',
            'is_skippable' => 'Skippable',
        ]);

        if ($validator->fails()) {
            flash()->addError('Failed to create broadcast.');
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $deliveryType = $data['delivery_type'] ?? 'both';
        if ($deliveryType === 'push_only' && $request->hasFile('video')) {
            flash()->addError('Failed to create broadcast.');
            return redirect()->back()->withErrors([
                'video' => 'Video can only be used with in-app notifications.',
            ])->withInput();
        }

        if ($request->hasFile('video')) {
            $durationError = $videoDurationService->validateMaxDuration($request->file('video'), 20);
            if ($durationError !== null) {
                flash()->addError('Failed to create broadcast.');
                return redirect()->back()->withErrors(['video' => $durationError])->withInput();
            }
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('notification-broadcasts', 'public');
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('notification-broadcasts', 'public');
        }

        $displayType = $data['display_type'] ?? 'list';
        if ($deliveryType === 'push_only') {
            $displayType = 'list';
        }

        $isSkippable = $displayType === 'modal'
            ? $request->boolean('is_skippable')
            : true;

        $mediaType = $videoPath ? 'video' : ($imagePath ? 'image' : 'none');

        $broadcast = NotificationBroadcast::create([
            'super_admin_id' => Auth::id(),
            'title' => $data['title'],
            'body' => $data['body'],
            'image_path' => $imagePath,
            'video_path' => $videoPath,
            'media_type' => $mediaType,
            'delivery_type' => $deliveryType,
            'display_type' => $displayType,
            'is_skippable' => $isSkippable,
            'target_type' => $data['target_type'],
            'target_specialty_ids' => $data['target_type'] === 'specialties'
                ? array_values(array_map('intval', $data['specialty_ids'] ?? []))
                : null,
            'status' => 'pending',
        ]);

        SendNotificationBroadcastJob::dispatch($broadcast->id);

        flash()->addSuccess('Broadcast queued. Doctors will receive the notification shortly.');

        return redirect()->route('notification-broadcasts.index');
    }

    public function show($id)
    {
        $broadcast = NotificationBroadcast::findOrFail($id);
        $specialties = collect();
        if ($broadcast->target_type === 'specialties' && !empty($broadcast->target_specialty_ids)) {
            $specialties = Specialty::whereIn('id', $broadcast->target_specialty_ids)->orderBy('name')->get();
        }

        return view('dashboard.super_admin.notification_broadcasts.show', compact('broadcast', 'specialties'));
    }
}
