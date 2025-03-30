<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityMetric extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'csv_upload_id',
        'activity_date',
        'steps',
        'distance',
        'active_minutes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activity_date' => 'date',
        'steps' => 'integer',
        'distance' => 'decimal:2',
        'active_minutes' => 'integer',
    ];

    /**
     * Get the user that owns the activity metric
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the CSV upload that this metric belongs to
     */
    public function csvUpload(): BelongsTo
    {
        return $this->belongsTo(CsvUpload::class);
    }
} 