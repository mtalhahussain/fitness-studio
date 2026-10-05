@php
    $brandName = $hostGym->name ?? config('app.name', 'Fitness Studio');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Sign in — {{ $brandName }}</title>
    <script>(function(){try{var t=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme: light)').matches?'light':'dark');document.documentElement.setAttribute('data-theme',t);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@600;700;800;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --volt:       #d4ff3a;
            --volt-deep:  #b8e61a;
            --volt-glow:  rgba(212,255,58,.35);
            --iron:       #0a0a0b;
            --iron-2:     #121214;
            --iron-3:     #1a1a1d;
            --ember:      #ff5a1f;
            --display:    'Big Shoulders Display', 'Arial Narrow', sans-serif;
            --body:       'Manrope', system-ui, sans-serif;
            --mono:       'JetBrains Mono', ui-monospace, monospace;
            --ease:       cubic-bezier(.2,.8,.2,1);
        }

        :root, [data-theme="dark"] {
            --panel:        #0f0f11;
            --panel-line:   rgba(255,255,255,.07);
            --field:        #17171a;
            --field-line:   rgba(255,255,255,.10);
            --field-focus:  #1c1c20;
            --ink:          #f4f4f1;
            --ink-sub:      #a3a3a0;
            --ink-mute:     #64645f;
            --error:        #ff6b5b;
            --btn-ink:      #0a0a0b;
            --chip:         rgba(255,255,255,.04);
        }

        [data-theme="light"] {
            --panel:        #f6f6f2;
            --panel-line:   rgba(10,10,11,.09);
            --field:        #ffffff;
            --field-line:   rgba(10,10,11,.14);
            --field-focus:  #ffffff;
            --ink:          #0a0a0b;
            --ink-sub:      #4a4a46;
            --ink-mute:     #8a8a84;
            --error:        #d6331f;
            --btn-ink:      #0a0a0b;
            --chip:         rgba(10,10,11,.035);
        }

        html, body { min-height: 100%; }
        body {
            font-family: var(--body);
            background: var(--iron);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        .wrap { display: grid; grid-template-columns: minmax(0, 1fr) 520px; min-height: 100vh; }

        /* ═════════════ Stage (left) ═════════════ */
        .stage {
            position: relative; overflow: hidden;
            background: var(--iron);
            color: #f4f4f1;
            display: flex; flex-direction: column;
            padding: 36px 56px 0;
            isolation: isolate;
        }

        /* Grain */
        .stage::after {
            content: ''; position: absolute; inset: -50%; z-index: 5; pointer-events: none; opacity: .07;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* Diagonal hazard band + glow */
        .stage-glow {
            position: absolute; z-index: -1; pointer-events: none;
            width: 900px; height: 900px; right: -320px; top: 50%; transform: translateY(-50%);
            background: radial-gradient(circle, rgba(212,255,58,.12) 0%, rgba(212,255,58,0) 60%);
        }
        .stage-lines {
            position: absolute; inset: 0; z-index: -1; pointer-events: none;
            background-image: repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 1px, transparent 1px 120px);
            mask-image: linear-gradient(180deg, transparent, #000 25%, #000 70%, transparent);
        }

        /* Weight plate */
        .plate {
            position: absolute; z-index: -1; pointer-events: none;
            width: 640px; height: 640px; right: -190px; top: 50%; margin-top: -360px;
            opacity: .9;
        }
        .plate svg { width: 100%; height: 100%; }

        /* Top bar */
        .topbar { display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2; }
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; }
        .brand-mark {
            width: 40px; height: 40px; border-radius: 10px;
            background: var(--volt); color: var(--iron);
            display: grid; place-items: center;
            box-shadow: 0 0 0 1px rgba(212,255,58,.4), 0 8px 30px -6px var(--volt-glow);
        }
        .brand-name {
            font-family: var(--display); font-weight: 800; font-size: 22px;
            letter-spacing: .02em; text-transform: uppercase; line-height: 1;
        }
        .brand-sub { font-size: 11px; color: rgba(244,244,241,.45); margin-top: 3px; letter-spacing: .04em; }

        .status-pill {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: var(--mono); font-size: 11px; letter-spacing: .06em; text-transform: uppercase;
            color: rgba(244,244,241,.6);
            border: 1px solid rgba(255,255,255,.1); border-radius: 999px; padding: 7px 12px;
            background: rgba(255,255,255,.02);
        }
        .status-pill i { width: 7px; height: 7px; border-radius: 50%; background: var(--volt); box-shadow: 0 0 10px var(--volt); animation: blink 2.4s infinite; }
        @keyframes blink { 0%,100% { opacity: 1 } 50% { opacity: .35 } }

        /* Hero */
        .hero { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 48px 0 40px; position: relative; z-index: 2; max-width: 640px; }

        .eyebrow {
            display: inline-flex; align-items: center; gap: 10px; margin-bottom: 22px;
            font-family: var(--mono); font-size: 11.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--volt);
        }
        .eyebrow::before { content: ''; width: 28px; height: 2px; background: var(--volt); }

        .headline {
            font-family: var(--display); font-weight: 900; text-transform: uppercase;
            font-size: clamp(56px, 7.4vw, 112px); line-height: .86; letter-spacing: -.01em;
        }
        .headline .line { display: block; overflow: hidden; padding-bottom: .04em; }
        .headline .line > span { display: inline-block; will-change: transform; }
        .headline .stroke { color: transparent; -webkit-text-stroke: 2px rgba(244,244,241,.85); }
        .headline .hl { color: var(--volt); position: relative; }

        .lede {
            margin-top: 26px; max-width: 460px;
            font-size: 15.5px; line-height: 1.7; color: rgba(244,244,241,.58);
        }
        .lede strong { color: #f4f4f1; font-weight: 600; }

        /* Stat rail */
        .stats { display: flex; gap: 0; margin-top: 40px; border-top: 1px solid rgba(255,255,255,.08); max-width: 560px; }
        .stat { flex: 1; padding: 18px 20px 0 0; }
        .stat + .stat { padding-left: 20px; border-left: 1px solid rgba(255,255,255,.08); }
        .stat-num { font-family: var(--display); font-weight: 800; font-size: 40px; line-height: 1; color: #f4f4f1; }
        .stat-num small { font-size: 20px; color: var(--volt); margin-left: 2px; }
        .stat-label { margin-top: 8px; font-size: 12px; color: rgba(244,244,241,.45); line-height: 1.45; }

        /* Ticker */
        .ticker {
            position: relative; z-index: 2;
            margin: 0 -56px; border-top: 1px solid rgba(255,255,255,.08);
            background: var(--volt); color: var(--iron);
            overflow: hidden; white-space: nowrap;
        }
        .ticker-track { display: inline-flex; padding: 14px 0; will-change: transform; }
        .ticker-item {
            display: inline-flex; align-items: center; gap: 14px; padding: 0 22px;
            font-family: var(--display); font-weight: 800; font-size: 19px; letter-spacing: .04em; text-transform: uppercase;
        }
        .ticker-item::after { content: ''; width: 8px; height: 8px; background: var(--iron); transform: rotate(45deg); margin-left: 22px; }

        /* ═════════════ Form panel (right) ═════════════ */
        .panel {
            position: relative; background: var(--panel);
            border-left: 1px solid var(--panel-line);
            display: flex; flex-direction: column;
            padding: 36px 64px;
            transition: background .3s var(--ease);
        }
        .panel-top { display: flex; justify-content: flex-end; }

        .theme-btn {
            width: 38px; height: 38px; border-radius: 10px;
            background: var(--chip); border: 1px solid var(--panel-line);
            color: var(--ink-sub); cursor: pointer;
            display: grid; place-items: center; transition: color .2s, border-color .2s, transform .2s var(--ease);
        }
        .theme-btn:hover { color: var(--ink); border-color: var(--ink-mute); }
        .theme-btn:active { transform: scale(.94); }
        .theme-btn:focus-visible { outline: 2px solid var(--volt); outline-offset: 2px; }

        .form-zone { flex: 1; display: flex; flex-direction: column; justify-content: center; max-width: 380px; width: 100%; margin: 0 auto; padding: 24px 0; }

        .form-kicker {
            font-family: var(--mono); font-size: 11px; letter-spacing: .14em; text-transform: uppercase;
            color: var(--ink-mute); margin-bottom: 14px;
        }
        .form-title {
            font-family: var(--display); font-weight: 900; text-transform: uppercase;
            font-size: 52px; line-height: .9; letter-spacing: -.005em; color: var(--ink);
        }
        .form-title span { display: inline-block; background: var(--volt); color: var(--iron); padding: 0 .12em; margin-left: -.04em; }
        .form-sub { margin-top: 14px; font-size: 14px; line-height: 1.6; color: var(--ink-sub); }

        form { margin-top: 34px; }

        .field { margin-bottom: 18px; }
        .field label {
            display: block; margin-bottom: 8px;
            font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-sub);
        }
        .input {
            position: relative; display: flex; align-items: center;
            background: var(--field); border: 1.5px solid var(--field-line); border-radius: 12px;
            transition: border-color .2s, background .2s, box-shadow .25s var(--ease);
        }
        .input:focus-within { border-color: var(--volt); background: var(--field-focus); box-shadow: 0 0 0 4px rgba(212,255,58,.14); }
        [data-theme="light"] .input:focus-within { border-color: var(--iron); box-shadow: 0 0 0 4px rgba(10,10,11,.08); }
        .input.is-invalid { border-color: var(--error); }
        .input-icon { padding-left: 15px; color: var(--ink-mute); display: flex; transition: color .2s; }
        .input:focus-within .input-icon { color: var(--ink); }
        .input input {
            flex: 1; min-width: 0; border: 0; outline: 0; background: transparent;
            padding: 15px 14px 15px 12px;
            font: 500 15px/1.2 var(--body); color: var(--ink);
        }
        .input input::placeholder { color: var(--ink-mute); font-weight: 400; }
        .input input:-webkit-autofill { -webkit-text-fill-color: var(--ink); -webkit-box-shadow: 0 0 0 100px var(--field) inset; transition: background-color 9999s; }

        .reveal-btn {
            border: 0; background: none; cursor: pointer; color: var(--ink-mute);
            padding: 0 15px; height: 100%; display: flex; align-items: center; transition: color .2s;
        }
        .reveal-btn:hover { color: var(--ink); }
        .reveal-btn:focus-visible { outline: 2px solid var(--volt); outline-offset: -4px; border-radius: 8px; }

        .field-error, .caps-warn { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12.5px; font-weight: 600; }
        .field-error { color: var(--error); }
        .caps-warn { color: var(--ember); }
        .caps-warn[hidden] { display: none; }

        .row-between { display: flex; align-items: center; justify-content: space-between; margin: 6px 0 26px; gap: 12px; }

        .check { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; font-size: 13.5px; color: var(--ink-sub); }
        .check input { position: absolute; opacity: 0; pointer-events: none; }
        .check-box {
            width: 20px; height: 20px; border-radius: 6px; flex-shrink: 0;
            border: 1.5px solid var(--field-line); background: var(--field);
            display: grid; place-items: center; transition: background .2s, border-color .2s;
        }
        .check-box svg { opacity: 0; transform: scale(.5); transition: opacity .2s, transform .25s var(--ease); }
        .check input:checked + .check-box { background: var(--volt); border-color: var(--volt); }
        .check input:checked + .check-box svg { opacity: 1; transform: scale(1); }
        .check input:focus-visible + .check-box { outline: 2px solid var(--volt); outline-offset: 2px; }

        .help-text { font-size: 12.5px; color: var(--ink-mute); text-align: right; }

        .btn-submit {
            position: relative; width: 100%; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px; border: 0; border-radius: 12px; cursor: pointer;
            background: var(--volt); color: var(--btn-ink);
            font-family: var(--display); font-weight: 800; font-size: 22px; letter-spacing: .06em; text-transform: uppercase;
            box-shadow: 0 14px 34px -14px var(--volt-glow);
            transition: box-shadow .25s var(--ease), background .2s;
        }
        .btn-submit::before {
            content: ''; position: absolute; inset: 0; background: var(--iron);
            transform: translateY(101%); transition: transform .45s var(--ease);
        }
        .btn-submit > * { position: relative; z-index: 1; transition: color .3s; }
        .btn-submit:hover::before { transform: translateY(0); }
        .btn-submit:hover > * { color: var(--volt); }
        .btn-submit:focus-visible { outline: 3px solid var(--ink); outline-offset: 3px; }
        .btn-arrow { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 8px; background: var(--iron); color: var(--volt); transition: transform .35s var(--ease), background .3s; }
        .btn-submit:hover .btn-arrow { transform: translateX(4px); background: var(--volt); color: var(--iron); }
        .btn-submit[disabled] { cursor: progress; }
        .btn-submit .spinner { display: none; width: 18px; height: 18px; border: 2.5px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spin .7s linear infinite; }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-arrow svg { display: none; }
        @keyframes spin { to { transform: rotate(360deg) } }

        .alert {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 12px 14px; border-radius: 10px; margin-top: 24px;
            font-size: 13.5px; line-height: 1.5;
            background: rgba(212,255,58,.08); border: 1px solid rgba(212,255,58,.25); color: var(--ink);
        }

        /* Demo vault — only rendered for ?demo=<key> */
        .demo { margin-top: 30px; border: 1.5px dashed var(--field-line); border-radius: 14px; padding: 16px; }
        .demo-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .demo-title { font-family: var(--mono); font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--ink-mute); }
        .demo-pass { font-family: var(--mono); font-size: 11px; color: var(--ink-sub); }
        .demo-pass code { background: var(--chip); border: 1px solid var(--panel-line); padding: 2px 6px; border-radius: 5px; color: var(--ink); }
        .demo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .demo-btn {
            display: flex; flex-direction: column; align-items: flex-start; gap: 6px;
            padding: 12px; border-radius: 10px; cursor: pointer; text-align: left;
            background: var(--chip); border: 1px solid var(--panel-line); color: var(--ink);
            font-family: var(--body); transition: border-color .2s, transform .25s var(--ease), background .2s;
        }
        .demo-btn:hover { border-color: var(--volt); transform: translateY(-2px); }
        .demo-btn:focus-visible { outline: 2px solid var(--volt); outline-offset: 2px; }
        .demo-btn.active { border-color: var(--volt); background: rgba(212,255,58,.1); }
        .demo-role { font-family: var(--display); font-weight: 800; font-size: 17px; letter-spacing: .04em; text-transform: uppercase; }
        .demo-email { font-size: 10.5px; color: var(--ink-mute); word-break: break-all; line-height: 1.3; }
        .demo-tag { width: 8px; height: 8px; border-radius: 2px; }

        .panel-foot {
            display: flex; justify-content: space-between; align-items: center; gap: 12px;
            font-size: 12px; color: var(--ink-mute);
            padding-top: 20px; border-top: 1px solid var(--panel-line);
        }
        .panel-foot .secure { display: inline-flex; align-items: center; gap: 6px; }

        /* Pre-animation state (only when JS + motion allowed) */
        .js-anim [data-reveal] { opacity: 0; }

        /* ═════════════ Responsive ═════════════ */
        @media (max-width: 1180px) {
            .wrap { grid-template-columns: minmax(0, 1fr) 460px; }
            .panel { padding: 32px 44px; }
            .stage { padding: 32px 40px 0; }
            .ticker { margin: 0 -40px; }
            .plate { width: 520px; height: 520px; right: -220px; margin-top: -300px; }
        }
        @media (max-width: 920px) {
            .wrap { grid-template-columns: 1fr; }
            .stage { padding: 24px 20px 0; min-height: auto; }
            .hero { padding: 40px 0 32px; }
            .headline { font-size: clamp(48px, 13vw, 80px); }
            .stats, .lede { display: none; }
            .ticker { margin: 0 -20px; }
            .plate { width: 360px; height: 360px; right: -140px; top: 40px; margin-top: 0; opacity: .6; }
            .status-pill { display: none; }
            .panel { border-left: 0; border-top: 1px solid var(--panel-line); padding: 24px 20px; }
            .form-zone { padding: 16px 0 32px; }
            .form-title { font-size: 44px; }
        }
        @media (max-width: 420px) {
            .demo-grid { grid-template-columns: 1fr; }
            .demo-btn { flex-direction: row; align-items: center; }
            .demo-email { margin-left: auto; }
            .row-between { flex-direction: column; align-items: flex-start; }
            .help-text { text-align: left; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
<div class="wrap">

    {{-- ═════════════ Stage ═════════════ --}}
    <section class="stage" aria-hidden="false">
        <div class="stage-lines"></div>
        <div class="stage-glow"></div>

        {{-- Weight plate motif --}}
        <div class="plate" aria-hidden="true">
            <svg viewBox="0 0 400 400" id="plate">
                <defs>
                    <path id="plate-text" d="M200,200 m-150,0 a150,150 0 1,1 300,0 a150,150 0 1,1 -300,0"/>
                </defs>
                <circle cx="200" cy="200" r="196" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="2"/>
                <circle cx="200" cy="200" r="180" fill="#111113" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
                <circle cx="200" cy="200" r="170" fill="none" stroke="rgba(212,255,58,.55)" stroke-width="1.5" stroke-dasharray="2 8"/>
                <text font-family="Big Shoulders Display, sans-serif" font-weight="800" font-size="19" letter-spacing="6" fill="rgba(244,244,241,.35)">
                    <textPath href="#plate-text">TRAIN · TRACK · RETAIN · GROW · TRAIN · TRACK · RETAIN · GROW ·</textPath>
                </text>
                <circle cx="200" cy="200" r="118" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="22"/>
                <circle cx="200" cy="200" r="90" fill="#0c0c0d" stroke="rgba(255,255,255,.08)"/>
                <text x="200" y="196" text-anchor="middle" font-family="Big Shoulders Display, sans-serif" font-weight="900" font-size="64" fill="#d4ff3a">20</text>
                <text x="200" y="224" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="12" letter-spacing="4" fill="rgba(244,244,241,.4)">KG</text>
                <circle cx="200" cy="200" r="26" fill="#050505" stroke="rgba(255,255,255,.12)" stroke-width="2"/>
            </svg>
        </div>

        <div class="topbar" data-reveal>
            <div class="brand">
                <div class="brand-mark">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>
                    </svg>
                </div>
                <div>
                    <div class="brand-name">{{ $brandName }}</div>
                    <div class="brand-sub">Gym Management Platform</div>
                </div>
            </div>
            <span class="status-pill"><i></i> All systems operational</span>
        </div>

        <div class="hero">
            <div class="eyebrow" data-reveal>Owner's command center</div>

            <h1 class="headline">
                <span class="line"><span>Run your gym</span></span>
                <span class="line"><span class="stroke">like a</span> <span class="hl">machine.</span></span>
            </h1>

            <p class="lede" data-reveal>
                Memberships, renewals, biometric check-ins, trainers, POS and
                WhatsApp reminders — <strong>one dashboard, zero spreadsheets.</strong>
            </p>

            <div class="stats" data-reveal>
                <div class="stat">
                    <div class="stat-num"><span data-count="24">24</span><small>/7</small></div>
                    <div class="stat-label">Biometric check-ins, synced live</div>
                </div>
                <div class="stat">
                    <div class="stat-num"><span data-count="100">100</span><small>%</small></div>
                    <div class="stat-label">Of renewals tracked automatically</div>
                </div>
                <div class="stat">
                    <div class="stat-num"><span data-count="0">0</span><small>min</small></div>
                    <div class="stat-label">Manual attendance entry</div>
                </div>
            </div>
        </div>

        <div class="ticker" aria-hidden="true">
            <div class="ticker-track" id="ticker">
                @foreach (['Memberships', 'Biometric Check-in', 'Trainer Commissions', 'Point of Sale', 'WhatsApp Reminders', 'Revenue Reports', 'Multi-Branch'] as $item)
                    <span class="ticker-item">{{ $item }}</span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═════════════ Form panel ═════════════ --}}
    <main class="panel">
        <div class="panel-top">
            <button type="button" class="theme-btn" onclick="toggleTheme()" aria-label="Toggle colour theme">
                <svg id="icon-sun" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:none" aria-hidden="true">
                    <circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
                <svg id="icon-moon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
            </button>
        </div>

        <div class="form-zone">
            <div class="form-kicker" data-reveal>Secure sign in</div>
            <h2 class="form-title" data-reveal>Back to<br><span>work.</span></h2>
            <p class="form-sub" data-reveal>Sign in to manage {{ $hostGym ? $hostGym->name : 'your gym' }}.</p>

            @if (session('status'))
                <div class="alert" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" id="login-form" novalidate>
                @csrf

                <div class="field" data-reveal>
                    <label for="email">Email</label>
                    <div class="input @error('email') is-invalid @enderror">
                        <span class="input-icon" aria-hidden="true">
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 7l9 6 9-6"/></svg>
                        </span>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               placeholder="you@yourgym.com" autocomplete="username" required autofocus
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    </div>
                    @error('email')
                        <p class="field-error" id="email-error" role="alert">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="field" data-reveal>
                    <label for="password">Password</label>
                    <div class="input @error('password') is-invalid @enderror">
                        <span class="input-icon" aria-hidden="true">
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 018 0v3.5"/></svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="Enter your password"
                               autocomplete="current-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button type="button" class="reveal-btn" id="reveal-btn" aria-label="Show password" aria-pressed="false">
                            <svg id="eye-open" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7.5 11-7.5S23 12 23 12s-4 7.5-11 7.5S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="eye-shut" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none" aria-hidden="true"><path d="M17.94 17.94A10.1 10.1 0 0112 19.5C5 19.5 1 12 1 12a18.5 18.5 0 015.06-5.94M9.9 4.74A9.1 9.1 0 0112 4.5c7 0 11 7.5 11 7.5a18.6 18.6 0 01-2.16 3.19M1 1l22 22"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/></svg>
                        </button>
                    </div>
                    <p class="caps-warn" id="caps-warn" hidden>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 3l9 9h-5v6H8v-6H3z"/></svg>
                        Caps Lock is on
                    </p>
                    @error('password')
                        <p class="field-error" id="password-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="row-between" data-reveal>
                    <label class="check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span class="check-box"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0a0a0b" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7"/></svg></span>
                        Keep me signed in
                    </label>
                    <span class="help-text">Forgot password? Ask your admin.</span>
                </div>

                <div data-reveal>
                    <button type="submit" class="btn-submit" id="submit-btn">
                        <span class="btn-label">Sign in</span>
                        <span class="btn-arrow">
                            <span class="spinner" aria-hidden="true"></span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </span>
                    </button>
                </div>
            </form>

            @if ($showDemo)
                <div class="demo" data-reveal>
                    <div class="demo-head">
                        <span class="demo-title">Demo accounts</span>
                        <span class="demo-pass">pass <code>password</code></span>
                    </div>
                    <div class="demo-grid">
                        @foreach ([
                            ['Owner',   'owner@demogym.com',   '#d4ff3a'],
                            ['Trainer', 'trainer@demogym.com', '#ff5a1f'],
                            ['Member',  'member@demogym.com',  '#5ab8ff'],
                        ] as [$role, $email, $color])
                            <button type="button" class="demo-btn" data-email="{{ $email }}">
                                <span class="demo-tag" style="background: {{ $color }}"></span>
                                <span class="demo-role">{{ $role }}</span>
                                <span class="demo-email">{{ $email }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="panel-foot">
            <span>&copy; {{ date('Y') }} {{ config('app.name', 'Fitness Studio') }}</span>
            <span class="secure">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Encrypted connection
            </span>
        </div>
    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
<script>
    /* ── Theme ── */
    function syncIcons(t) {
        document.getElementById('icon-sun').style.display  = t === 'light' ? 'block' : 'none';
        document.getElementById('icon-moon').style.display = t === 'dark'  ? 'block' : 'none';
    }
    function toggleTheme() {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('theme', next); } catch (e) {}
        syncIcons(next);
    }
    syncIcons(document.documentElement.getAttribute('data-theme') || 'dark');

    /* ── Form behaviour ── */
    (function () {
        var form = document.getElementById('login-form');
        var pwd  = document.getElementById('password');
        var btn  = document.getElementById('submit-btn');
        var rev  = document.getElementById('reveal-btn');
        var caps = document.getElementById('caps-warn');

        rev.addEventListener('click', function () {
            var show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            rev.setAttribute('aria-pressed', show);
            rev.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            document.getElementById('eye-open').style.display = show ? 'none' : 'block';
            document.getElementById('eye-shut').style.display = show ? 'block' : 'none';
            pwd.focus();
        });

        function capsCheck(e) {
            if (e.getModifierState) caps.hidden = !e.getModifierState('CapsLock');
        }
        pwd.addEventListener('keyup', capsCheck);
        pwd.addEventListener('keydown', capsCheck);
        pwd.addEventListener('blur', function () { caps.hidden = true; });

        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                var bad = form.querySelector(':invalid');
                bad.focus();
                if (window.gsap) gsap.fromTo(bad.closest('.input'), { x: -8 }, { x: 0, duration: .5, ease: 'elastic.out(1, .3)' });
                return;
            }
            btn.disabled = true;
            btn.classList.add('loading');
            btn.querySelector('.btn-label').textContent = 'Signing in';
        });

        // bfcache: re-enable the button if the user navigates back
        window.addEventListener('pageshow', function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            btn.querySelector('.btn-label').textContent = 'Sign in';
        });

        document.querySelectorAll('.demo-btn').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('.demo-btn').forEach(function (x) { x.classList.remove('active'); });
                b.classList.add('active');
                document.getElementById('email').value = b.dataset.email;
                pwd.value = 'password';
                if (window.gsap) gsap.fromTo('.input', { boxShadow: '0 0 0 4px rgba(212,255,58,.35)' }, { boxShadow: '0 0 0 0px rgba(212,255,58,0)', duration: .8, ease: 'power2.out', stagger: .08 });
                btn.focus();
            });
        });
    })();

    /* ── Motion (GSAP) ── */
    (function () {
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) return;
        document.documentElement.classList.add('js-anim');

        function run() {
            if (!window.gsap) { document.documentElement.classList.remove('js-anim'); return; }

            var tl = gsap.timeline({ defaults: { ease: 'power4.out' } });

            tl.from('.headline .line > span', { yPercent: 110, duration: 1.1, stagger: .12 })
              .fromTo('.stage [data-reveal]', { opacity: 0, y: 24 }, { opacity: 1, y: 0, duration: .8, stagger: .08 }, '-=.8')
              .fromTo('.panel [data-reveal]', { opacity: 0, y: 18 }, { opacity: 1, y: 0, duration: .7, stagger: .06 }, '-=.9')
              .from('.plate', { rotate: -120, scale: .85, opacity: 0, duration: 1.6, ease: 'expo.out' }, 0)
              .from('.ticker', { yPercent: 100, duration: .8 }, .4);

            // Stat counters
            document.querySelectorAll('[data-count]').forEach(function (el) {
                var end = +el.dataset.count, o = { v: 0 };
                tl.to(o, { v: end, duration: 1.4, ease: 'power2.out', onUpdate: function () { el.textContent = Math.round(o.v); } }, .6);
            });

            // Weight plate: slow perpetual spin
            gsap.to('#plate', { rotate: 360, duration: 90, repeat: -1, ease: 'none', transformOrigin: '50% 50%' });

            // Infinite ticker — duplicate content once for a seamless loop
            var track = document.getElementById('ticker');
            track.innerHTML += track.innerHTML;
            gsap.to(track, { xPercent: -50, duration: 28, repeat: -1, ease: 'none' });

            // Parallax plate on pointer move (desktop only)
            if (window.matchMedia('(pointer: fine)').matches) {
                var stage = document.querySelector('.stage');
                var px = gsap.quickTo('.plate', 'x', { duration: 1.2, ease: 'power3.out' });
                var py = gsap.quickTo('.plate', 'y', { duration: 1.2, ease: 'power3.out' });
                stage.addEventListener('pointermove', function (e) {
                    var r = stage.getBoundingClientRect();
                    px(((e.clientX - r.left) / r.width - .5) * -40);
                    py(((e.clientY - r.top) / r.height - .5) * -40);
                });

                // Magnetic submit button
                var btn = document.getElementById('submit-btn');
                var bx = gsap.quickTo(btn, 'x', { duration: .5, ease: 'power3.out' });
                var by = gsap.quickTo(btn, 'y', { duration: .5, ease: 'power3.out' });
                btn.addEventListener('pointermove', function (e) {
                    var r = btn.getBoundingClientRect();
                    bx((e.clientX - r.left - r.width / 2) * .08);
                    by((e.clientY - r.top - r.height / 2) * .25);
                });
                btn.addEventListener('pointerleave', function () { bx(0); by(0); });
            }

            // Shake on server-side validation error
            var err = document.querySelector('.input.is-invalid');
            if (err) tl.fromTo(err, { x: -10 }, { x: 0, duration: .6, ease: 'elastic.out(1, .3)' }, '-=.3');
        }

        // If CDN fails / is slow, never leave content hidden
        var fallback = setTimeout(function () { document.documentElement.classList.remove('js-anim'); }, 2500);
        window.addEventListener('DOMContentLoaded', function () {
            if (window.gsap) { clearTimeout(fallback); run(); }
            else document.querySelector('script[src*="gsap"]').addEventListener('load', function () { clearTimeout(fallback); run(); });
        });
    })();
</script>
</body>
</html>
