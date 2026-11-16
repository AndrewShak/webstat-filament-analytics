<?php

declare(strict_types=1);

namespace Webstat\Analytics\Console;

use Illuminate\Console\Command;
use Webstat\Analytics\Ingest\Buffer\RedisBuffer;
use Webstat\Analytics\Ingest\Writer;

final class FlushCommand extends Command
{
    protected $signature = 'analytics:flush {--batch= : entries per DB transaction}';

    protected $description = 'Drain the Redis analytics buffer into the database in bulk';

    public function handle(RedisBuffer $buffer, Writer $writer): int
    {
        $batch = (int) ($this->option('batch') ?: config('analytics.buffer.flush_batch', 2000));
        $total = 0;

        // Bounded loop so one run never starves the scheduler.
        for ($i = 0; $i < 50; $i++) {
            $entries = $buffer->drain($batch);
            if ($entries === []) {
                break;
            }
            try {
                $writer->write($entries);
            } catch (\Throwable $e) {
                foreach ($entries as $entry) { // put back on failure (best effort)
                    $buffer->push($entry);
                }
                report($e);

                return self::FAILURE;
            }
            $total += count($entries);
        }

        $this->components->info("Flushed {$total} payload(s).");

        return self::SUCCESS;
    }
}
