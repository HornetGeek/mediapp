<?php

namespace App\Http\Controllers\API\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\LineResource;
use App\Http\Resources\LinesResource;
use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LinesController extends Controller
{
    //

    public function getLines () {
        
        $lines = Line::where('company_id', Auth::user()->id)->get();

        $data = LineResource::collection($lines);
        
        if($lines->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No lines found', []);
        } else {
            return ApiResponse::sendResponse(200, 'Lines retrieved successfully', $data);
        }
        
    }
}
