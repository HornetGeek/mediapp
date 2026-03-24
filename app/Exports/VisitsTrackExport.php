<?php

namespace App\Exports;

use App\Models\Appointment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\Exportable;

class VisitsTrackExport implements FromCollection, WithHeadings
{
    use Exportable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->data->map(function ($appointment) {
            return [
                'ID' => $appointment->id,
                'Doctor Name' => optional($appointment->doctor)->name,
                'Reps Name' => optional($appointment->representative)->name,
                'Date' => $appointment->date->format('Y-m-d'),
                'Start Time' => $appointment->start_time->format('H:i'),
                'End Time' => $appointment->end_time->format('H:i'),
                'Status' => $appointment->status,
                'Appointment Code' => $appointment->appointment_code,
                'Company Name' => optional($appointment->company)->name,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Doctor Name',
            'Reps Name',
            'Date',
            'Start Time',
            'End Time',
            'Status',
            'appointment_code',
            'Company Name'
        ];
    }
}
