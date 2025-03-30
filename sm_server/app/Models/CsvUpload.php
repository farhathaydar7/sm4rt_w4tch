<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CsvUpload extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'file_path',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'upload_date' => 'datetime',
        'status' => 'string',
    ];

    /**
     * Get the user that owns the CSV upload
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the activity metrics associated with this CSV upload
     */
    public function activityMetrics(): HasMany
    {
        return $this->hasMany(ActivityMetric::class);
    }

    /**
     * Get the predictions associated with this CSV upload
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }
} 