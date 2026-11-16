@props(['nonce' => null, 'defer' => false])

@php
    $script = app(\Webstat\Analytics\Support\TrackerScript::class);
    $nonce ??= class_exists(\Illuminate\Support\Facades\Vite::class)
        ? \Illuminate\Support\Facades\Vite::cspNonce()
        : null;
@endphp

@if (config('analytics.enabled'))
    @if ($defer)
        <script @if($nonce) nonce="{{ $nonce }}" @endif>
            (() => {
                const config = {!! json_encode($script->config(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
                const source = {!! json_encode($script->source(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
                const nonce = document.currentScript?.nonce || '';

                function hasConsent() {
                    try {
                        const consent = JSON.parse(localStorage.getItem('vertexa-cookie-consent-v1') || 'null');
                        return consent?.version === 1 && consent?.analytics === true;
                    } catch {
                        return false;
                    }
                }

                function start() {
                    if (window.__WA_STARTED__ || window.__WA_DISABLED__ || !hasConsent()) return;

                    window.__WA_STARTED__ = true;

                    const script = document.createElement('script');
                    if (nonce) script.nonce = nonce;
                    script.textContent = 'window.__WA__=' + JSON.stringify(config) + ';' + source;
                    (document.head || document.documentElement).appendChild(script);
                }

                if (hasConsent()) start();

                window.addEventListener('vertexa:analytics-consent', (event) => {
                    if (event.detail?.allowed === true) {
                        start();
                    } else if (event.detail?.allowed === false) {
                        window.__WA_DISABLED__ = true;
                    }
                });
            })();
        </script>
    @else
        <script @if($nonce) nonce="{{ $nonce }}" @endif>
            window.__WA__={!! json_encode($script->config(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!};
            {!! $script->source() !!}
        </script>
    @endif
@endif
