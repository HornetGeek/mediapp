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
            @foreach($data as $visit)
            <tr>
                <td>{{ $visit->id }}</td>
                <td>{{ optional($visit->doctor)->name }}</td>
                <td>{{ optional($visit->representative)->name }}</td>
                <td>{{ $visit->date }}</td>
                <td>{{ $visit->start_time }}</td>
                <td>{{ $visit->end_time }}</td>
                <td>{{ $visit->status }}</td>
                <td>{{ $visit->appointment_code }}</td>
                <td>{{ optional($visit->company)->name }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
