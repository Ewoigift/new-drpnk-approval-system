<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Add this line

class ApprovalRequest extends Model
{
    use HasFactory;

    // Explicitly define the table name
    protected $table = 'approval_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'estimated_cost',
        'attachment_path',
        'status',
        'current_approver_role',
        'procurement_comments',
        'procurement_approved_at',
        'accountant_comments',
        'accountant_approved_at',
        'coordinator_comments',
        'coordinator_approved_at',
        'chief_officer_comments',
        'chief_officer_approved_at',
        'final_outcome',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'procurement_approved_at' => 'datetime',
        'accountant_approved_at' => 'datetime',
        'coordinator_approved_at' => 'datetime',
        'chief_officer_approved_at' => 'datetime',
    ];

    /**
     * Get the user that owns the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
