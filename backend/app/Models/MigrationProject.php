<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MigrationProject extends Model
{
    use HasFactory;

    protected $table = 'migration_projects';

    protected $fillable = [
        'name',
        'description',
        'source_type',
        'source_config',
        'status',
        'ai_config',
        'api_key_id',
        'data_cutoff_date',
        'organization_id',
        'created_by',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'source_config' => 'array',
        'ai_config' => 'array',
        'data_cutoff_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [];

    const STATUS_DRAFT = 'draft';
    const STATUS_PREPARING = 'preparing';
    const STATUS_VALIDATING = 'validating';
    const STATUS_PREVIEWING = 'previewing';
    const STATUS_MIGRATING = 'migrating';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_ROLLED_BACK = 'rolled_back';

    const SOURCE_TYPES = [
        'firebird',
        'mysql',
        'sql_server',
        'postgres',
        'oracle',
        'zip',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(MigrationImport::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MigrationReport::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(MigrationApiKey::class);
    }

    public function previews(): HasMany
    {
        return $this->hasMany(MigrationPreview::class);
    }

    public function latestPreview(): HasOne
    {
        return $this->hasOne(MigrationPreview::class)->latestOfMany();
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(MigrationReport::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PREPARING,
            self::STATUS_VALIDATING,
            self::STATUS_PREVIEWING,
            self::STATUS_MIGRATING,
        ]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_FAILED,
            self::STATUS_ROLLED_BACK,
        ]);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PREPARING => 'blue',
            self::STATUS_VALIDATING => 'yellow',
            self::STATUS_PREVIEWING => 'indigo',
            self::STATUS_MIGRATING => 'purple',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_ROLLED_BACK => 'orange',
            default => 'gray',
        };
    }

    public function getProgressAttribute(): ?int
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 100;
        }

        if ($this->status === self::STATUS_DRAFT) {
            return 0;
        }

        $totalRecords = $this->imports()->sum('records_total');
        $importedRecords = $this->imports()->sum('records_imported');

        if ($totalRecords === 0) {
            return null;
        }

        return (int) round(($importedRecords / $totalRecords) * 100);
    }
}
