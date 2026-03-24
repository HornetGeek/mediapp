<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Exports\VisitsReportExportForDashboard;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VisitsTrackerController extends Controller
{
    public function visitsTrack()
    {
        $list_visits  = Appointment::with(['company', 'doctor.specialty', 'representative.company'])
            ->orderBy('date', 'desc')
            ->get();
        $get_data = $list_visits->map(function ($visit) {
            return [
                'id' => $visit->id,
                'date' => Carbon::parse($visit->date)->format('Y-m-d'),
                'start_time' => $visit->start_time->format('h:i A'),
                'end_time' => $visit->end_time->format('h:i'),
                'status' => $visit->status,
                'company' => [
                    'name' => $visit->company->name,
                ],
                'doctor' => [
                    'name' => $visit->doctor->name,
                    'specialization' => $visit->doctor->specialty->name ?? 'not specified',
                ],
                'representative' => [
                    'name' => $visit->representative->name,
                    'company_name' => $visit->representative->company->name,
                ],
            ];
        });

        // dd($get_data);
        return view('dashboard.super_admin.visits_tracker.index', compact('get_data'));
    }

    public function destroy($id)
    {
        $visit = Appointment::findOrFail($id);
        $visit->delete();

        flash()->addSuccess('Visit deleted successfully.');
        return redirect()->route('visits.index');
    }

    public function generateVisitsReportCSV()
    {
        $visits = Appointment::with(['doctor.specialty', 'representative.company'])
            ->orderBy('date', 'desc')
            ->get();

        // $fileName = 'visits_report_' . now()->format('Y_m_d') . '.xlsx';
        // dd($visits);
        $randomNumber = rand(1000, 9999);
        $fileName = 'visits_' . $randomNumber . '.csv';

        return Excel::download(new VisitsReportExportForDashboard($visits), $fileName, \Maatwebsite\Excel\Excel::CSV);
    }

    public function generateVisitReportPDF()
    {
        $data = Appointment::with(['doctor.specialty', 'representative.company'])
            ->orderBy('date', 'desc')
            ->get();

        if ($data->isEmpty()) {
            flash()->addError('No visits found.');
            return redirect()->route('visits.index');
        }

        $pdf = Pdf::loadView('reports.visits', ['data' => $data]);
        return $pdf->download('visits_report.pdf');
    }

    public function generateVisitsReportByIdCSV($bookId)
    {
        $visit = Appointment::with(['doctor.specialty', 'representative.company'])
            ->findOrFail($bookId);

        $randomNumber = rand(1000, 9999);
        $fileName = 'visit_' . $randomNumber . '.csv';

        return Excel::download(new VisitsReportExportForDashboard(collect([$visit])), $fileName, \Maatwebsite\Excel\Excel::CSV);
    }

    public function generateVisitReportByIdPDF($bookId)
    {
        $data = Appointment::with(['doctor.specialty', 'representative.company'])
            ->findOrFail($bookId);
        $randomNumber = rand(1000, 9999);
        $pdf = Pdf::loadView('reports.visits_dash', ['data' => $data]);
        return $pdf->download('visits_report_'. $randomNumber .'.pdf');
    }
}
