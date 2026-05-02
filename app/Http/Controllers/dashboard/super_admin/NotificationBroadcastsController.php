<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationBroadcastJob;
use App\Models\NotificationBroadcast;
use App\Models\Specialty;
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'target_type' => ['required', 'in:all,specialties'],
            'specialty_ids' => ['required_if:target_type,specialties', 'array'],
            'specialty_ids.*' => ['integer', 'exists:specialties,id'],
        ]);

        if ($validator->fails()) {
            flash()->addError('Failed to create broadcast.');
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('notification-broadcasts', 'public');
        }

        $broadcast = NotificationBroadcast::create([
            'super_admin_id' => Auth::id(),
            'title' => $data['title'],
            'body' => $data['body'],
            'image_path' => $imagePath,
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
