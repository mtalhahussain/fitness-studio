<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Fitness Studio</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #0f0f1a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #1a1a2e; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 40px 48px; text-align: center; max-width: 480px; }
        .icon { font-size: 48px; margin-bottom: 20px; }
        h1 { font-size: 24px; font-weight: 700; margin-bottom: 10px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 8px; }
        .badge { display: inline-block; background: rgba(108,99,255,0.15); color: #a78bfa; padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; margin-bottom: 24px; }
        .logout-btn { padding: 10px 28px; background: linear-gradient(135deg, #6C63FF, #8b5cf6); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">💪</div>
        <h1>Welcome, {{ Auth::user()->name }}</h1>
        <div class="badge">{{ ucfirst(Auth::user()->getRoleNames()->first() ?? 'user') }}</div>
        <p>You are successfully logged in to Fitness Studio SaaS.</p>
        @if(Auth::user()->gym)
            <p><strong style="color:#e2e8f0">Gym:</strong> {{ Auth::user()->gym->name }}</p>
        @endif
        <br>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn">Sign Out</button>
        </form>
    </div>
</body>
</html>
