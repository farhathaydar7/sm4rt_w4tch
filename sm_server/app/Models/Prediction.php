<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
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
        'prediction_date',
        'prediction_type',
        'predicted_value',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'prediction_date' => 'date',
        'predicted_value' => 'decimal:2',
    ];

    /**
     * Valid prediction types
     *
     * @var array<string>
     */
    public const PREDICTION_TYPES = [
        'daily_goal',
        'weekly_goal',
        'anomaly',
        'trend',
        'insight'
    ];

    /**
     * Get the user that owns the prediction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the CSV upload that this prediction belongs to
     */
    public function csvUpload(): BelongsTo
    {
        return $this->belongsTo(CsvUpload::class);
    }
} 