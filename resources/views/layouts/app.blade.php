<!DOCTYPE html>
<html lang="en" x-data x-init="initApp()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Fitness Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #080813;
            --sidebar-bg:  #0c0c1c;
            --card:        #10101f;
            --card-hover:  #13132a;
            --border:      rgba(255,255,255,0.06);
            --border-hover:rgba(255,255,255,0.12);
            --primary:     #6C63FF;
            --primary-dim: rgba(108,99,255,0.12);
            --primary-glow:rgba(108,99,255,0.3);
            --accent:      #f472b6;
            --success:     #22c55e;
            --success-dim: rgba(34,197,94,0.12);
            --warning:     #eab308;
            --warning-dim: rgba(234,179,8,0.12);
            --error:       #ef4444;
            --error-dim:   rgba(239,68,68,0.12);
            --info:        #3b82f6;
            --info-dim:    rgba(59,130,246,0.12);
            --text:        #e2e8f0;
            --text-muted:  #64748b;
            --text-dim:    #94a3b8;
            --sidebar-w:   248px;
            --topbar-h:    60px;
            --radius:      12px;
            --radius-sm:   8px;
        }

        html, body { height: 100%; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }

        ::selection { background: var(--primary); color: #fff; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* ── Layout ─────────────────────────────────────────────── */
        .layout { display: flex; height: 100vh; overflow: hidden; }

        /* ── Sidebar ─────────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            flex-shrink: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: width .25s ease, transform .25s ease;
            overflow: hidden;
            position: relative;
            z-index: 100;
        }
        .sidebar.collapsed { width: 64px; }

        .sidebar-brand {
            padding: 20px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            min-height: var(--topbar-h);
        }
        .brand-icon {
            width: 36px; height: 36px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .brand-text { white-space: nowrap; overflow: hidden; }
        .brand-text .name  { font-size: 15px; font-weight: 700; color: var(--text); }
        .brand-text .tagline { font-size: 10px; color: var(--text-muted); letter-spacing: .5px; text-transform: uppercase; }

        .sidebar-nav { flex: 1; padding: 12px 8px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px; }

        .nav-section { margin-top: 16px; margin-bottom: 4px; padding: 0 8px; }
        .nav-section span {
            font-size: 10px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: .8px;
            white-space: nowrap; overflow: hidden;
        }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 10px; border-radius: var(--radius-sm);
            color: var(--text-muted); text-decoration: none;
            transition: all .15s ease; cursor: pointer;
            position: relative; white-space: nowrap;
        }
        .nav-item:hover { background: rgba(255,255,255,0.04); color: var(--text); }
        .nav-item.active {
            background: var(--primary-dim); color: var(--primary);
        }
        .nav-item.active::before {
            content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 20px; background: var(--primary); border-radius: 0 3px 3px 0;
        }
        .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
        .nav-item .label { font-size: 13.5px; font-weight: 500; overflow: hidden; }
        .nav-badge {
            margin-left: auto; background: var(--primary); color: #fff;
            font-size: 10px; font-weight: 600; padding: 1px 6px;
            border-radius: 20px; min-width: 18px; text-align: center;
        }

        .sidebar-footer {
            padding: 12px 8px;
            border-top: 1px solid var(--border);
        }
        .user-card {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px; border-radius: var(--radius-sm);
            cursor: pointer; transition: .15s;
        }
        .user-card:hover { background: rgba(255,255,255,0.04); }
        .user-avatar {
            width: 32px; height: 32px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #fff;
        }
        .user-info { flex: 1; overflow: hidden; min-width: 0; }
        .user-info .uname { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-info .urole { font-size: 11px; color: var(--text-muted); }

        /* ── Main content ─────────────────────────────────────────── */
        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

        .topbar {
            height: var(--topbar-h); flex-shrink: 0;
            background: var(--sidebar-bg); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 20px; gap: 16px;
        }
        .topbar-toggle {
            width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-sm); border: none; background: transparent;
            color: var(--text-muted); cursor: pointer; transition: .15s;
        }
        .topbar-toggle:hover { background: rgba(255,255,255,0.06); color: var(--text); }
        .topbar-title { font-size: 15px; font-weight: 600; color: var(--text); flex: 1; }

        .topbar-actions { display: flex; align-items: center; gap: 8px; }
        .topbar-btn {
            width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-sm); border: 1px solid var(--border);
            background: transparent; color: var(--text-muted); cursor: pointer; transition: .15s;
        }
        .topbar-btn:hover { border-color: var(--border-hover); color: var(--text); background: rgba(255,255,255,0.04); }

        .page-content { flex: 1; overflow-y: auto; padding: 24px; }

        /* ── Cards ───────────────────────────────────────────────── */
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px;
            transition: border-color .2s ease;
        }
        .card:hover { border-color: var(--border-hover); }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .card-title { font-size: 15px; font-weight: 600; color: var(--text); }
        .card-subtitle { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* ── Stat cards ──────────────────────────────────────────── */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
        .stat-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 18px 20px;
            display: flex; align-items: flex-start; gap: 14px;
            transition: all .2s ease; cursor: default;
        }
        .stat-card:hover { border-color: var(--border-hover); transform: translateY(-2px); }
        .stat-icon {
            width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .stat-content .label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .stat-content .value { font-size: 26px; font-weight: 700; color: var(--text); line-height: 1.1; margin-top: 2px; }
        .stat-content .change { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
        .stat-content .change.up { color: var(--success); }
        .stat-content .change.warn { color: var(--warning); }

        /* ── Table ───────────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 10px 14px; text-align: left;
            font-size: 11px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: .6px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 12px 14px; color: var(--text-dim); vertical-align: middle; }
        td .cell-main { font-weight: 500; color: var(--text); }
        td .cell-sub  { font-size: 12px; color: var(--text-muted); margin-top: 1px; }

        /* ── Badges ──────────────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-green   { background: var(--success-dim); color: var(--success); }
        .badge-red     { background: var(--error-dim);   color: var(--error);   }
        .badge-yellow  { background: var(--warning-dim); color: var(--warning); }
        .badge-purple  { background: var(--primary-dim); color: var(--primary); }
        .badge-blue    { background: var(--info-dim);    color: var(--info);    }
        .badge-gray    { background: rgba(255,255,255,0.06); color: var(--text-muted); }

        /* ── Buttons ─────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: var(--radius-sm); border: none;
            font-size: 13px; font-weight: 600; cursor: pointer; transition: all .15s ease;
            font-family: 'Inter', sans-serif; text-decoration: none;
        }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-primary  { background: var(--primary); color: #fff; }
        .btn-primary:hover:not(:disabled)  { background: #574fd6; box-shadow: 0 4px 16px var(--primary-glow); }
        .btn-success  { background: var(--success); color: #fff; }
        .btn-success:hover:not(:disabled)  { background: #16a34a; }
        .btn-outline  { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
        .btn-outline:hover:not(:disabled)  { border-color: var(--border-hover); color: var(--text); background: rgba(255,255,255,0.04); }
        .btn-danger   { background: transparent; border: 1px solid rgba(239,68,68,0.3); color: var(--error); }
        .btn-danger:hover:not(:disabled)   { background: var(--error-dim); }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        .btn-icon { padding: 6px; border-radius: var(--radius-sm); }

        /* ── Forms ───────────────────────────────────────────────── */
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 12px; font-weight: 500; color: var(--text-dim); }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: 9px 12px;
            background: rgba(255,255,255,0.04); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text); font-size: 13px;
            font-family: 'Inter', sans-serif; outline: none; transition: .15s;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary); background: rgba(108,99,255,0.05);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
        }
        .form-input::placeholder { color: var(--text-muted); }
        .form-select option { background: #1a1a2e; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }

        /* ── Search bar ──────────────────────────────────────────── */
        .search-wrap { position: relative; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
        .search-input { padding-left: 34px !important; }

        /* ── Modal ───────────────────────────────────────────────── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.7);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000; padding: 20px; backdrop-filter: blur(4px);
        }
        .modal {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 16px; width: 100%; max-width: 520px;
            max-height: 90vh; overflow-y: auto; padding: 28px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.5);
        }
        .modal-lg { max-width: 720px; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .modal-title { font-size: 17px; font-weight: 700; color: var(--text); }
        .modal-close {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: 6px; border: none; background: transparent; color: var(--text-muted);
            cursor: pointer; font-size: 18px; transition: .15s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.06); color: var(--text); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--border); }

        /* ── Toast ───────────────────────────────────────────────── */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 14px 16px; border-radius: 10px; min-width: 280px; max-width: 360px;
            border: 1px solid; backdrop-filter: blur(20px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            animation: toastIn .25s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes toastIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toast-success { background: rgba(10,20,14,0.95); border-color: rgba(34,197,94,0.3); }
        .toast-error   { background: rgba(20,10,10,0.95); border-color: rgba(239,68,68,0.3); }
        .toast-warning { background: rgba(20,18,8,0.95);  border-color: rgba(234,179,8,0.3); }
        .toast-info    { background: rgba(10,12,22,0.95); border-color: rgba(59,130,246,0.3); }
        .toast-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
        .toast-body { flex: 1; }
        .toast-title { font-size: 13px; font-weight: 600; color: var(--text); }
        .toast-msg   { font-size: 12px; color: var(--text-muted); margin-top: 2px; line-height: 1.4; }
        .toast-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px; line-height: 1; padding: 0; transition: .15s; }
        .toast-close:hover { color: var(--text); }

        /* ── Avatar initials ─────────────────────────────────────── */
        .avatar {
            width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #fff;
        }

        /* ── Empty state ─────────────────────────────────────────── */
        .empty-state { text-align: center; padding: 48px 20px; }
        .empty-state .icon { font-size: 40px; margin-bottom: 12px; opacity: .4; }
        .empty-state p    { color: var(--text-muted); font-size: 13px; }

        /* ── Divider ─────────────────────────────────────────────── */
        .divider { height: 1px; background: var(--border); margin: 16px 0; }

        /* ── Spinner ─────────────────────────────────────────────── */
        .spinner {
            width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.2);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite; display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Page header ─────────────────────────────────────────── */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .page-title  { font-size: 20px; font-weight: 700; color: var(--text); }
        .page-sub    { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

        /* ── Toolbar ─────────────────────────────────────────────── */
        .toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }

        /* ── Responsive ──────────────────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar { position: fixed; top: 0; left: 0; height: 100%; transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .form-grid { grid-template-columns: 1fr; }
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
    @stack('styles')
</head>

<body>
<div class="layout" x-data="layout()" x-init="init()">

    {{-- Sidebar --}}
    <aside class="sidebar" :class="{ collapsed: !sidebarOpen }">
        <div class="sidebar-brand">
            <div class="brand-icon">💪</div>
            <div class="brand-text" x-show="sidebarOpen" x-transition:enter="transition-all duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                <div class="name">Fitness Studio</div>
                <div class="tagline">Gym Management</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                <span class="label" x-show="sidebarOpen">Dashboard</span>
            </a>

            <div class="nav-section" x-show="sidebarOpen"><span>Management</span></div>

            <a href="{{ route('members.index') }}" class="nav-item {{ request()->routeIs('members*') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="label" x-show="sidebarOpen">Members</span>
            </a>

            <a href="{{ route('trainers.index') }}" class="nav-item {{ request()->routeIs('trainers*') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path stroke-linecap="round" d="M16 11l2 2 4-4"/></svg>
                <span class="label" x-show="sidebarOpen">Trainers</span>
            </a>

            <a href="{{ route('attendance.index') }}" class="nav-item {{ request()->routeIs('attendance*') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline stroke-linecap="round" points="12 6 12 12 16 14"/></svg>
                <span class="label" x-show="sidebarOpen">Attendance</span>
            </a>

            <div class="nav-section" x-show="sidebarOpen"><span>Sales</span></div>

            <a href="{{ route('pos.index') }}" class="nav-item {{ request()->routeIs('pos*') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                <span class="label" x-show="sidebarOpen">Point of Sale</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}" id="logout-form">@csrf</form>
            <div class="user-card" @click="document.getElementById('logout-form').submit()">
                <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                <div class="user-info" x-show="sidebarOpen">
                    <div class="uname">{{ auth()->user()->name }}</div>
                    <div class="urole">{{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'user') }}</div>
                </div>
                <svg x-show="sidebarOpen" style="width:14px;height:14px;color:var(--text-muted);flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="main">
        <header class="topbar">
            <button class="topbar-toggle" @click="toggleSidebar()">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-title">@yield('title', 'Dashboard')</div>
            <div class="topbar-actions">
                @if(auth()->user()->gym)
                <span style="font-size:12px;color:var(--text-muted);padding:4px 10px;background:var(--primary-dim);color:var(--primary);border-radius:20px;font-weight:500">
                    {{ auth()->user()->gym->name }}
                </span>
                @endif
            </div>
        </header>

        <main class="page-content">
            @yield('content')
        </main>
    </div>

    {{-- Toast container --}}
    <div class="toast-container" x-data x-ref="toastRef">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div class="toast" :class="'toast-' + toast.type"
                 x-show="toast.visible"
                 x-transition:enter="transition-transform duration-300"
                 x-transition:leave="transition-opacity duration-200"
                 x-transition:leave-end="opacity-0">
                <div class="toast-icon">
                    <span x-text="toast.icon"></span>
                </div>
                <div class="toast-body">
                    <div class="toast-title" x-text="toast.title"></div>
                    <div class="toast-msg" x-text="toast.message" x-show="toast.message"></div>
                </div>
                <button class="toast-close" @click="$store.toasts.dismiss(toast.id)">×</button>
            </div>
        </template>
    </div>
</div>

<script>
    // ── Global helpers ────────────────────────────────────────────

    const CSRF = () => document.querySelector('meta[name="csrf-token"]').content;

    async function http(method, url, data = null) {
        const opts = {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
        };
        if (data) opts.body = JSON.stringify(data);
        const res = await fetch(url, opts);
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw { status: res.status, message: json.message || 'Something went wrong.' };
        return json;
    }

    const get  = (url)        => http('GET', url);
    const post = (url, data)  => http('POST', url, data);
    const put  = (url, data)  => http('PUT', url, data);
    const del  = (url)        => http('DELETE', url);

    function toast(title, type = 'success', message = '') {
        Alpine.store('toasts').show(title, type, message);
    }

    function currency(n) {
        return 'PKR ' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function avatarBg(name) {
        const colors = ['#6C63FF','#f472b6','#22c55e','#3b82f6','#eab308','#ef4444','#14b8a6'];
        let h = 0; for (let c of name) h = c.charCodeAt(0) + ((h << 5) - h);
        return colors[Math.abs(h) % colors.length];
    }

    function initials(name) {
        return name ? name.split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase() : '?';
    }

    // ── Layout component ──────────────────────────────────────────
    function layout() {
        return {
            sidebarOpen: localStorage.getItem('sidebar') !== 'closed',
            init() {},
            toggleSidebar() {
                this.sidebarOpen = !this.sidebarOpen;
                localStorage.setItem('sidebar', this.sidebarOpen ? 'open' : 'closed');
            },
        };
    }

    function initApp() {
        Alpine.store('toasts', {
            items: [],
            show(title, type = 'success', message = '') {
                const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
                const id = Date.now() + Math.random();
                this.items.push({ id, title, message, type, icon: icons[type] || '✅', visible: true });
                setTimeout(() => this.dismiss(id), 4500);
            },
            dismiss(id) {
                const t = this.items.find(i => i.id === id);
                if (t) t.visible = false;
                setTimeout(() => { this.items = this.items.filter(i => i.id !== id); }, 250);
            }
        });
    }
</script>
@stack('scripts')
</body>
</html>
