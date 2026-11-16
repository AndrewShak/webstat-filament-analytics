<?php

declare(strict_types=1);

namespace Webstat\Analytics\Filament;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

final class AnalyticsAccess
{
    public static function allowed(): bool
    {
        $panels = (array) config('analytics.filament.panels', ['*']);
        if (! in_array('*', $panels, true)) {
            $id = Filament::getCurrentPanel()?->getId();
            if ($id === null || ! in_array($id, $panels, true)) {
                return false;
            }
        }

        $ability = config('analytics.filament.ability');

        return $ability === null || Gate::allows((string) $ability);
    }
}
