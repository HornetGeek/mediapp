<?php

namespace App\Http\Controllers\API\representatives;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorResource;
use App\Models\Doctors;
use App\Services\DoctorBusyStatusService;
use Illuminate\Http\Request;

class RepFavoriteDoctorController extends Controller
{
    public function addFavoriteDoctor(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
        ]);

        // Check if the doctor is already in favorites
        if (auth()->user()->favoriteDoctors()->where('doctors_id', $request->doctor_id)->exists()) {
            return ApiResponse::sendResponse(400, 'Doctor is already in favorites', []);
        }

        auth()->user()->favoriteDoctors()->attach($request->doctor_id, ['is_fav' => true]);

        auth()->user()->favoriteDoctors()->syncWithoutDetaching([$request->doctor_id]);

        // Update the doctor's is_fav status
        $doctor = auth()->user()->favoriteDoctors()->find($request->doctor_id);
        if ($doctor) {
            $doctor->update(['is_fav' => true]);
        }


        return ApiResponse::sendResponse(200, 'Doctor added to favorites successfully', []);
    }

    public function removeFavoriteDoctor(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
        ]);

        $doctor = auth()->user()->favoriteDoctors()->detach($request->doctor_id);

        if ($doctor) {
            return ApiResponse::sendResponse(200, 'Doctor removed from favorites successfully', []);
        }

        return ApiResponse::sendResponse(500, 'Failed to remove doctor from favorites', []);
    }

    public function list(DoctorBusyStatusService $doctorBusyStatus)
    {
        $rep = auth()->user();
        $favoriteDoctors = $rep->favoriteDoctors()
            ->with([
                'availableTimes' => function ($query) {
                    $query->where('status', 'available');
                },
                'specialty',
            ])
            ->get();

        $doctorBusyStatus->normalizeDoctorCollectionBusyState($favoriteDoctors);

        if ($favoriteDoctors->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No favorite doctors found', []);
        }
        return ApiResponse::sendResponse(200, 'Favorite doctors retrieved successfully', DoctorResource::collection($favoriteDoctors));
    }

    public function searchFavoriteDoctors(Request $request, DoctorBusyStatusService $doctorBusyStatus)
    {
        $request->validate([
            'search' => 'required|string',
        ]);

        $rep = auth()->user();

        $favoriteDoctors = $rep->favoriteDoctors()
            ->favoriteFilter($request->search)
            ->with([
                'availableTimes' => function ($query) {
                    $query->where('status', 'available');
                },
                'specialty',
            ])
            ->get();

        $doctorBusyStatus->normalizeDoctorCollectionBusyState($favoriteDoctors);

        if ($favoriteDoctors->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No favorite doctors found matching the search criteria', []);
        }

        return ApiResponse::sendResponse(200, 'Favorite doctors retrieved successfully', DoctorResource::collection($favoriteDoctors));
    }
}
