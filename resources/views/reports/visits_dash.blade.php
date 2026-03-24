<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Visits Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <h2 style="text-align: center;">Visits Report</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Doctor Name</th>
                <th>Reps Name</th>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Status</th>
                <th>Appointment Code</th>
                <th>Company Name</th>
            </tr>
        </thead>
        <tbody>
            
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ optional($data->doctor)->name }}</td>
                <td>{{ optional($data->representative)->name }}</td>
                <td>{{ $data->date }}</td>
                <td>{{ $data->start_time }}</td>
                <td>{{ $data->end_time }}</td>
                <td>{{ $data->status }}</td>
                <td>{{ $data->appointment_code }}</td>
                <td>{{ optional($data->company)->name }}</td>
            </tr>
            
        </tbody>
    </table>
</body>
</html>
