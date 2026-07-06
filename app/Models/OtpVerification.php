<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'name',
        'email',
        'user_id',
        'phone',
        'image',
        'otp',
        'expires_at',
    ];
}
