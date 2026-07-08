<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKyc extends Model
{
    use HasFactory;

    protected $table = 'user_kycs';

    /**
     * Mass assignable attributes.
     * Schema-এর কলামের নামের সাথে হুবহু মিল রাখা হয়েছে।
     */
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

        // Didit Integration Fields (Schema Matching)
        'didit_session_id',
        'didit_verification_id',
        'didit_workflow_id',
        'didit_attempt_id',

        'didit_response',
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
        'last_attempt_at' => 'datetime',
        'verified_at' => 'datetime',
        'didit_webhook_received_at' => 'datetime',
    ];

    

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
