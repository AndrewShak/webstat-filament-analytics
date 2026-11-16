<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Buffer;

use Webstat\Analytics\Ingest\Writer;

/** Writes after the response has been sent (terminating callback) — no added request latency. */
final class DirectBuffer implements EventBuffer
{
    public function __construct(private readonly Writer $writer) {}

    public function push(array $entry): void
    {
        app()->terminating(function () use ($entry): void {
            try {
                $this->writer->write([$entry]);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
