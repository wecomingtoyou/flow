<?php

declare(strict_types=1);

namespace Flow\ETL\Adapter\JSON;

use Flow\ETL\Exception\RuntimeException;
use Flow\ETL\Loader;
use Flow\ETL\Rows;
use Ramsey\Uuid\Uuid;

/**
 * @implements Loader<array{path: string, safe_mode: boolean}>
 */
final class JsonLoader implements Loader
{
    /**
     * @var null|resource
     */
    private $stream;

    public function __construct(private string $path, private bool $safeMode = false)
    {
    }

    /**
     * @psalm-suppress InvalidPassByReference
     */
    public function __destruct()
    {
        \fclose($this->stream());
    }

    public function __serialize() : array
    {
        return [
            'path' => $this->path,
            'safe_mode' => $this->safeMode,
        ];
    }

    public function __unserialize(array $data) : void
    {
        $this->path = $data['path'];
        $this->safeMode = $data['safe_mode'];
    }

    /**
     * @psalm-suppress PossiblyNullArgument
     */
    public function load(Rows $rows) : void
    {
        /** @var array{size:int} $stats */
        $stats = \fstat($this->stream());

        $json = ($stats['size'] > 2)
            ? ',' . \json_encode($rows->toArray(), JSON_THROW_ON_ERROR) . ']'
            : \json_encode($rows->toArray(), JSON_THROW_ON_ERROR) . ']';

        \fseek($this->stream(), $stats['size'] - 1);
        \fwrite($this->stream(), $json);
    }

    /**
     * @return resource
     */
    private function stream()
    {
        if ($this->stream === null) {
            $fullPath = ($this->safeMode)
                ? (\rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . Uuid::uuid4()->toString() . '.csv')
                : $this->path;

            if ($this->safeMode) {
                \mkdir(\rtrim($this->path, DIRECTORY_SEPARATOR));
            }

            $stream = \fopen($fullPath, 'w+');

            if ($stream === false) {
                throw new RuntimeException("Unable to open stream for path {$this->path}.");
            }

            \fwrite($stream, '[]');

            $this->stream = $stream;
        }

        return $this->stream;
    }
}
