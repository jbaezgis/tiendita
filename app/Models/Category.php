<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'salary_from',
        'salary_to',
        'purchase_limit',
    ];

    protected $casts = [
        'salary_from' => 'decimal:2',
        'salary_to' => 'decimal:2',
        'purchase_limit' => 'decimal:2',
    ];
}
