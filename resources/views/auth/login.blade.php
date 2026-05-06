<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Fitness Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #6C63FF;
            --primary-dark: #574fd6;
            --accent: #FF6584;
            --dark: #0f0f1a;
            --card: #1a1a2e;
            --surface: #16213e;
            --border: rgba(255,255,255,0.08);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --success: #10b981;
            --error: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* Left Panel */
        .panel-left {
            flex: 1;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 40%, #16213e 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }

        .panel-left::before {
            content: '';
            position: absolute;
            top: -200px;
            left: -200px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(108,99,255,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .panel-left::after {
            content: '';
            position: absolute;
            bottom: -150px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,101,132,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 60px;
            z-index: 1;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.5px;
        }

        .brand-tagline {
            font-size: 12px;
            color: var(--muted);
            font-weight: 400;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .hero-content {
            z-index: 1;
            text-align: center;
            max-width: 440px;
        }

        .hero-content h1 {
            font-size: 42px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
            letter-spacing: -1px;
            margin-bottom: 20px;
        }

        .hero-content h1 span {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 48px;
        }

        .stats {
            display: flex;
            gap: 32px;
            justify-content: center;
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .stat-divider {
            width: 1px;
            background: var(--border);
            align-self: stretch;
        }

        /* Floating cards */
        .floating-card {
            position: absolute;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px 20px;
            backdrop-filter: blur(10px);
            z-index: 1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-card:nth-child(1) { top: 15%; right: 8%; animation-delay: 0s; }
        .floating-card:nth-child(2) { bottom: 20%; left: 5%; animation-delay: 3s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
        }

        .fc-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .fc-value { font-size: 20px; font-weight: 700; color: var(--text); margin-top: 4px; }
        .fc-change { font-size: 12px; color: var(--success); margin-top: 4px; }

        /* Right Panel */
        .panel-right {
            width: 480px;
            flex-shrink: 0;
            background: #0d0d1a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 48px;
            border-left: 1px solid var(--border);
        }

        .login-box {
            width: 100%;
            max-width: 380px;
        }

        .login-header {
            margin-bottom: 36px;
        }

        .login-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: var(--muted);
            font-size: 14px;
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 16px;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            padding: 13px 14px 13px 44px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary);
            background: rgba(108,99,255,0.05);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.12);
        }

        .form-input::placeholder { color: #475569; }

        .form-input.is-invalid {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(239,68,68,0.12);
        }

        .error-msg {
            font-size: 12px;
            color: var(--error);
            margin-top: 6px;
        }

        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .remember-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-wrap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .remember-wrap span {
            font-size: 13px;
            color: var(--muted);
        }

        .forgot-link {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover { text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.3px;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(108,99,255,0.4);
        }

        .btn-login:active { transform: translateY(0); }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0;
        }

        .divider-line { flex: 1; height: 1px; background: var(--border); }
        .divider span { font-size: 12px; color: var(--muted); white-space: nowrap; }

        .demo-accounts {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }

        .demo-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
        }

        .demo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: 0.15s;
        }

        .demo-item:last-child { border-bottom: none; }
        .demo-item:hover .demo-email { color: var(--primary); }

        .demo-role {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .role-admin { background: rgba(108,99,255,0.15); color: #a78bfa; }
        .role-owner { background: rgba(16,185,129,0.15); color: #34d399; }
        .role-trainer { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .role-member { background: rgba(59,130,246,0.15); color: #60a5fa; }

        .demo-email { font-size: 12px; color: var(--muted); }

        .demo-use-btn {
            font-size: 11px;
            color: var(--primary);
            background: none;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            padding: 2px 8px;
            border-radius: 4px;
            transition: 0.15s;
        }

        .demo-use-btn:hover { background: rgba(108,99,255,0.1); }

        @media (max-width: 900px) {
            .panel-left { display: none; }
            .panel-right { width: 100%; border-left: none; }
        }
    </style>
</head>
<body>

    <!-- Left Panel -->
    <div class="panel-left">
        <div class="floating-card">
            <div class="fc-label">Active Members</div>
            <div class="fc-value">2,847</div>
            <div class="fc-change">↑ 12% this month</div>
        </div>
        <div class="floating-card">
            <div class="fc-label">Revenue</div>
            <div class="fc-value">$48,290</div>
            <div class="fc-change">↑ 8.3% vs last month</div>
        </div>

        <div class="brand">
            <div class="brand-icon">💪</div>
            <div>
                <div class="brand-name">Fitness Studio</div>
                <div class="brand-tagline">Gym Management SaaS</div>
            </div>
        </div>

        <div class="hero-content">
            <h1>Manage Your <span>Gym Empire</span> Smarter</h1>
            <p>All-in-one platform for gym owners, trainers, and members. Track memberships, schedule classes, and grow your business.</p>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value">500+</div>
                    <div class="stat-label">Gyms</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat">
                    <div class="stat-value">50K+</div>
                    <div class="stat-label">Members</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat">
                    <div class="stat-value">99.9%</div>
                    <div class="stat-label">Uptime</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="panel-right">
        <div class="login-box">
            <div class="login-header">
                <h2>Welcome back 👋</h2>
                <p>Sign in to your account to continue</p>
            </div>

            @if(session('status'))
                <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:#34d399;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrap">
                        <span class="input-icon">✉</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="form-input @error('email') is-invalid @enderror"
                            placeholder="you@example.com"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            autofocus
                        >
                    </div>
                    @error('email')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-input @error('password') is-invalid @enderror"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                        >
                    </div>
                    @error('password')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-row">
                    <label class="remember-wrap">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">Sign In →</button>
            </form>

            <div class="divider">
                <div class="divider-line"></div>
                <span>Demo Accounts (password: password)</span>
                <div class="divider-line"></div>
            </div>

            <div class="demo-accounts">
                <div class="demo-title">Quick Login</div>

                <div class="demo-item" onclick="fillLogin('admin@fitnessstudio.com')">
                    <span class="demo-role role-admin">⚡ Admin</span>
                    <span class="demo-email">admin@fitnessstudio.com</span>
                    <button class="demo-use-btn">Use →</button>
                </div>
                <div class="demo-item" onclick="fillLogin('owner@demogym.com')">
                    <span class="demo-role role-owner">🏋 Owner</span>
                    <span class="demo-email">owner@demogym.com</span>
                    <button class="demo-use-btn">Use →</button>
                </div>
                <div class="demo-item" onclick="fillLogin('trainer@demogym.com')">
                    <span class="demo-role role-trainer">🎯 Trainer</span>
                    <span class="demo-email">trainer@demogym.com</span>
                    <button class="demo-use-btn">Use →</button>
                </div>
                <div class="demo-item" onclick="fillLogin('member@demogym.com')">
                    <span class="demo-role role-member">👤 Member</span>
                    <span class="demo-email">member@demogym.com</span>
                    <button class="demo-use-btn">Use →</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fillLogin(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'password';
            document.getElementById('email').focus();
        }
    </script>
</body>
</html>
