<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

use Illuminate\Http\Request;

/**
 * Extension point for age/gender estimation from an external provider (placeholder by default).
 * Bind your implementation via config analytics.demographics.enricher.
 */
interface DemographicsEnricher
{
    /**
     * @param  array<string, mixed>  $session  session row being created (no IP is available here beyond $request)
     * @return array{age_group?: ?string, gender?: ?string}  age_group e.g. 18-24|25-34|35-44|45-54|55+; gender male|female
     */
    public function enrich(Request $request, array $session): array;
}
