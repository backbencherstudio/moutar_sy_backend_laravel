<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class ExchangeRate extends Model
{
    use HasFactory;

     protected $fillable = [
        'from_country',
        'from_country_flag',
        'from_currency',
        'to_country',
        'to_country_flag',
        'to_currency',
        'customer_rate',
        'fixed_fee',
        'status',
    ];


    protected $casts = [
        'customer_rate' => 'float',
        'fixed_fee' => 'float',
        'percentage_fee' => 'float',
        'status' => 'boolean',
    ];
}
