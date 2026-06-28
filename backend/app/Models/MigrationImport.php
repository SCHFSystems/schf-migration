<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationImport extends Model
{
    use HasFactory;

    protected $table = 'migration_imports';

    protected $fillable = [
        'migration_project_id',
        'batch_number',
        'table_name',
        'records_total',
        'records_imported',
        'records_skipped',
        'records_failed',
        'status',
        'started_at',
        'completed_at',
        'error_log',
        'checksum',
    ];

    protected $casts = [
        'records_total' => 'integer',
        'records_imported' => 'integer',
        'records_skipped' => 'integer',
        'records_failed' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_log' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public function project(): BelongsTo
    {
        return $this->belongsTo(MigrationProject::class, 'migration_project_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(MigrationRecord::class, 'import_id');
    }

    public function getDurationAttribute(): ?float
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->records_total === 0) {
            return 0.0;
        }

        return round(($this->records_imported / $this->records_total) * 100, 2);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
