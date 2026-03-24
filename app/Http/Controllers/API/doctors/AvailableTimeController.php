<?php

namespace App\Http\Controllers\API\doctors;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Doctors;
use Illuminate\Http\Request;

class AvailableTimeController extends Controller
{
    //

    // public function availableTimes($id)
    // {
    //     $doctor = Doctors::with('availableTimes')->findOrFail($id);
        
    //     if (!$doctor) {
    //         return ApiResponse::sendResponse(404, 'Doctor not found');
    //     }
        
    //     return ApiResponse::sendResponse(200, 'Available times retrieved successfully', $doctor->availableTimes);
    // }
}
