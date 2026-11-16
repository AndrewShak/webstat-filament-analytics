/*! webstat analytics tracker — first-party, no third-party requests, no field values collected. */
(function (w, d) {
  'use strict';
  var C = w.__WA__;
  if (!C || w.__wa_loaded || w.self !== w.top) return;
  var n = w.navigator;
  if (C.dnt && (n.doNotTrack === '1' || w.doNotTrack === '1' || n.globalPrivacyControl === true)) return;
  w.__wa_loaded = 1;

  var now = Date.now;
  function rnd() {
    var a = new Uint8Array(16), s = '', i;
    (w.crypto || w.msCrypto).getRandomValues(a);
    for (i = 0; i < 16; i++) s += ('0' + a[i].toString(16)).slice(-2);
    return s;
  }
  function lsGet(k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { w.localStorage.setItem(k, v); } catch (e) {} }
  function cookie(name) {
    var m = d.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }
  function setCookie(name, val, days) {
    d.cookie = name + '=' + encodeURIComponent(val) + ';max-age=' + days * 86400 +
      ';path=/;SameSite=Lax' + (w.location.protocol === 'https:' ? ';Secure' : '');
  }

  // ---- identifiers: visitor (renewed on each visit) + session (inactivity based) ----
  var vid = cookie(C.cookie) || lsGet('_wa_v'), created = false;
  if (!vid || !/^[a-f0-9]{16,36}$/.test(vid)) { vid = rnd(); created = true; }
  setCookie(C.cookie, vid, C.days); lsSet('_wa_v', vid);

  var sess;
  function session() {
    var t = now(), raw = lsGet('_wa_s'), s = null;
    try { s = raw ? JSON.parse(raw) : null; } catch (e) {}
    if (!s || !s.id || t - s.ts > C.session * 60000) s = { id: rnd(), nv: created ? 1 : 0, fresh: 1 };
    else s.fresh = 0;
    s.ts = t; lsSet('_wa_s', JSON.stringify({ id: s.id, nv: s.nv, ts: t }));
    return s;
  }
  sess = session();

  // ---- bot / automation signals (bitmask, evaluated server-side together with UA & headers) ----
  var sig = 0, ua = n.userAgent || '';
  if (n.webdriver) sig |= 1;
  if (/HeadlessChrome|PhantomJS|Puppeteer|Playwright/i.test(ua)) sig |= 2;
  if (!n.languages || !n.languages.length) sig |= 4;
  if (!w.screen || !w.screen.width || !w.screen.height) sig |= 8;
  if (w._phantom || w.callPhantom || w.__nightmare || w.Cypress || w.domAutomation || w.__selenium_unwrapped) sig |= 16;
  if (/Chrome\//.test(ua) && !w.chrome && !/Edg|OPR|Android|CriOS/.test(ua)) sig |= 32;

  // ---- queue / transport ----
  var q = [], timer = null, url = C.endpoint;
  function send(list) {
    if (w.__WA_DISABLED__ || !list.length) return;
    sess.ts = now(); lsSet('_wa_s', JSON.stringify({ id: sess.id, nv: sess.nv, ts: sess.ts }));
    var body = JSON.stringify({
      v: 1, vid: vid, sid: sess.id, nv: sess.nv, sig: sig, lang: (n.language || '').slice(0, 8),
      sw: w.screen ? w.screen.width : 0, sh: w.screen ? w.screen.height : 0,
      e: list
    });
    try {
      if (n.sendBeacon && n.sendBeacon(url, new Blob([body], { type: 'text/plain' }))) return;
    } catch (e) {}
    try { w.fetch(url, { method: 'POST', body: body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } }); } catch (e) {}
  }
  function flush() { clearTimeout(timer); timer = null; send(q.splice(0, 40)); if (q.length) flush(); }
  function push(ev, immediate) {
    if (w.__WA_DISABLED__) return;
    q.push(ev);
    if (immediate || q.length >= 10) flush(); else if (!timer) timer = setTimeout(flush, 2000);
  }

  // ---- helpers ----
  function docH() { var b = d.body, e = d.documentElement; return Math.max(b ? b.scrollHeight : 0, e.scrollHeight, e.clientHeight, 1); }
  function docW() { var b = d.body, e = d.documentElement; return Math.max(b ? b.scrollWidth : 0, e.scrollWidth, e.clientWidth, 1); }
  function clamp(v) { return Math.max(0, Math.min(100, Math.round(v))); }
  function path() {
    var p = w.location.pathname || '/', keep = C.keep || [];
    if (keep.length && w.location.search) {
      var sp = new URLSearchParams(w.location.search), out = [];
      keep.forEach(function (k) { if (sp.has(k)) out.push(k + '=' + sp.get(k)); });
      if (out.length) p += '?' + out.join('&');
    }
    return p.slice(0, 255);
  }
  function clean(s, max) { return (s || '').replace(/\s+/g, ' ').trim().slice(0, max || 60); }
  function ignored(el) { return el && el.closest && el.closest('[data-wa-ignore]'); }
  function selector(el) {
    var parts = [], depth = 0;
    while (el && el.nodeType === 1 && el !== d.body && depth < 4) {
      var s = el.tagName.toLowerCase();
      if (el.id && /^[A-Za-z][\w-]{0,40}$/.test(el.id)) { parts.unshift(s + '#' + el.id); break; }
      var cls = (el.getAttribute('class') || '').split(/\s+/).filter(function (c) { return c && /^[A-Za-z][\w-]{0,30}$/.test(c); }).slice(0, 2);
      if (cls.length) s += '.' + cls.join('.');
      parts.unshift(s); el = el.parentElement; depth++;
    }
    return parts.join('>').slice(0, 250);
  }
  function labelOf(el) {
    if (!el || el.closest('[data-wa-mask]')) return null;
    var t = el.closest('a,button,[role=button]');
    return t ? clean(t.innerText || t.getAttribute('aria-label') || t.title, 60) : null;
  }
  function base(type) { return { type: type, path: cur.path, title: cur.title }; }

  // ---- page lifecycle ----
  var cur = null, lastUrl = '', maxScroll = 0, bands = new Array(20).fill(0), visMs = 0, tick = 0,
      forms = new Map(), fieldSeen = {}, ended = false;

  function utm() {
    var sp = new URLSearchParams(w.location.search), o = {};
    ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) { if (sp.get(k)) o[k] = sp.get(k).slice(0, 64); });
    return o;
  }

  function startPage(first) {
    cur = { path: path(), title: clean(d.title, 250), t0: now() };
    lastUrl = w.location.pathname + w.location.search;
    maxScroll = 0; bands = new Array(20).fill(0); visMs = 0; forms = new Map(); fieldSeen = {}; ended = false;
    var ev = base('pageview');
    if (first || sess.fresh) {
      ev.ref = d.referrer ? d.referrer.slice(0, 500) : '';
      var u = utm(); if (Object.keys(u).length) ev.data = u;
    }
    sess.fresh = 0;
    push(ev);
    measure();
  }

  function measure() {
    var h = docH(), vh = w.innerHeight || 1, top = w.pageYOffset || d.documentElement.scrollTop || 0;
    maxScroll = Math.max(maxScroll, clamp((top + vh) / h * 100));
  }

  function sample() {
    if (d.hidden || !cur) return;
    measure();
    var h = docH(), vh = w.innerHeight || 1, top = w.pageYOffset || 0;
    var a = Math.floor(top / h * 20), b = Math.min(19, Math.floor(Math.min(h, top + vh) / h * 20 - 0.0001));
    for (var i = Math.max(0, a); i <= b; i++) bands[i] += 500;
    visMs += 500;
  }

  function endPage() {
    if (!cur || ended) return;
    ended = true; measure();
    var mask = 0;
    bands.forEach(function (ms, i) { if (ms >= 1000) mask |= (1 << i); });
    if (C.forms) forms.forEach(function (f, key) {
      if (f.started && !f.submitted) push({ type: 'form_abandon', path: cur.path, title: cur.title, target: key, label: f.last || null });
    });
    var ev = base('engage');
    ev.value = maxScroll; ev.aux = mask; ev.dur = Math.round(visMs / 1000); ev.ph = docH();
    push(ev, true);
    flush();
  }

  function onUrlChange() {
    var u = w.location.pathname + w.location.search;
    if (u === lastUrl) return;
    endPage();
    setTimeout(function () { if (w.location.pathname + w.location.search !== lastUrl) startPage(false); }, 80);
  }

  // ---- interactions ----
  if (C.clicks) d.addEventListener('click', function (e) {
    var t = e.target; if (!cur || !t || !t.closest || ignored(t)) return;
    var g = t.closest('[data-wa-goal]');
    if (g) api.track(g.getAttribute('data-wa-goal'));
    var ev = base('click');
    ev.target = selector(t); ev.label = labelOf(t);
    ev.x = clamp(e.pageX / docW() * 100); ev.y = clamp(e.pageY / docH() * 100); ev.ph = docH();
    push(ev);
    var a = t.closest('a[href]');
    if (a && C.links) {
      var href = (a.href || '').split('#')[0].split('?')[0].slice(0, 250);
      var lk = base('link'); lk.target = href; lk.label = labelOf(t);
      lk.data = { out: a.host && a.host !== w.location.host ? 1 : 0 };
      push(lk, lk.data.out === 1);
    }
  }, true);

  function formKey(f) {
    var k = f.id || f.getAttribute('name');
    if (k) return ('#' + k).slice(0, 120);
    var act = f.getAttribute('action');
    if (act) { try { return new URL(act, w.location.href).pathname.slice(0, 120); } catch (e) {} }
    return 'form[' + Array.prototype.indexOf.call(d.forms, f) + ']';
  }
  if (C.forms) {
    d.addEventListener('focusin', function (e) {
      var el = e.target, f = el && el.form;
      if (!cur || !f || ignored(el) || !/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) return;
      if (/^(password|hidden|submit|button|reset)$/i.test(el.type || '')) return;
      var key = formKey(f), st = forms.get(key);
      if (!st) { st = { started: false, submitted: false, last: null }; forms.set(key, st); }
      var field = (el.name || el.id || el.type || 'field').slice(0, 80);
      if (!st.started) { st.started = true; var s = base('form_start'); s.target = key; push(s); }
      st.last = field;
      var fk = key + '|' + field;
      if (!fieldSeen[fk]) { fieldSeen[fk] = 1; var ff = base('form_field'); ff.target = key; ff.label = field; push(ff); }
    }, true);
    d.addEventListener('submit', function (e) {
      var f = e.target; if (!cur || !f || f.tagName !== 'FORM' || ignored(f)) return;
      var key = formKey(f), st = forms.get(key);
      if (!st) { st = { started: true, submitted: false, last: null }; forms.set(key, st); }
      st.submitted = true;
      var ev = base('form_submit'); ev.target = key; push(ev, true);
    }, true);
  }

  // ---- public API ----
  var api = w.analytics = w.analytics || {};
  api.track = function (name, value, data) {
    if (!cur || !name) return;
    var ev = base('goal'); ev.target = String(name).slice(0, 120);
    if (typeof value === 'number' && isFinite(value)) ev.value = Math.round(value);
    if (data && typeof data === 'object') ev.data = data;
    push(ev, true);
  };
  api.pageview = function () { lastUrl = ''; onUrlChange(); };

  // ---- boot ----
  function boot() {
    startPage(true);
    setInterval(sample, 500);
    w.addEventListener('scroll', measure, { passive: true });
    d.addEventListener('visibilitychange', function () { if (d.hidden) { endPage(); } else if (ended) { startEngageOnly(); } });
    w.addEventListener('pagehide', endPage);
    if (C.spa) {
      ['pushState', 'replaceState'].forEach(function (m) {
        var o = w.history[m];
        w.history[m] = function () { var r = o.apply(this, arguments); onUrlChange(); return r; };
      });
      w.addEventListener('popstate', onUrlChange);
      ['livewire:navigated', 'turbo:load', 'htmx:pushedUrl'].forEach(function (ev) { d.addEventListener(ev, onUrlChange); });
    }
  }
  // returning to a hidden tab keeps the same page view; start a fresh engagement window
  function startEngageOnly() { ended = false; visMs = 0; bands = new Array(20).fill(0); }

  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot); else boot();
})(window, document);
