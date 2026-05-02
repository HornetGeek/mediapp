<?php

namespace App\Imports;

use App\Models\RepCompanyCatalog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class RepCompanyCatalogImport implements ToCollection
{
    private int $imported = 0;

    private int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $name = trim((string) ($row[0] ?? ''));

            if ($index === 0 && RepCompanyCatalog::normalizeName($name) === 'COMPANIES') {
                continue;
            }

            if ($name === '') {
                $this->skipped++;
                continue;
            }

            RepCompanyCatalog::updateOrCreate(
                ['normalized_name' => RepCompanyCatalog::normalizeName($name)],
                [
                    'name' => $name,
                    'rank' => $index,
                    'status' => 'active',
                ]
            );

            $this->imported++;
        }
    }

    public function imported(): int
    {
        return $this->imported;
    }

    public function skipped(): int
    {
        return $this->skipped;
    }
}
