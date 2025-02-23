<?php

declare(strict_types=1);

namespace Flow\ETL\Async\Socket\Worker;

use Flow\ETL\Async\Socket\Communication\Message;
use Flow\ETL\Async\Socket\Communication\Protocol;
use Flow\ETL\Cache;
use Flow\ETL\Cache\InMemoryCache;
use Flow\ETL\Partition\NoopFilter;
use Flow\ETL\Pipeline\Pipes;
use Flow\ETL\Rows;

final class ClientProtocol
{
    private Cache $cache;

    private string $cacheId;

    public function __construct(private readonly Processor $processor)
    {
        $this->cache = new InMemoryCache();
        $this->cacheId = \uniqid('flow_async_pipeline', true);
    }

    public function handle(string $id, Message $message, Server $server) : void
    {
        $payload = $message->payload();

        switch ($message->type()) {
            case Protocol::SERVER_SETUP:
                $refs = \array_key_exists('partition_entries', $payload) ? $payload['partition_entries']->all() : [];
                $this->processor->setPipes($payload['pipes'] ?? new Pipes([]));
                $this->processor->setPartitionEntries($refs);
                $this->processor->setPartitionFilter($payload['partition_filter'] ?? new NoopFilter());
                $this->cache = $payload['cache'] ?? $this->cache;
                $this->cacheId = $payload['cache_id'] ?? $this->cacheId;

                $server->send(Message::fetch($id));

                break;
            case Protocol::SERVER_PROCESS:
                $rows = $this->processor->process($payload['rows'] ?? new Rows());

                $server->send(Message::fetch($id));
                $this->cache->add($this->cacheId, $rows);

                break;
        }
    }

    public function identify(string $id, Server $server) : void
    {
        $server->send(Message::identify($id));
    }
}
