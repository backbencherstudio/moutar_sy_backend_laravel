<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Beneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone_number',
        'country_code',
        'city',
        'transfer_type',
        'bank_or_wallet_name',
        'account_or_wallet_number',
        'branch_name',
        'routing_number',
        'swift_code',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
