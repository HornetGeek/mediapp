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

class VisitTrackingController extends Controller
{
    public function VisitsTrack()
    {
        $list_visits  = Appointment::with(['company', 'doctor.specialty', 'representative.company'])->where('status', 'confirmed')
            ->orderBy('date', 'desc')
            ->get();

        if ($list_visits->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No Visits Found', []);
        }
        return ApiResponse::sendResponse(200, 'Visits Retrieved Successfully', VisitTrackingResource::collection($list_visits));
    }

    public function filterVisits(Request $request)
    {
        $filters = $request->only(['doctor_name', 'company_name', 'from_date', 'to_date']);

        $query = Appointment::advancedFilter($filters)->get();

        if ($query->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No Visits Found with the given filters', []);
        }

        return ApiResponse::sendResponse(200, 'Filtered Visits Retrieved Successfully', VisitTrackingResource::collection($query));
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
}
