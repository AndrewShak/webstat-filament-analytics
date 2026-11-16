<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Webstat\Analytics\Ingest\ClientIp;

function wa_request(string $peer, array $headers = []): Request
{
    $server = ['REMOTE_ADDR' => $peer];
    foreach ($headers as $k => $v) {
        $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
    }

    return Request::create('/', 'POST', server: $server);
}

it('uses the TCP peer when it is public and ignores spoofable headers', function (): void {
    expect(ClientIp::resolve(wa_request('93.184.216.34', ['X-Forwarded-For' => '1.2.3.4'])))->toBe('93.184.216.34');
});

it('reads forwarding headers when the peer is a private proxy/Docker address', function (): void {
    expect(ClientIp::resolve(wa_request('172.18.0.2', ['X-Forwarded-For' => '10.0.0.1, 8.8.4.4'])))->toBe('8.8.4.4')
        ->and(ClientIp::resolve(wa_request('127.0.0.1', ['CF-Connecting-IP' => '1.1.1.1', 'X-Forwarded-For' => '8.8.8.8'])))->toBe('1.1.1.1');
});

it('falls back to the private peer when nothing better exists', function (): void {
    expect(ClientIp::resolve(wa_request('192.168.1.5')))->toBe('192.168.1.5');
});

it('applies the storage mode', function (): void {
    expect(ClientIp::forStorage('8.8.4.4', 'full'))->toBe('8.8.4.4')
        ->and(ClientIp::forStorage('8.8.4.4', 'anonymized'))->toBe('8.8.4.0')
        ->and(ClientIp::forStorage('2001:db8:1:2:3:4:5:6', 'anonymized'))->toBe('2001:db8:1::')
        ->and(ClientIp::forStorage('8.8.4.4', 'none'))->toBeNull()
        ->and(ClientIp::forStorage(null, 'full'))->toBeNull();
});
