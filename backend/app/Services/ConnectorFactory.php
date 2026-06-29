<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Synthetic\SyntheticConnector;
use App\Services\Synthetic\SyntheticSourceService;
use SCHF\SDK\Connector\ConnectorInterface;

class ConnectorFactory
{
    public function __construct(
        private ?SyntheticSourceService $syntheticSourceService = null,
    ) {}

    /**
     * @param  string $sourceType  'synthetic', 'firebird', 'mysql', 'postgresql', 'sqlserver', 'oracle', 'sqlite'
     * @param  array  $config      Connection parameters
     * @return ConnectorInterface
     * @throws \InvalidArgumentException
     */
    public function make(string $sourceType, array $config): ConnectorInterface
    {
        $realSourceTypes = ['firebird', 'mysql', 'postgresql', 'sqlserver', 'oracle', 'sqlite'];

        if (in_array($sourceType, $realSourceTypes, true) && $this->syntheticOnly()) {
            throw new \InvalidArgumentException('Real connectors are disabled in synthetic-only mode');
        }

        return match ($sourceType) {
            'synthetic' => $this->makeSynthetic($config),
            'firebird'  => $this->realConnectorUnavailable($sourceType),
            'mysql', 'postgresql', 'sqlserver', 'oracle', 'sqlite' => $this->realConnectorUnavailable($sourceType),
            default     => throw new \InvalidArgumentException("No connector available for source type: {$sourceType}"),
        };
    }

    private function makeSynthetic(array $config): SyntheticConnector
    {
        return ($this->syntheticSourceService ?? new SyntheticSourceService())->connector($config);
    }

    private function realConnectorUnavailable(string $sourceType): never
    {
        throw new \InvalidArgumentException("No connector available for source type: {$sourceType}");
    }

    private function syntheticOnly(): bool
    {
        return filter_var(env('MIGRATION_SYNTHETIC_ONLY', true), FILTER_VALIDATE_BOOLEAN);
    }
}
