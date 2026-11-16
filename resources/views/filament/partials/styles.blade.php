@once
<style>
    .wa-root { display: flex; flex-direction: column; gap: 1.25rem; }
    .wa-grid { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); }
    .wa-stats { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); }
    .wa-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .wa-table th { text-align: left; font-weight: 500; opacity: .65; padding: .35rem .5rem; }
    .wa-table td { padding: .4rem .5rem; border-top: 1px solid color-mix(in srgb, currentColor 10%, transparent); }
    .wa-table td.num, .wa-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .wa-sub { font-size: .8rem; opacity: .65; margin: 0 0 .5rem; }
</style>
@endonce
