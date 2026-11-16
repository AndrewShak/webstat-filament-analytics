<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

use Illuminate\Http\Request;

final class NullDemographicsEnricher implements DemographicsEnricher
{
    public function enrich(Request $request, array $session): array
    {
        return [];
    }
}
