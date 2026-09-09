<?php
namespace App\Saas;

use Illuminate\Queue\QueueManager;

final class TenantQueueManager extends QueueManager
{
    public function __construct(QueueManager $original)
    {
        parent::__construct($original->app);
        $this->connectors = $original->connectors;
        $this->connections = $original->connections;
    }

    public function resetConnections(): void
    {
        $this->connections = [];
    }
}
