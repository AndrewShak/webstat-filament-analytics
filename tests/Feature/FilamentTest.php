<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Webstat\Analytics\Filament\AnalyticsCluster;
use Webstat\Analytics\Filament\Pages\Audience;
use Webstat\Analytics\Filament\Pages\GoalsConversions;
use Webstat\Analytics\Filament\Pages\MapsForms;
use Webstat\Analytics\Filament\Pages\SourcesBehavior;
use Webstat\Analytics\Models\Goal;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('registers the cluster and all four pages through discoverClusters()', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getClusters())->toContain(AnalyticsCluster::class)
        ->and($panel->getPages())->toContain(Audience::class, SourcesBehavior::class, MapsForms::class, GoalsConversions::class);
});

it('serves every analytics tab to an authorised user', function (string $slug): void {
    $this->actingAs($this->admin())->get("/admin/analytics/{$slug}")->assertOk();
})->with(['audience', 'sources', 'maps', 'goals']);

it('redirects guests to login and honours the gate ability', function (): void {
    $this->get('/admin/analytics/audience')->assertRedirect();

    $user = $this->admin();
    config(['analytics.filament.ability' => 'view-analytics']);
    $this->actingAs($user)->get('/admin/analytics/audience')->assertForbidden();

    Illuminate\Support\Facades\Gate::define('view-analytics', fn () => true);
    $this->actingAs($user)->get('/admin/analytics/audience')->assertOk();
});

it('renders the dashboard in Russian and English', function (): void {
    $user = $this->admin();

    $this->withSession(['analytics.lang' => 'ru'])->actingAs($user)->get('/admin/analytics/audience')
        ->assertOk()->assertSee('Аудитория')->assertSee('Последние визиты');

    $this->withSession(['analytics.lang' => 'en'])->actingAs($user)->get('/admin/analytics/audience')
        ->assertOk()->assertSee('Audience')->assertSee('Latest visits');
});

it('switches the language from the dashboard', function (): void {
    $user = $this->admin();

    $this->actingAs($user)
        ->get('/admin/analytics/audience?lang=ru')
        ->assertOk()
        ->assertSee('Аудитория');

    expect(session('analytics.lang'))->toBe('ru');

    $this->withSession(['analytics.lang' => 'ru'])
        ->actingAs($user)
        ->get('/admin/analytics/audience?lang=xx')
        ->assertOk()
        ->assertSee('Аудитория');

    expect(session('analytics.lang'))->toBe('ru');
});

it('keeps filters in Livewire state and the session', function (): void {
    Livewire::actingAs($this->admin())->test(Audience::class)
        ->set('range', 'week')->assertSet('range', 'week')
        ->set('audience', 'all')->set('group', 'day')->set('live', true)
        ->assertOk()->assertSee('wire:poll', false);

    expect(session('analytics.filters'))->toMatchArray(['range' => 'week', 'audience' => 'all', 'group' => 'day']);
});

it('shows collected data on the dashboard', function (): void {
    wa_seed_session(['ip' => '8.8.4.4', 'country' => 'NL', 'city' => 'Amsterdam', 'device_brand' => 'Apple', 'device_model' => 'iPhone', 'device' => 'mobile', 'os' => 'iOS', 'browser' => 'Safari'], [['title' => 'Home']]);

    Livewire::actingAs($this->admin())->test(Audience::class)
        ->assertSee('8.8.4.4')->assertSee('Amsterdam')->assertSee('Apple iPhone');
});

it('lets the user manage goals from the table', function (): void {
    Goal::create(['name' => 'Signup', 'type' => 'event', 'match' => 'signup']);

    Livewire::actingAs($this->admin())->test(GoalsConversions::class)->assertSee('Signup')->assertOk();
});
