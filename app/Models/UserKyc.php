<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKyc extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',

        'name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'address',

        'country',
        'father_name',
        'mother_name',

        'document_type',
        'document_number',
        'nid_number',
        'passport_number',
        'document_expiry_date',

        'front_image',
        'back_image',

        'status',
        'rejection_reason',

        // --- Didit integration matching DB columns ---
        'didit_user_id',            // 'didit_session_id' এর জায়গায়
        'didit_verification_id',
        'didit_workflow_id',
        'didit_attemp_id',          // DB-তে স্পেলিং 'attemp' থাকায় এটি দিন
        'didit_initiate_payload',   // 'didit_response' এর জায়গায়

        'didit_webhook_payload',
        'didit_verification_data',

        'verified_at',
        'last_attempt_at',
        'didit_webhook_received_at',

        'attempt_count',
    ];

    protected $casts = [

        'didit_response' => 'array',
        'didit_webhook_payload' => 'array',
        'didit_verification_data' => 'array',

        'verified_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'didit_webhook_received_at' => 'datetime',

        'date_of_birth' => 'date',
        'document_expiry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
