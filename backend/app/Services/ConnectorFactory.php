<?php

declare(strict_types=1);

namespace App\Services;

use SCHF\SDK\Connector\ConnectorInterface;
use SCHF\SDK\Connector\Drivers\FirebirdDriver;

class ConnectorFactory
{
    /**
     * @param  string $sourceType  'firebird', 'mysql', 'postgresql', 'sqlserver', 'oracle', 'sqlite'
     * @param  array  $config      Connection parameters
     * @return ConnectorInterface
     * @throws \InvalidArgumentException
     */
    public function make(string $sourceType, array $config): ConnectorInterface
    {
        return match ($sourceType) {
            'firebird'  => $this->makeFirebird($config),
            // 'mysql'      => new MysqlConnector(...),
            // 'postgresql' => new PostgresqlConnector(...),
            default     => throw new \InvalidArgumentException("No connector available for source type: {$sourceType}"),
        };
    }

    private function makeFirebird(array $config): FirebirdDriver
    {
        $connector = new FirebirdDriver();
        $connector->connect($config);
        return $connector;
    }
}
