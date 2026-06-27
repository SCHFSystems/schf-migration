<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationReport extends Model
{
    use HasFactory;

    protected $table = 'migration_reports';

    protected $fillable = [
        'migration_project_id',
        'summary',
        'totals',
        'duration_seconds',
        'core_version',
        'migration_version',
        'legacy_version',
        'package_hash',
        'operator_id',
        'backup_path',
        'status',
    ];

    protected $casts = [
        'summary' => 'array',
        'totals' => 'array',
        'duration_seconds' => 'float',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_FINAL = 'final';
    const STATUS_ARCHIVED = 'archived';

    public function project(): BelongsTo
    {
        return $this->belongsTo(MigrationProject::class, 'migration_project_id');
    }

    public function getDurationFormattedAttribute(): string
    {
        if (! $this->duration_seconds) {
            return '0s';
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        }

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        }

        return sprintf('%ds', $seconds);
    }

    public function getTotalRecordsAttribute(): int
    {
        return data_get($this->totals, 'total_records', 0);
    }

    public function getSuccessCountAttribute(): int
    {
        return data_get($this->totals, 'success', 0);
    }

    public function getFailedCountAttribute(): int
    {
        return data_get($this->totals, 'failed', 0);
    }

    public function getSkippedCountAttribute(): int
    {
        return data_get($this->totals, 'skipped', 0);
    }
}
