<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Representative;
use Illuminate\Support\Facades\Schema;

class CompanyPayload
{
    public static function forRepresentative(Representative $representative): ?array
    {
        $relations = ['company'];
        if (Schema::hasTable('rep_company_catalogs')) {
            $relations[] = 'companyCatalog';
        }
        $representative->loadMissing($relations);

        if ($representative->company) {
            return [
                'type' => 'managed',
                'id' => $representative->company->id,
                'name' => $representative->company->name,
            ];
        }

        if ($representative->companyCatalog) {
            return [
                'type' => 'catalog',
                'id' => $representative->companyCatalog->id,
                'name' => $representative->companyCatalog->name,
            ];
        }

        if ($representative->requested_company_name) {
            return [
                'type' => 'requested',
                'id' => null,
                'name' => $representative->requested_company_name,
            ];
        }

        return null;
    }

    public static function forAppointment(Appointment $appointment): ?array
    {
        $relations = ['company'];
        if (Schema::hasTable('rep_company_catalogs')) {
            $relations[] = 'companyCatalog';
        }
        $appointment->loadMissing($relations);

        if ($appointment->company) {
            return [
                'type' => 'managed',
                'id' => $appointment->company->id,
                'name' => $appointment->company->name,
            ];
        }

        if ($appointment->companyCatalog) {
            return [
                'type' => 'catalog',
                'id' => $appointment->companyCatalog->id,
                'name' => $appointment->companyCatalog->name,
            ];
        }

        return null;
    }
}
