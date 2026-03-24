<?php

namespace App\Http\Controllers\API\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\RepsCreateRequest;
use App\Http\Resources\AreaResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\LineResource;
use App\Http\Resources\NotificationsResource;
use App\Http\Resources\RepsResource;
use App\Models\Area;
use App\Models\Company;
use App\Models\Line;
use App\Models\Notification;
use App\Models\Representative;
use App\Services\RepresentativeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompaniessController extends Controller
{
    //

    public function getCompanyProfile()
    {
        $company = Company::find(Auth::user()->id);

        if (!$company) {
            return ApiResponse::sendResponse(404, 'Company not found', []);
        }

        return ApiResponse::sendResponse(200, 'Company profile retrieved successfully', new CompanyResource($company));
    }

    public function getRepresentativeProfile(Request $request)
    {
        $representative = $request->reps_id;
        $rep = Representative::where('id', $representative)->with(['lines', 'areas'])->first();

        if (!$rep) {
            return ApiResponse::sendResponse(404, 'Representative not found', []);
        }

        return ApiResponse::sendResponse(200, 'Representative profile retrieved successfully', new RepsResource($rep));
    }

    public function createReps(RepsCreateRequest $request, RepresentativeService $repService)
    {
        return $repService->create($request->validated());
    }

    public function editReps(Request $request, RepresentativeService $repService)
    {
        $validatedData = Validator::make($request->all(), [
            'rep_id' => ['required', 'integer', 'exists:representatives,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('representatives', 'email')->ignore($request->rep_id)],
            'phone' => ['sometimes', 'string', 'max:20'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'line_ids' => ['sometimes', 'array'],
            'line_ids.*' => ['integer', 'exists:lines,id'],
            'area_ids' => ['sometimes', 'array'],
            'area_ids.*' => ['integer', 'exists:areas,id'],
        ], [], [
            'rep_id' => 'Representative ID',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'password' => 'Password',
            'line_ids' => 'Line IDs',
            'area_ids' => 'Area IDs',
        ]);

        if ($validatedData->fails()) {
            return ApiResponse::sendResponse(422, 'Validation Error', $validatedData->messages()->all());
        }

        return $repService->edit($validatedData->validated());
    }

    public function deleteReps($repId, RepresentativeService $repService)
    {
        return $repService->delete($repId);
    }


    public function getReps()
    {
        $reps = Representative::where('company_id', Auth::user()->id)->with(['lines', 'company', 'areas'])->get();

        $data = RepsResource::collection($reps);

        if ($reps->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No representatives found', []);
        } else {
            return ApiResponse::sendResponse(200, 'Representatives retrieved successfully', $data);
        }
    }

    public function createLine(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ], [], [
            'name' => 'Name',
        ]);

        if ($validatedData->fails()) {
            return ApiResponse::sendResponse(422, 'Validation Error', $validatedData->messages()->all());
        }

        $line = new Line();
        $line->name = $request->name;
        $line->company_id = Auth::user()->id;

        $line->save();

        return ApiResponse::sendResponse(200, 'Line created successfully', new LineResource($line));
    }

    public function getLines()
    {

        $lines = Line::where('company_id', Auth::user()->id)->get();

        $data = LineResource::collection($lines);

        if ($lines->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No lines found', []);
        } else {
            return ApiResponse::sendResponse(200, 'Lines retrieved successfully', $data);
        }
    }

    public function createArea(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ], [], [
            'name' => 'Name',
        ]);

        if ($validatedData->fails()) {
            return ApiResponse::sendResponse(422, 'Validation Error', $validatedData->messages()->all());
        }

        $companyId = Auth::user()->id;
        $area = new Area();
        $area->name = $request->name;
        $area->company_id = $companyId;
        $area->save();
        return ApiResponse::sendResponse(200, 'Area created successfully', $area);
    }

    public function getAreas()
    {

        $areas = Area::where('company_id', Auth::user()->id)->get();

        $data = AreaResource::collection($areas);

        if ($areas->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No areas found', []);
        } else {
            return ApiResponse::sendResponse(200, 'areas retrieved successfully', $data);
        }
    }

    public function filterReps(Request $request)
    {
        $query = Representative::where('company_id', Auth::user()->id);

        $filters = $request->only(['name']);
        $reps = $query->with(['lines', 'areas'])->filter($filters)->get();

        return ApiResponse::sendResponse(200, 'Filtered representatives retrieved successfully', RepsResource::collection($reps));
    }

    public function getNotifications()
    {
        $company = Company::find(Auth::user()->id);
        // dd($company);

        $notifications = $company->notifications()
            ->orderBy('created_at', 'desc')
            ->get();
        return ApiResponse::sendResponse(200, 'Notifications fetched successfully', NotificationsResource::collection($notifications));
    }

    public function markNotificationAsRead($notification_id)
    {
        $company = Company::find(Auth::user()->id);

        $notification = Notification::where('id', $notification_id)
            ->where('notifiable_id', $company->id)
            ->where('notifiable_type', Company::class)
            ->first();

        if (!$notification) {
            return ApiResponse::sendResponse(404, 'Notification not found or not yours', []);
        }

        $notification->update(['is_read' => true]);

        return ApiResponse::sendResponse(200, 'Notification marked as read successfully', new NotificationsResource($notification));
    }

    public function deleteNotification($notification_id)
    {
        $company = Company::find(Auth::user()->id);

        $notification = Notification::where('id', $notification_id)
            ->where('notifiable_id', $company->id)
            ->where('notifiable_type', Company::class)
            ->first();

        if (!$notification) {
            return ApiResponse::sendResponse(404, 'Notification not found or not yours', []);
        }

        $notification->delete();

        return ApiResponse::sendResponse(200, 'Notification deleted successfully', []);
    }

    public function clearAllNotifications(Request $request)
    {
        $company = Company::find(Auth::user()->id);

        $company->notifications()->delete();

        return ApiResponse::sendResponse(200, 'All notifications cleared successfully', []);
    }
}
