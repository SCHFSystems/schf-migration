<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MigrationApiKey extends Model
{
    use HasFactory;

    protected $table = 'migration_api_keys';

    protected $fillable = [
        'name',
        'key',
        'migration_project_id',
        'permissions',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(MigrationProject::class, 'migration_project_id');
    }

    public static function generateKey(): string
    {
        return 'schf-mig-' . Str::random(32);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return true;
        }

        return in_array($permission, $this->permissions) || in_array('*', $this->permissions);
    }
}
