<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Fitness Studio</title>
    <script>(function(){const t=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme:light)').matches?'light':'dark');document.documentElement.setAttribute('data-theme',t);})();</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        
        :root, [data-theme="dark"] {
            --bg:            #0f1020;
            --left-bg:       #111228;
            --right-bg:      #161728;
            --border:        rgba(255,255,255,0.08);
            --input-bg:      rgba(255,255,255,0.05);
            --input-border:  rgba(255,255,255,0.10);
            --input-focus-bg:rgba(108,99,255,0.06);
            --primary:       #6C63FF;
            --primary-hover: #5a52e0;
            --primary-glow:  rgba(108,99,255,0.30);
            --text:          #f1f5f9;
            --text-sub:      #94a3b8;
            --text-muted:    #64748b;
            --error:         #f87171;
            --success:       #4ade80;
            --badge-bg:      rgba(255,255,255,0.06);
            --theme-btn:     rgba(255,255,255,0.06);
        }

        [data-theme="light"] {
            --bg:            #f4f6fb;
            --left-bg:       #1e1b4b;
            --right-bg:      #ffffff;
            --border:        rgba(0,0,0,0.08);
            --input-bg:      #f8fafc;
            --input-border:  rgba(0,0,0,0.12);
            --input-focus-bg:rgba(108,99,255,0.04);
            --primary:       #6C63FF;
            --primary-hover: #5a52e0;
            --primary-glow:  rgba(108,99,255,0.25);
            --text:          #111827;
            --text-sub:      #4b5563;
            --text-muted:    #9ca3af;
            --error:         #ef4444;
            --success:       #16a34a;
            --badge-bg:      #f1f5f9;
            --theme-btn:     rgba(255,255,255,0.12);
        }

        html, body { height: 100%; font-family: 'Inter', sans-serif; background: var(--bg); overflow: hidden; }

        /* ── Layout ─────────────────────────────────────────────── */
        .wrap { display: flex; height: 100vh; }

        /* ── Left panel ─────────────────────────────────────────── */
        .left {
            flex: 1;
            background: var(--left-bg);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 56px;
            position: relative;
            overflow: hidden;
        }

        /* Mesh gradient background */
        .left::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% 20%, rgba(108,99,255,0.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 90% 80%, rgba(124,58,237,0.14) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 50% 50%, rgba(59,130,246,0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Subtle dot pattern */
        .left::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        .left-inner { position: relative; z-index: 1; display: flex; flex-direction: column; height: 100%; justify-content: space-between; }

        /* Brand */
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-mark {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, #6C63FF, #a855f7);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(108,99,255,0.4);
        }
        .brand-text { line-height: 1.2; }
        .brand-name    { font-size: 15px; font-weight: 700; color: #fff; letter-spacing: -.2px; }
        .brand-tagline { font-size: 11px; color: rgba(255,255,255,0.45); margin-top: 1px; }

        /* Hero text */
        .hero { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 40px 0; max-width: 400px; }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 600; letter-spacing: .8px;
            text-transform: uppercase; color: rgba(167,139,250,0.9);
            margin-bottom: 20px;
        }
        .hero-eyebrow span {
            width: 20px; height: 1px; background: rgba(167,139,250,0.5);
        }
        .hero h1 {
            font-size: 36px; font-weight: 700; color: #fff;
            line-height: 1.2; letter-spacing: -1px; margin-bottom: 16px;
        }
        .hero h1 strong {
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa 0%, #f472b6 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .hero-sub {
            font-size: 14px; color: rgba(255,255,255,0.5);
            line-height: 1.7; margin-bottom: 36px;
        }

        /* Feature list */
        .features { display: flex; flex-direction: column; gap: 14px; }
        .feat {
            display: flex; align-items: flex-start; gap: 12px;
        }
        .feat-icon {
            width: 32px; height: 32px; flex-shrink: 0;
            border-radius: 8px; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; justify-content: center;
        }
        .feat-icon svg { color: #a78bfa; }
        .feat-body {}
        .feat-title { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.85); }
        .feat-desc  { font-size: 12px; color: rgba(255,255,255,0.38); margin-top: 1px; }

        /* Stats row */
        .stats {
            display: flex; gap: 0;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; overflow: hidden;
            background: rgba(255,255,255,0.03);
        }
        .stat {
            flex: 1; padding: 16px 20px; text-align: center;
            border-right: 1px solid rgba(255,255,255,0.08);
        }
        .stat:last-child { border-right: none; }
        .stat-val { font-size: 20px; font-weight: 700; color: #fff; letter-spacing: -.5px; }
        .stat-lbl { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 2px; }

        /* Theme toggle */
        .theme-btn {
            position: absolute; top: 44px; right: 20px; z-index: 10;
            width: 34px; height: 34px; border-radius: 8px;
            background: var(--theme-btn); border: 1px solid rgba(255,255,255,0.10);
            color: rgba(255,255,255,0.6); cursor: pointer; transition: .15s;
            display: flex; align-items: center; justify-content: center;
        }
        .theme-btn:hover { background: rgba(255,255,255,0.12); color: #fff; }

        /* ── Right panel ─────────────────────────────────────────── */
        .right {
            width: 460px; flex-shrink: 0;
            background: var(--right-bg);
            border-left: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            padding: 40px 48px;
            overflow-y: auto;
        }

        .form-box { width: 100%; max-width: 340px; }

        /* Form header */
        .form-head { margin-bottom: 28px; }
        .form-head h2 { font-size: 22px; font-weight: 700; color: var(--text); letter-spacing: -.4px; }
        .form-head p  { font-size: 13px; color: var(--text-sub); margin-top: 5px; line-height: 1.5; }

        /* Inputs */
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 13px; font-weight: 500; color: var(--text-sub); margin-bottom: 6px; }

        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); display: flex; align-items: center; pointer-events: none;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%; padding: 10px 12px 10px 38px;
            background: var(--input-bg); border: 1px solid var(--input-border);
            border-radius: 8px; color: var(--text); font-size: 13.5px;
            font-family: 'Inter', sans-serif; outline: none;
            transition: border-color .18s, background .18s, box-shadow .18s;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary);
            background: var(--input-focus-bg);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.12);
        }
        input::placeholder { color: var(--text-muted); }
        input.is-invalid   { border-color: var(--error); box-shadow: 0 0 0 3px rgba(239,68,68,0.10); }
        .field-error { font-size: 12px; color: var(--error); margin-top: 4px; }

        /* Options row */
        .options { display: flex; align-items: center; justify-content: space-between; margin: 18px 0; }
        .check-label { display: flex; align-items: center; gap: 7px; cursor: pointer; font-size: 13px; color: var(--text-sub); }
        .check-label input { accent-color: var(--primary); width: 14px; height: 14px; }
        .forgot { font-size: 13px; font-weight: 500; color: var(--primary); text-decoration: none; }
        .forgot:hover { text-decoration: underline; }

        /* Submit */
        .btn-submit {
            width: 100%; padding: 11px 16px;
            background: var(--primary); color: #fff;
            font-size: 14px; font-weight: 600; font-family: 'Inter', sans-serif;
            border: none; border-radius: 8px; cursor: pointer;
            transition: background .18s, box-shadow .18s, transform .1s;
            letter-spacing: .1px;
        }
        .btn-submit:hover { background: var(--primary-hover); box-shadow: 0 6px 20px var(--primary-glow); transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); box-shadow: none; }

        /* Divider */
        .or { display: flex; align-items: center; gap: 10px; margin: 22px 0; }
        .or-line { flex: 1; height: 1px; background: var(--border); }
        .or span { font-size: 11px; color: var(--text-muted); white-space: nowrap; }

        /* Demo accounts */
        .demo {
            border: 1px solid var(--border); border-radius: 10px; overflow: hidden;
        }
        .demo-header {
            padding: 9px 14px; background: var(--badge-bg);
            border-bottom: 1px solid var(--border);
            font-size: 11px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: .7px;
        }
        .demo-row {
            display: flex; align-items: center;
            padding: 10px 14px; gap: 10px;
            border-bottom: 1px solid var(--border); cursor: pointer;
            transition: background .12s;
        }
        .demo-row:last-child { border-bottom: none; }
        .demo-row:hover { background: var(--badge-bg); }

        .role-dot {
            width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
        }
        .dot-admin   { background: #a78bfa; box-shadow: 0 0 6px rgba(167,139,250,0.6); }
        .dot-owner   { background: #4ade80; box-shadow: 0 0 6px rgba(74,222,128,0.5); }
        .dot-trainer { background: #facc15; box-shadow: 0 0 6px rgba(250,204,21,0.5); }
        .dot-member  { background: #60a5fa; box-shadow: 0 0 6px rgba(96,165,250,0.5); }

        .demo-role  { font-size: 12px; font-weight: 600; color: var(--text-sub); width: 54px; }
        .demo-email { font-size: 12px; color: var(--text-muted); flex: 1; }
        .demo-use   {
            font-size: 11px; font-weight: 600; color: var(--primary);
            background: none; border: none; cursor: pointer;
            font-family: 'Inter', sans-serif; padding: 0;
        }
        .demo-use:hover { text-decoration: underline; }

        /* Alert */
        .alert { padding: 10px 14px; border-radius: 7px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: rgba(74,222,128,0.08); border: 1px solid rgba(74,222,128,0.2); color: var(--success); }

        @media (max-width: 860px) {
            .left  { display: none; }
            .right { width: 100%; border-left: none; }
        }
    </style>
</head>
<body>
<div class="wrap">

    {{-- Left panel --}}
    <div class="left">
        <div class="left-inner">

            {{-- Brand --}}
            <div class="brand">
                <div class="brand-mark">💪</div>
                <div class="brand-text">
                    <div class="brand-name">Fitness Studio</div>
                    <div class="brand-tagline">Gym Management Platform</div>
                </div>
            </div>

            {{-- Hero --}}
            <div class="hero">
                <div class="hero-eyebrow"><span></span> Multi-tenant SaaS</div>
                <h1>The smarter way to run <strong>your gym</strong></h1>
                <p class="hero-sub">Manage members, trainers, attendance, and revenue — all from one unified platform built for modern gym businesses.</p>

                <div class="features">
                    <div class="feat">
                        <div class="feat-icon">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <div class="feat-body">
                            <div class="feat-title">Member & Trainer Management</div>
                            <div class="feat-desc">Full CRUD, memberships, session scheduling</div>
                        </div>
                    </div>
                    <div class="feat">
                        <div class="feat-icon">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="feat-body">
                            <div class="feat-title">Biometric Attendance Sync</div>
                            <div class="feat-desc">ZKTeco device integration, real-time punch</div>
                        </div>
                    </div>
                    <div class="feat">
                        <div class="feat-icon">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        </div>
                        <div class="feat-body">
                            <div class="feat-title">Analytics & Reporting</div>
                            <div class="feat-desc">Revenue, growth, and attendance charts</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="stats">
                <div class="stat">
                    <div class="stat-val">500+</div>
                    <div class="stat-lbl">Gyms</div>
                </div>
                <div class="stat">
                    <div class="stat-val">50K+</div>
                    <div class="stat-lbl">Members</div>
                </div>
                <div class="stat">
                    <div class="stat-val">99.9%</div>
                    <div class="stat-lbl">Uptime</div>
                </div>
            </div>

        </div>

        {{-- Theme toggle --}}
        <button class="theme-btn" id="theme-btn" onclick="toggleTheme()" title="Toggle theme">
            <svg id="icon-sun" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg id="icon-moon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        </button>
    </div>

    {{-- Right panel --}}
    <div class="right">
        <div class="form-box">

            <div class="form-head">
                <h2>Sign in to your account</h2>
                <p>Enter your credentials to continue</p>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="field">
                    <label for="email">Email address</label>
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
                    <label for="password">Password</label>
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

                <div class="options">
                    <label class="check-label">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit">Sign in</button>
            </form>

            <div class="or">
                <div class="or-line"></div>
                <span>Demo access · password: password</span>
                <div class="or-line"></div>
            </div>

            <div class="demo">
                <div class="demo-header">Quick login</div>
                <div class="demo-row" onclick="fillLogin('admin@fitnessstudio.com')">
                    <span class="role-dot dot-admin"></span>
                    <span class="demo-role">Admin</span>
                    <span class="demo-email">admin@fitnessstudio.com</span>
                    <button class="demo-use">Use</button>
                </div>
                <div class="demo-row" onclick="fillLogin('owner@demogym.com')">
                    <span class="role-dot dot-owner"></span>
                    <span class="demo-role">Owner</span>
                    <span class="demo-email">owner@demogym.com</span>
                    <button class="demo-use">Use</button>
                </div>
                <div class="demo-row" onclick="fillLogin('trainer@demogym.com')">
                    <span class="role-dot dot-trainer"></span>
                    <span class="demo-role">Trainer</span>
                    <span class="demo-email">trainer@demogym.com</span>
                    <button class="demo-use">Use</button>
                </div>
                <div class="demo-row" onclick="fillLogin('member@demogym.com')">
                    <span class="role-dot dot-member"></span>
                    <span class="demo-role">Member</span>
                    <span class="demo-email">member@demogym.com</span>
                    <button class="demo-use">Use</button>
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
        const html  = document.documentElement;
        const next  = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
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
