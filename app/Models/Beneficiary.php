<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Beneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'country_name',
        'mobile_name',
        'phone_number',
        'beneficiary_name', 
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
