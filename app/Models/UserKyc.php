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
        'country',
        'document_type',
        'front_image',
        'back_image',
        'nid_number',
        'name',
        'father_name',
        'mother_name',
        'date_of_birth',
        'address',
        'status',
    ];

    /**
     * Get the user that owns the KYC document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
