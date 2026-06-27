<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationRecord extends Model
{
    use HasFactory;

    protected $table = 'migration_records';

    protected $fillable = [
        'import_id',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'action',
        'old_values',
        'new_values',
        'validation_errors',
        'ai_suggestions',
        'status',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'validation_errors' => 'array',
        'ai_suggestions' => 'array',
    ];

    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_SKIP = 'skip';
    const ACTION_ERROR = 'error';

    const STATUS_PENDING = 'pending';
    const STATUS_IMPORTED = 'imported';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_FAILED = 'failed';
    const STATUS_ROLLED_BACK = 'rolled_back';

    public function import(): BelongsTo
    {
        return $this->belongsTo(MigrationImport::class, 'import_id');
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByTable($query, string $tableName)
    {
        return $query->where('source_table', $tableName);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeImported($query)
    {
        return $query->where('status', self::STATUS_IMPORTED);
    }

    public function hasValidationErrors(): bool
    {
        return ! empty($this->validation_errors) && count($this->validation_errors) > 0;
    }

    public function hasAiSuggestions(): bool
    {
        return ! empty($this->ai_suggestions) && count($this->ai_suggestions) > 0;
    }
}
