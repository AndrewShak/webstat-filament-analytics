<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Buffer;

interface EventBuffer
{
    /** @param array{s: array<string, mixed>, e: list<array<string, mixed>>} $entry */
    public function push(array $entry): void;
}
