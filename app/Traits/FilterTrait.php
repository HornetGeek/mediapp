<?php

namespace App\Traits;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Kid;
use App\Models\Package;
use App\Models\PermanentSubscription;
use App\Models\Plan;
use App\Models\Salary;
use App\Models\Teacher;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

trait FilterTrait
{


    public function filterByStatus($model, $search)
    {
        $output = '';
        $data = $model::where('status', $search)
            ->get();
        $packages = Package::all();

        if ($data->count() > 0) {

            $output .= View::make('components.companies.index', ['data' => $data, 'packages' => $packages])->render();
        
        } else {
            // No data found response
            $output = '<tr><td colspan="4">No Data Found</td></tr>';
        }

        return $output;
    }

}
