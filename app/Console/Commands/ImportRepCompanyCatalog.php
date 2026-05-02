<?php

namespace App\Console\Commands;

use App\Imports\RepCompanyCatalogImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportRepCompanyCatalog extends Command
{
    protected $signature = 'rep-companies:import {path : Path to the company Excel file}';

    protected $description = 'Import the representative company catalog from an Excel file';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $import = new RepCompanyCatalogImport();
        Excel::import($import, $path);

        $this->info(sprintf(
            'Imported %d company rows. Skipped %d blank rows.',
            $import->imported(),
            $import->skipped()
        ));

        return self::SUCCESS;
    }
}
