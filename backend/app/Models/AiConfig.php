<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConfig extends Model
{
    use HasFactory;

    protected $table = 'ai_configs';

    protected $fillable = [
        'migration_project_id',
        'provider',
        'api_key_encrypted',
        'model',
        'temperature',
        'max_tokens',
        'system_prompt',
        'is_active',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_NVIDIA = 'nvidia';
    const PROVIDER_GLM = 'glm';
    const PROVIDER_MINIMAX = 'minimax';
    const PROVIDER_KIMI = 'kimi';
    const PROVIDER_CUSTOM = 'custom';

    const PROVIDERS = [
        self::PROVIDER_OPENAI => 'OpenAI',
        self::PROVIDER_NVIDIA => 'NVIDIA NIM',
        self::PROVIDER_GLM => 'GLM',
        self::PROVIDER_MINIMAX => 'MiniMax',
        self::PROVIDER_KIMI => 'Kimi',
        self::PROVIDER_CUSTOM => 'Custom',
    ];

    const DEFAULT_MODELS = [
        self::PROVIDER_OPENAI => 'gpt-4o',
        self::PROVIDER_NVIDIA => 'nvidia/nemotron-4-340b-instruct',
        self::PROVIDER_GLM => 'glm-4',
        self::PROVIDER_MINIMAX => 'abab6.5-chat',
        self::PROVIDER_KIMI => 'moonshot-v1-128k',
        self::PROVIDER_CUSTOM => '',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(MigrationProject::class, 'migration_project_id');
    }

    public function getProviderLabelAttribute(): string
    {
        return self::PROVIDERS[$this->provider] ?? $this->provider;
    }

    public function getDefaultModelAttribute(): string
    {
        return self::DEFAULT_MODELS[$this->provider] ?? '';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
