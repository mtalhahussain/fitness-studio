<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Fitness Studio</title>
    <script>(function(){const t=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme:light)').matches?'light':'dark');document.documentElement.setAttribute('data-theme',t);})();</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root, [data-theme="dark"] {
            --bg:             #080912;
            --left-bg:        #0c0d1c;
            --right-bg:       #101122;
            --border:         rgba(255,255,255,0.06);
            --input-bg:       rgba(255,255,255,0.04);
            --input-border:   rgba(255,255,255,0.09);
            --input-focus:    rgba(108,99,255,0.07);
            --primary:        #6C63FF;
            --primary-hover:  #5a52e0;
            --primary-glow:   rgba(108,99,255,0.35);
            --text:           #f1f5f9;
            --text-sub:       #94a3b8;
            --text-muted:     #475569;
            --error:          #f87171;
            --surface:        rgba(255,255,255,0.03);
            --surface-border: rgba(255,255,255,0.06);
        }

        [data-theme="light"] {
            --bg:             #eef0f8;
            --left-bg:        #16184a;
            --right-bg:       #ffffff;
            --border:         rgba(0,0,0,0.07);
            --input-bg:       #f8fafc;
            --input-border:   rgba(0,0,0,0.10);
            --input-focus:    rgba(108,99,255,0.04);
            --primary:        #6C63FF;
            --primary-hover:  #5a52e0;
            --primary-glow:   rgba(108,99,255,0.20);
            --text:           #0f172a;
            --text-sub:       #475569;
            --text-muted:     #94a3b8;
            --error:          #ef4444;
            --surface:        rgba(0,0,0,0.02);
            --surface-border: rgba(0,0,0,0.06);
        }

        html, body { height: 100%; font-family: 'Inter', sans-serif; background: var(--bg); }
        .wrap { display: flex; min-height: 100vh; }

        /* ────────── Left panel ────────── */
        .left {
            flex: 1; background: var(--left-bg);
            display: flex; flex-direction: column;
            padding: 40px 52px; position: relative; overflow: hidden;
        }

        /* Mesh gradient */
        .left::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 65% 50% at 5% 5%,   rgba(108,99,255,.24) 0%, transparent 65%),
                radial-gradient(ellipse 55% 60% at 90% 90%, rgba(147,51,234,.18) 0%, transparent 65%),
                radial-gradient(ellipse 40% 35% at 50% 50%, rgba(59,130,246,.06) 0%, transparent 70%);
        }

        /* Fine grid */
        .left::after {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,.022) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.022) 1px, transparent 1px);
            background-size: 52px 52px;
        }

        .left-inner { position: relative; z-index: 1; display: flex; flex-direction: column; height: 100%; }

        /* Brand */
        .brand { display: flex; align-items: center; gap: 11px; }
        .brand-mark {
            width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
            background: linear-gradient(145deg, #6C63FF 0%, #9333ea 100%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 0 1px rgba(255,255,255,.12), 0 4px 20px rgba(108,99,255,.45);
        }
        .brand-text { line-height: 1.2; }
        .brand-name    { font-size: 14px; font-weight: 700; color: #fff; letter-spacing: -.1px; }
        .brand-tagline { font-size: 11px; color: rgba(255,255,255,.32); margin-top: 1px; }

        /* Hero */
        .hero { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 56px 0 44px; max-width: 400px; }

        .kicker {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 10.5px; font-weight: 600; letter-spacing: 1.2px;
            text-transform: uppercase; color: rgba(167,139,250,.75);
            margin-bottom: 20px;
        }
        .kicker-bar { width: 18px; height: 1.5px; background: rgba(167,139,250,.45); border-radius: 2px; }

        .hero h1 {
            font-size: 36px; font-weight: 800; color: #fff;
            line-height: 1.14; letter-spacing: -1.4px; margin-bottom: 18px;
        }
        .hero h1 em {
            font-style: normal;
            background: linear-gradient(115deg, #a78bfa 0%, #f472b6 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .hero-desc {
            font-size: 14px; color: rgba(255,255,255,.38);
            line-height: 1.78; margin-bottom: 48px; max-width: 340px;
        }

        /* Feature list */
        .features { display: flex; flex-direction: column; gap: 18px; }
        .feat { display: flex; align-items: flex-start; gap: 13px; }
        .feat-dot {
            width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px;
            background: rgba(108,99,255,.13); border: 1px solid rgba(108,99,255,.22);
            display: flex; align-items: center; justify-content: center; margin-top: 1px;
        }
        .feat-title { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.82); }
        .feat-desc  { font-size: 12px; color: rgba(255,255,255,.30); margin-top: 3px; line-height: 1.55; }

        /* Footer strip */
        .left-footer {
            display: flex; align-items: center; justify-content: space-between;
            border-top: 1px solid rgba(255,255,255,.06); padding-top: 22px; margin-top: 40px;
        }
        .left-footer-copy { font-size: 11px; color: rgba(255,255,255,.22); }

        /* Theme toggle */
        .theme-btn {
            width: 30px; height: 30px; border-radius: 7px; flex-shrink: 0;
            background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
            color: rgba(255,255,255,.45); cursor: pointer; transition: .15s;
            display: flex; align-items: center; justify-content: center;
        }
        .theme-btn:hover { background: rgba(255,255,255,.10); color: #fff; }

        /* ────────── Right panel ────────── */
        .right {
            width: 480px; flex-shrink: 0;
            background: var(--right-bg);
            border-left: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            padding: 48px 52px; position: relative; overflow: hidden;
        }

        /* Subtle dot texture */
        .right::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background-image: radial-gradient(rgba(108,99,255,.05) 1px, transparent 1px);
            background-size: 22px 22px;
        }

        .form-box { width: 100%; max-width: 348px; position: relative; }

        /* Form header */
        .form-head { margin-bottom: 30px; }
        .form-head-label {
            display: inline-block; font-size: 10.5px; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
            color: var(--primary); margin-bottom: 10px;
        }
        .form-head h2 {
            font-size: 23px; font-weight: 700; color: var(--text);
            letter-spacing: -.5px; line-height: 1.2;
        }
        .form-head p { font-size: 13px; color: var(--text-muted); margin-top: 6px; line-height: 1.5; }

        /* Fields */
        .field { margin-bottom: 13px; }
        .field-top {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 7px;
        }
        .field-top label { font-size: 12.5px; font-weight: 500; color: var(--text-sub); }
        .forgot { font-size: 12px; font-weight: 500; color: var(--primary); text-decoration: none; opacity: .75; transition: opacity .15s; }
        .forgot:hover { opacity: 1; }

        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); display: flex; align-items: center;
            pointer-events: none; transition: color .15s;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%; padding: 11px 13px 11px 40px;
            background: var(--input-bg); border: 1px solid var(--input-border);
            border-radius: 9px; color: var(--text); font-size: 13.5px;
            font-family: 'Inter', sans-serif; outline: none;
            transition: border-color .18s, background .18s, box-shadow .18s;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary);
            background: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(108,99,255,.12);
        }
        input::placeholder { color: var(--text-muted); }
        input.is-invalid   { border-color: var(--error); box-shadow: 0 0 0 3px rgba(239,68,68,.08); }
        .field-error { font-size: 12px; color: var(--error); margin-top: 5px; }

        /* Remember me */
        .remember { display: flex; align-items: center; gap: 8px; margin: 18px 0 20px; }
        .remember input[type="checkbox"] { accent-color: var(--primary); width: 14px; height: 14px; cursor: pointer; flex-shrink: 0; }
        .remember label { font-size: 13px; color: var(--text-sub); cursor: pointer; user-select: none; }

        /* Submit */
        .btn-submit {
            width: 100%; padding: 12px 16px;
            background: var(--primary); color: #fff;
            font-size: 14px; font-weight: 600; font-family: 'Inter', sans-serif;
            border: none; border-radius: 9px; cursor: pointer;
            letter-spacing: .1px; position: relative; overflow: hidden;
            transition: background .18s, box-shadow .18s, transform .1s;
        }
        .btn-submit::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,.10) 0%, transparent 100%);
            pointer-events: none;
        }
        .btn-submit:hover { background: var(--primary-hover); box-shadow: 0 6px 24px var(--primary-glow); transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); box-shadow: none; }

        /* Divider */
        .sep { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
        .sep-line { flex: 1; height: 1px; background: var(--border); }
        .sep span { font-size: 11px; color: var(--text-muted); white-space: nowrap; letter-spacing: .3px; }

        /* Demo accounts */
        .demo { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .demo-header {
            padding: 8px 14px; background: var(--surface);
            border-bottom: 1px solid var(--surface-border);
            font-size: 10.5px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: .9px;
        }
        .demo-row {
            display: flex; align-items: center; padding: 9px 14px; gap: 10px;
            border-bottom: 1px solid var(--surface-border);
            cursor: pointer; transition: background .12s;
        }
        .demo-row:last-child { border-bottom: none; }
        .demo-row:hover { background: var(--surface); }

        .role-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .dot-admin   { background: #a78bfa; box-shadow: 0 0 5px rgba(167,139,250,.7); }
        .dot-owner   { background: #4ade80; box-shadow: 0 0 5px rgba(74,222,128,.6); }
        .dot-trainer { background: #facc15; box-shadow: 0 0 5px rgba(250,204,21,.6); }
        .dot-member  { background: #60a5fa; box-shadow: 0 0 5px rgba(96,165,250,.6); }

        .demo-role  { font-size: 12px; font-weight: 600; color: var(--text-sub); width: 52px; }
        .demo-email { font-size: 11.5px; color: var(--text-muted); flex: 1; font-variant-numeric: tabular-nums; }
        .demo-use   { font-size: 11px; font-weight: 600; color: var(--primary); background: none; border: none; cursor: pointer; font-family: 'Inter', sans-serif; opacity: .75; padding: 0; }
        .demo-use:hover { opacity: 1; }

        /* Alert */
        .alert { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; }
        .alert-success { background: rgba(74,222,128,.07); border: 1px solid rgba(74,222,128,.18); color: #4ade80; }

        @media (max-width: 860px) {
            .left  { display: none; }
            .right { width: 100%; border-left: none; padding: 40px 24px; }
        }
    </style>
</head>
<body>
<div class="wrap">

    {{-- ── Left panel ────────────────────────────────────── --}}
    <div class="left">
        <div class="left-inner">

            <div class="brand">
                <div class="brand-mark">
                    {{-- Dumbbell icon --}}
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6.5 6.5h1v11h-1z" fill="#fff" stroke="none"/>
                        <path d="M16.5 6.5h1v11h-1z" fill="#fff" stroke="none"/>
                        <rect x="3" y="9" width="4" height="6" rx="1.5"/>
                        <rect x="17" y="9" width="4" height="6" rx="1.5"/>
                        <line x1="7.5" y1="12" x2="16.5" y2="12" stroke-width="2"/>
                    </svg>
                </div>
                <div class="brand-text">
                    <div class="brand-name">Fitness Studio</div>
                    <div class="brand-tagline">Gym Management Platform</div>
                </div>
            </div>

            <div class="hero">
                <div class="kicker">
                    <span class="kicker-bar"></span>
                    Gym Management
                </div>

                <h1>Run your gym,<br>not your <em>spreadsheets.</em></h1>

                <p class="hero-desc">
                    Everything a gym owner needs — memberships, trainers,
                    biometric attendance, and revenue — in one platform
                    that actually makes sense to use.
                </p>

                <div class="features">
                    <div class="feat">
                        <div class="feat-dot">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a78bfa" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <div>
                            <div class="feat-title">Members & Trainers</div>
                            <div class="feat-desc">Memberships, sessions, commissions — all tracked automatically</div>
                        </div>
                    </div>
                    <div class="feat">
                        <div class="feat-dot">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a78bfa" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18" stroke-width="2.5"/><path d="M9 7h6M9 11h4"/></svg>
                        </div>
                        <div>
                            <div class="feat-title">Biometric Check-in</div>
                            <div class="feat-desc">ZKTeco devices push attendance in real time, zero manual entry</div>
                        </div>
                    </div>
                    <div class="feat">
                        <div class="feat-dot">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a78bfa" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        </div>
                        <div>
                            <div class="feat-title">Revenue & Reports</div>
                            <div class="feat-desc">Daily revenue, membership trends, attendance heatmaps</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="left-footer">
                <span class="left-footer-copy">&copy; {{ date('Y') }} Fitness Studio</span>
                <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme">
                    <svg id="icon-sun" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    <svg id="icon-moon" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                    </svg>
                </button>
            </div>

        </div>
    </div>

    {{-- ── Right panel ────────────────────────────────────── --}}
    <div class="right">
        <div class="form-box">

            <div class="form-head">
                <span class="form-head-label">Welcome back</span>
                <h2>Sign in to your account</h2>
                <p>Enter your credentials to access the dashboard</p>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="field">
                    <div class="field-top">
                        <label for="email">Email address</label>
                    </div>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" id="email" name="email"
                            class="@error('email') is-invalid @enderror"
                            placeholder="you@example.com"
                            value="{{ old('email') }}"
                            autocomplete="email" autofocus>
                    </div>
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <div class="field-top">
                        <label for="password">Password</label>
                        <a href="#" class="forgot">Forgot password?</a>
                    </div>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        </span>
                        <input type="password" id="password" name="password"
                            class="@error('password') is-invalid @enderror"
                            placeholder="••••••••"
                            autocomplete="current-password">
                    </div>
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="remember">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Keep me signed in</label>
                </div>

                <button type="submit" class="btn-submit">Sign in</button>
            </form>

            <div class="sep">
                <div class="sep-line"></div>
                <span>Demo accounts &middot; password: <strong>password</strong></span>
                <div class="sep-line"></div>
            </div>

            <div class="demo">
                <div class="demo-header">Quick access</div>
<div class="demo-row" onclick="fillLogin('owner@demogym.com')">
                    <span class="role-dot dot-owner"></span>
                    <span class="demo-role">Owner</span>
                    <span class="demo-email">owner@demogym.com</span>
                    <button class="demo-use">Use &rarr;</button>
                </div>
                <div class="demo-row" onclick="fillLogin('trainer@demogym.com')">
                    <span class="role-dot dot-trainer"></span>
                    <span class="demo-role">Trainer</span>
                    <span class="demo-email">trainer@demogym.com</span>
                    <button class="demo-use">Use &rarr;</button>
                </div>
                <div class="demo-row" onclick="fillLogin('member@demogym.com')">
                    <span class="role-dot dot-member"></span>
                    <span class="demo-role">Member</span>
                    <span class="demo-email">member@demogym.com</span>
                    <button class="demo-use">Use &rarr;</button>
                </div>
            </div>

        </div>
    </div>

</div>
<script>
    function fillLogin(email) {
        document.getElementById('email').value    = email;
        document.getElementById('password').value = 'password';
    }

    function toggleTheme() {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        syncIcons(next);
    }

    function syncIcons(t) {
        document.getElementById('icon-sun').style.display  = t === 'light' ? 'block' : 'none';
        document.getElementById('icon-moon').style.display = t === 'dark'  ? 'block' : 'none';
    }

    syncIcons(document.documentElement.getAttribute('data-theme') || 'dark');
</script>
</body>
</html>
