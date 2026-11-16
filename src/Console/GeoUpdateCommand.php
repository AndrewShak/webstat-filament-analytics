<?php

declare(strict_types=1);

namespace Webstat\Analytics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Webstat\Analytics\Ingest\Geo\MaxMindGeoResolver;

/** Downloads the free DB-IP "Lite" database (CC BY 4.0, no account needed) used for offline country/city lookup. */
final class GeoUpdateCommand extends Command
{
    protected $signature = 'analytics:geo-update
        {--type=city : city (country + city, ~130 MB) or country (country only, small)}
        {--url= : custom .mmdb or .mmdb.gz URL (e.g. your own MaxMind mirror)}';

    protected $description = 'Download the offline IP geolocation database (DB-IP Lite, .mmdb)';

    public function handle(): int
    {
        $type = $this->option('type') === 'country' ? 'country' : 'city';
        $target = MaxMindGeoResolver::databasePath();
        @mkdir(dirname($target), 0775, true);

        $urls = $this->option('url') ? [(string) $this->option('url')] : [
            sprintf('https://download.db-ip.com/free/dbip-%s-lite-%s.mmdb.gz', $type, now()->format('Y-m')),
            sprintf('https://download.db-ip.com/free/dbip-%s-lite-%s.mmdb.gz', $type, now()->subMonthNoOverflow()->format('Y-m')),
        ];

        foreach ($urls as $url) {
            $this->components->info("Downloading {$url}");
            $tmp = $target . '.download';
            try {
                $res = Http::timeout(600)->sink($tmp)->get($url);
            } catch (\Throwable $e) {
                $this->components->warn($e->getMessage());
                @unlink($tmp);

                continue;
            }
            if (! $res->ok() || ! is_file($tmp) || filesize($tmp) < 1024) {
                @unlink($tmp);

                continue;
            }

            if (str_ends_with($url, '.gz')) {
                if (! $this->gunzip($tmp, $target . '.new')) {
                    @unlink($tmp);
                    $this->components->error('Could not decompress the download.');

                    return self::FAILURE;
                }
                @unlink($tmp);
            } else {
                rename($tmp, $target . '.new');
            }
            rename($target . '.new', $target);
            $this->components->info("Geo database installed: {$target}");
            $this->line('IP geolocation by DB-IP.com (CC BY 4.0) — keep this attribution on your site if you publish statistics.');

            return self::SUCCESS;
        }

        $this->components->error('Download failed (check outbound network access or pass --url).');

        return self::FAILURE;
    }

    private function gunzip(string $from, string $to): bool
    {
        $in = @gzopen($from, 'rb');
        $out = @fopen($to, 'wb');
        if (! $in || ! $out) {
            return false;
        }
        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1 << 20));
        }
        gzclose($in);
        fclose($out);

        return filesize($to) > 1024;
    }
}
