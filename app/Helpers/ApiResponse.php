<?php 

namespace App\Helpers;

class ApiResponse {
    static function sendResponse($code = 200, $message = null, $data = null, ?array $pagination = null) {
        $response = [
            'code' => $code,
            'message' => $message,
            'data' => $data
        ];

        if ($pagination !== null) {
            $response['pagination'] = $pagination;
        }

        return response()->json($response, $code);
    }
}
