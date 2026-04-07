<?php

namespace App\Http\Controllers\API\Superadmin;

use App\Exports\ReportVisitsExport;
use App\Exports\VisitsTrackExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\VisitTrackingResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Doctors;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VisitTrackingController extends Controller
{
    public function VisitsTrack(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $list_visits  = Appointment::with(['company', 'doctor.specialty', 'representative.company'])->where('status', 'confirmed')
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($list_visits);
        $items = VisitTrackingResource::collection($list_visits->items());

        if ($list_visits->total() === 0) {
            return ApiResponse::sendResponse(404, 'No Visits Found', [], $pagination);
        }
        return ApiResponse::sendResponse(200, 'Visits Retrieved Successfully', $items, $pagination);
    }

    public function filterVisits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'doctor_name' => 'Doctor',
            'company_name' => 'Company',
            'from_date' => 'From Date',
            'to_date' => 'To Date',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $filters = $request->only(['doctor_name', 'company_name', 'from_date', 'to_date']);
        $perPage = (int) $request->input('per_page', 10);

        $query = Appointment::with(['company', 'doctor.specialty', 'representative.company'])
            ->advancedFilter($filters)
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($query);
        $items = VisitTrackingResource::collection($query->items());

        if ($query->total() === 0) {
            return ApiResponse::sendResponse(200, 'No Visits Found with the given filters', [], $pagination);
        }

        return ApiResponse::sendResponse(200, 'Filtered Visits Retrieved Successfully', $items, $pagination);
    }

    public function generateVisitsReportCSV($bookId)
    {
        $data = Appointment::find($bookId);
        // dd($data);
        if (!$data) {
            return ApiResponse::sendResponse(404, 'Data not found', []);
        }

        $fileName = rand(1000, 9999) . '_visits_track.csv';
        $filePath = 'exports/' . $fileName;

        Excel::store(new ReportVisitsExport($data->id), $filePath, 'public');

        $downloadUrl = asset('storage/' . $filePath);

        return ApiResponse::sendResponse(200, 'Download link generated successfully', [
            'download_url' => $downloadUrl,
        ]);
    }

    public function generateVisitReportPDF($bookId)
    {
        $data = Appointment::with(['doctor', 'representative', 'company'])->where('id', $bookId)->get();

        if ($data->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No visits found', []);
        }

        $fileName = rand(1000, 9999) . '_visits_report.pdf';
        $filePath = 'exportsPDF/' . $fileName;

        $pdf = Pdf::loadView('reports.visits', compact('data'));

        Storage::disk('public')->put($filePath, $pdf->output());

        $downloadUrl = asset('storage/' . $filePath);

        return ApiResponse::sendResponse(200, 'Download link generated successfully', [
            'download_url' => $downloadUrl,
        ]);
    }

    public function getStatistics()
    {
        $confirmedVisits = Appointment::where('status', 'confirmed')->count();
        // $pendingVisits = Appointment::where('status', 'pending')->count();
        $cancelledVisits = Appointment::where('status', 'cancelled')->count();
        $totalDoctors = Doctors::count();
        $totalCompanies = Company::count();



        return ApiResponse::sendResponse(200, 'Statistics Retrieved Successfully', [
            'total_doctors' => $totalDoctors,
            'total_companies' => $totalCompanies,
            // 'pending_visits' => $pendingVisits,
            'confirmed_visits' => $confirmedVisits,
            'cancelled_visits' => $cancelledVisits,
        ]);
    }

    public function downloadMonthlyReport(Request $request)
    {
        $request->validate([
            'type' => 'required|in:pdf,csv',
            'month' => 'required|date_format:Y-m',
        ]);

        $type = $request->type;
        $month = Carbon::createFromFormat('Y-m', $request->month);

        $data = $this->getMonthlyReportData($month);

        if ($data->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No data found for selected month', []);
        }

        if ($type === 'pdf') {
            $fileName = rand(1000, 9999) . '_visits_monthly_report.pdf';
            $filePath = 'exportsPDF_monthly/' . $fileName;

            $pdf = PDF::loadView('reports.visits', compact('data', 'month'));

            Storage::disk('public')->put($filePath, $pdf->output());

            $downloadUrl = asset('storage/' . $filePath);

            return ApiResponse::sendResponse(200, 'Download link generated successfully', [
                'download_url' => $downloadUrl,
            ]);
        }

        if ($type === 'csv') {
            $fileName = rand(1000, 9999) . '_visits_track.csv';
            $filePath = 'exportsCSV_monthly/' . $fileName;            

            Excel::store(new VisitsTrackExport($data), $filePath, 'public');
            $downloadUrl = asset('storage/' . $filePath);

            return ApiResponse::sendResponse(200, 'Download link generated successfully', [
                'download_url' => $downloadUrl,
            ]);
            
        }

        return ApiResponse::sendResponse(400, 'Invalid type', []);
    }

    public function downloadQuarterlyReport(Request $request)
    {
        $request->validate([
            'type' => 'required|in:pdf,csv',
            'from_month' => 'required|digits:2',
            'from_year' => 'required|digits:4',
            'to_month' => 'required|digits:2',
            'to_year' => 'required|digits:4',
        ]);

        
        $from = Carbon::createFromFormat('Y-m', "{$request->from_year}-{$request->from_month}")->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', "{$request->to_year}-{$request->to_month}")->endOfMonth();

        if ($from > $to) {
            return ApiResponse::sendResponse(422, 'Start date must be before end date', []);
        }

        $data = Appointment::whereBetween('date', [$from, $to])->get();

        if ($data->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No data found in selected range', []);
        }

        if ($request->type === 'pdf') {
            $fileName = rand(1000, 9999) . '_visits_monthly_report.pdf';
            $filePath = 'exportsPDF_quarterly/' . $fileName;

            $pdf = PDF::loadView('reports.visits', compact('data', 'from', 'to'));
            Storage::disk('public')->put($filePath, $pdf->output());

            $downloadUrl = asset('storage/' . $filePath);

            return ApiResponse::sendResponse(200, 'Download link generated successfully', [
                'download_url' => $downloadUrl,
            ]);
            
        }

        if ($request->type === 'csv') {

            $fileName = rand(1000, 9999) . '_visits_track.csv';
            $filePath = 'exportsCSV_quarterly/' . $fileName;            

            Excel::store(new VisitsTrackExport($data), $filePath, 'public');
            $downloadUrl = asset('storage/' . $filePath);

            return ApiResponse::sendResponse(200, 'Download link generated successfully', [
                'download_url' => $downloadUrl,
            ]);
        }

        return ApiResponse::sendResponse(400, 'Invalid type', []);
    }

    private function getMonthlyReportData($month)
    {
        return Appointment::with(['doctor', 'representative', 'company'])
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->get();
    }

    private function buildPaginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}
