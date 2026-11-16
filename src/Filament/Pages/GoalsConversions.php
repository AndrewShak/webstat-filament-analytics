<?php

declare(strict_types=1);

namespace Webstat\Analytics\Filament\Pages;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Webstat\Analytics\Filament\AnalyticsAccess;
use Webstat\Analytics\Support\Locale;
use Webstat\Analytics\Filament\AnalyticsCluster;
use Webstat\Analytics\Filament\Concerns\HasAnalyticsFilters;
use Webstat\Analytics\Models\Goal;
use Webstat\Analytics\Reports\AnalyticsReports;

final class GoalsConversions extends Page implements HasTable
{
    use HasAnalyticsFilters;
    use InteractsWithTable;

    protected static ?string $cluster = AnalyticsCluster::class;

    protected static ?string $slug = 'goals';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    public static function getNavigationLabel(): string
    {
        return Locale::t('tabs.goals');
    }

    public function getTitle(): string|Htmlable
    {
        return Locale::t('tabs.goals');
    }

    public function getView(): string
    {
        return 'analytics::filament.pages.goals';
    }

    public static function canAccess(): bool
    {
        return AnalyticsAccess::allowed();
    }

    public function getViewData(): array
    {
        $r = app(AnalyticsReports::class);
        $f = $this->analyticsFilters();

        return ['filters' => $f, 'goals' => $r->goals($f), 'funnel' => $r->funnel($f)];
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private function goalSchema(): array
    {
        return [
            TextInput::make('name')->label(Locale::t('goal.name'))->required()->maxLength(120),
            Select::make('type')->label(Locale::t('goal.type'))->required()->live()
                ->options(collect(Goal::TYPES)->mapWithKeys(fn (string $t): array => [$t => __("analytics::analytics.goal.types.{$t}")])->all()),
            TextInput::make('match')->label(Locale::t('goal.match'))->required()->maxLength(255)
                ->helperText(fn (Get $get): string => __('analytics::analytics.goal.help.' . ($get('type') ?: 'path'))),
            TextInput::make('funnel_step')->label(Locale::t('goal.step'))->numeric()->minValue(1)->maxValue(20)
                ->helperText(Locale::t('goal.step_help')),
            Toggle::make('is_active')->label(Locale::t('goal.active'))->default(true),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Goal::query())
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(Locale::t('goal.name'))->weight('medium'),
                TextColumn::make('type')->label(Locale::t('goal.type'))->badge()
                    ->formatStateUsing(fn (string $state): string => __("analytics::analytics.goal.types.{$state}")),
                TextColumn::make('match')->label(Locale::t('goal.match'))->fontFamily('mono')->limit(48),
                TextColumn::make('funnel_step')->label(Locale::t('goal.step'))->placeholder('—'),
                ToggleColumn::make('is_active')->label(Locale::t('goal.active')),
            ])
            ->headerActions([
                CreateAction::make()->model(Goal::class)->schema($this->goalSchema())->createAnother(false)
                    ->label(Locale::t('goal.create')),
            ])
            ->recordActions([
                EditAction::make()->schema($this->goalSchema()),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(Locale::t('goal.empty'))
            ->emptyStateDescription(Locale::t('goal.empty_help'));
    }
}
