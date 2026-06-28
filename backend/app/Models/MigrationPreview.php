<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationPreview extends Model
{
    use HasFactory;

    protected $table = 'migration_previews';

    protected $fillable = [
        'project_id',
        'status',
        'ready_for_bundle',
        'summary_json',
        'entities_json',
        'warnings_json',
        'errors_json',
        'ignored_json',
        'historical_json',
        'generated_at',
    ];

    protected $casts = [
        'ready_for_bundle' => 'boolean',
        'summary_json' => 'array',
        'entities_json' => 'array',
        'warnings_json' => 'array',
        'errors_json' => 'array',
        'ignored_json' => 'array',
        'historical_json' => 'array',
        'generated_at' => 'datetime',
    ];

    const STATUS_READY = 'ready';
    const STATUS_BLOCKED = 'blocked';

    public function project(): BelongsTo
    {
        return $this->belongsTo(MigrationProject::class, 'project_id');
    }

    public function isReady(): bool
    {
        return $this->ready_for_bundle === true;
    }

    public function toApiResponse(): array
    {
        return [
            'project_id' => $this->project_id,
            'status' => $this->status,
            'ready_for_bundle' => $this->ready_for_bundle,
            'summary' => $this->summary_json,
            'entities' => $this->entities_json,
            'warnings' => $this->warnings_json,
            'errors' => $this->errors_json,
            'ignored' => $this->ignored_json,
            'historical' => $this->historical_json,
            'generated_at' => $this->generated_at?->toISOString(),
        ];
    }
}
