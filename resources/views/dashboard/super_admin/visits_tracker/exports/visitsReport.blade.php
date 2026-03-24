<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <table>
    <thead>
        <tr>
            <th>Doctor</th>
            <th>Specialization</th>
            <th>Representative</th>
            <th>Company</th>
            <th>Date</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($visits as $visit)
            <tr>
                <td>{{ $visit->doctor->name }}</td>
                <td>{{ $visit->doctor->specialty->name ?? '-' }}</td>
                <td>{{ $visit->representative->name }}</td>
                <td>{{ $visit->representative->company->name }}</td>
                <td>{{ $visit->date }}</td>
                <td>{{ \Carbon\Carbon::parse($visit->start_time)->format('h:i A') }}</td>
                <td>{{ \Carbon\Carbon::parse($visit->end_time)->format('h:i A') }}</td>
                <td>{{ $visit->status }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>