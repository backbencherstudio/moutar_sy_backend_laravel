<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MobileMoneyProvider extends Model
{
     use HasFactory;

    protected $fillable = [
        'country_name',
        'name',
        'status',
    ];
}
