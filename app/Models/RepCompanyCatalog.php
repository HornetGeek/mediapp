<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepCompanyCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'rank',
        'status',
    ];

    public static function normalizeName(string $name): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name));

        return function_exists('mb_strtoupper')
            ? mb_strtoupper((string) $normalized)
            : strtoupper((string) $normalized);
    }

    public function representatives()
    {
        return $this->hasMany(Representative::class, 'company_catalog_id');
    }
}
