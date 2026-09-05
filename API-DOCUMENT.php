<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SC Events API Documentation - Mobile App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #0ea5e9;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e1e2e;
            --darker: #11111b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --font-arabic: 'Cairo', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        [data-theme="dark"] {
            --bg: var(--darker);
            --bg-card: var(--dark);
            --bg-code: #313244;
            --text: #cdd6f4;
            --text-muted: #a6adc8;
            --border: #45475a;
        }

        [data-theme="light"] {
            --bg: var(--gray-100);
            --bg-card: #ffffff;
            --bg-code: var(--gray-800);
            --text: var(--gray-800);
            --text-muted: var(--gray-500);
            --border: var(--gray-200);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-arabic);
            background: var(--bg);
            color: var(--text);
            line-height: 1.7;
            transition: background 0.3s, color 0.3s;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--gray-500); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }

        .header-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: var(--shadow-lg);
        }

        .logo svg { width: 50px; height: 50px; }

        .header h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
        }

        .version-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            margin-top: 15px;
            backdrop-filter: blur(10px);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 8px;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }

        .theme-toggle:hover { transform: scale(1.1); }
        .theme-toggle svg { width: 24px; height: 24px; fill: var(--text); }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
        }

        @media (max-width: 1024px) {
            .container { grid-template-columns: 1fr; }
        }

        .sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        @media (max-width: 1024px) {
            .sidebar { position: relative; top: 0; max-height: none; }
        }

        .nav-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .nav-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--text);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--primary);
            color: white;
        }

        .nav-link svg { width: 18px; height: 18px; opacity: 0.7; }

        .main-content { min-width: 0; }

        .section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            scroll-margin-top: 20px;
            animation: fadeIn 0.4s ease;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .section-icon svg { width: 28px; height: 28px; fill: white; }

        .section-icon.auth { background: linear-gradient(135deg, #10b981, #059669); }
        .section-icon.events { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        .section-icon.attendees { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .section-icon.tickets { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .section-icon.booths { background: linear-gradient(135deg, #ec4899, #db2777); }
        .section-icon.certificates { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .section-icon.coupons { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .section-icon.categories { background: linear-gradient(135deg, #f97316, #ea580c); }
        .section-icon.companies { background: linear-gradient(135deg, #64748b, #475569); }
        .section-icon.speakers { background: linear-gradient(135deg, #06b6d4, #0891b2); }

        .section h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; }
        .section-description { color: var(--text-muted); font-size: 0.95rem; }

        .endpoint {
            background: var(--bg);
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .endpoint-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .endpoint-header:hover { background: rgba(99, 102, 241, 0.05); }

        .method {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: var(--font-mono);
            min-width: 70px;
            text-align: center;
        }

        .method.get { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .method.post { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .method.put { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .method.delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        .endpoint-path {
            font-family: var(--font-mono);
            font-size: 0.9rem;
            color: var(--text);
            flex: 1;
        }

        .endpoint-path span { color: var(--primary); }

        .auth-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
        }

        .public-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .endpoint-toggle {
            width: 24px;
            height: 24px;
            fill: var(--text-muted);
            transition: transform 0.2s;
        }

        .endpoint.open .endpoint-toggle { transform: rotate(180deg); }

        .endpoint-body {
            display: none;
            padding: 20px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }

        .endpoint.open .endpoint-body { display: block; }

        .endpoint-desc { color: var(--text-muted); margin-bottom: 20px; }

        .code-block {
            background: var(--bg-code);
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }

        .code-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .code-title {
            font-size: 0.8rem;
            color: #a6adc8;
            font-family: var(--font-mono);
        }

        .copy-btn {
            background: transparent;
            border: none;
            color: #a6adc8;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .copy-btn:hover { background: rgba(255,255,255,0.1); color: white; }
        .copy-btn.copied { color: #10b981; }

        .code-content {
            padding: 15px;
            overflow-x: auto;
            direction: ltr;
            text-align: left;
        }

        .code-content pre {
            margin: 0;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            line-height: 1.6;
            color: #cdd6f4;
        }

        .string { color: #a6e3a1; }
        .number { color: #fab387; }
        .boolean { color: #cba6f7; }
        .null { color: #f38ba8; }
        .key { color: #89b4fa; }
        .comment { color: #6c7086; font-style: italic; }

        .table-wrapper { overflow-x: auto; margin: 20px 0; }

        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }

        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--bg);
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td { color: var(--text); }
        tr:hover td { background: rgba(99, 102, 241, 0.03); }

        .info-box {
            display: flex;
            gap: 15px;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .info-box.tip { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); }
        .info-box.warning { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); }
        .info-box.danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); }

        .info-box-icon { width: 24px; height: 24px; flex-shrink: 0; }
        .info-box.tip .info-box-icon { fill: #10b981; }
        .info-box.warning .info-box-icon { fill: #f59e0b; }
        .info-box.danger .info-box-icon { fill: #ef4444; }

        .info-box-content { flex: 1; }
        .info-box-title { font-weight: 600; margin-bottom: 5px; }
        .info-box.tip .info-box-title { color: #10b981; }
        .info-box.warning .info-box-title { color: #f59e0b; }
        .info-box.danger .info-box-title { color: #ef4444; }

        .footer {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .footer a { color: var(--primary); text-decoration: none; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .header h1 { font-size: 1.8rem; }
            .section { padding: 20px; }
            .endpoint-header { flex-wrap: wrap; gap: 8px; }
            .method { min-width: 60px; font-size: 0.7rem; }
            .endpoint-path { order: 3; width: 100%; font-size: 0.8rem; }
        }
    </style>
</head>
<body data-theme="dark">
    <button class="theme-toggle" onclick="toggleTheme()">
        <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 7a5 5 0 100 10 5 5 0 000-10zm0-5a1 1 0 011 1v2a1 1 0 01-2 0V3a1 1 0 011-1zm0 18a1 1 0 011 1v2a1 1 0 01-2 0v-2a1 1 0 011-1zM5.64 5.64a1 1 0 011.42 0l1.41 1.41a1 1 0 01-1.41 1.42L5.64 7.05a1 1 0 010-1.41zm12.73 12.73a1 1 0 011.41 0l1.42 1.41a1 1 0 01-1.42 1.42l-1.41-1.42a1 1 0 010-1.41zM3 12a1 1 0 011-1h2a1 1 0 010 2H4a1 1 0 01-1-1zm16 0a1 1 0 011-1h2a1 1 0 010 2h-2a1 1 0 01-1-1zM7.05 18.36a1 1 0 010 1.41l-1.41 1.42a1 1 0 11-1.42-1.42l1.42-1.41a1 1 0 011.41 0zm12.73-12.73a1 1 0 010 1.42l-1.42 1.41a1 1 0 11-1.41-1.41l1.41-1.42a1 1 0 011.42 0z"/></svg>
    </button>

    <header class="header">
        <div class="header-content">
            <div class="logo">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 4H5C3.89 4 3 4.9 3 6V20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V9H19V20ZM19 7H5V6H19V7Z" fill="#6366f1"/>
                    <path d="M7 11H17V13H7V11ZM7 15H14V17H7V15Z" fill="#6366f1"/>
                </svg>
            </div>
            <h1>SC Events Mobile API</h1>
            <p>واجهة برمجة التطبيقات للموبايل - مبسطة وسريعة</p>
            <span class="version-badge">الإصدار 2.0.0</span>
        </div>
    </header>

    <div class="container">
        <aside class="sidebar">
            <nav class="nav-section">
                <div class="nav-title">البداية السريعة</div>
                <a href="#overview" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                    نظرة عامة
                </a>
                <a href="#authentication" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"></path></svg>
                    المصادقة
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-title">نقاط النهاية</div>
                <a href="#auth-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path></svg>
                    المصادقة
                </a>
                <a href="#events-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"></path></svg>
                    الفعاليات
                </a>
                <a href="#categories-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-5.5 9h11L12 2zm0 3.84L13.93 9h-3.87L12 5.84zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5s2.01 4.5 4.5 4.5 4.5-2.01 4.5-4.5-2.01-4.5-4.5-4.5zm0 7a2.5 2.5 0 010-5 2.5 2.5 0 010 5zM3 21.5h8v-8H3v8zm2-6h4v4H5v-4z"></path></svg>
                    التصنيفات
                </a>
                <a href="#speakers-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>
                    المتحدثون
                </a>
                <a href="#organizers-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"></path></svg>
                    المنظمون
                </a>
                <a href="#attendees-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"></path></svg>
                    الحضور
                </a>
                <a href="#companies-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"></path></svg>
                    الشركات
                </a>
                <a href="#booths-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V6h16v12z"></path></svg>
                    الأكشاك
                </a>
                <a href="#certificates-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"></path></svg>
                    الشهادات
                </a>
                <a href="#coupons-endpoints" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"></path></svg>
                    الكوبونات
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-title">المساعدة</div>
                <a href="#errors" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path></svg>
                    الأخطاء
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <!-- Overview Section -->
            <section id="overview" class="section">
                <div class="section-header">
                    <div class="section-icon events">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                    </div>
                    <div>
                        <h2>نظرة عامة</h2>
                        <p class="section-description">API مبسط للتطبيق المحمول</p>
                    </div>
                </div>

                <p style="margin-bottom: 20px;">
                    هذه الواجهة مصممة خصيصاً للتطبيق المحمول. معظم النقاط <strong>عامة (Public)</strong> ولا تتطلب مصادقة.
                </p>

                <div class="code-block">
                    <div class="code-header">
                        <span class="code-title">Base URL</span>
                        <button class="copy-btn" onclick="copyCode(this)">نسخ</button>
                    </div>
                    <div class="code-content">
                        <pre>https://your-domain.com/wp-content/themes/sc_events/api.php</pre>
                    </div>
                </div>

                <div class="info-box tip">
                    <svg class="info-box-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    <div class="info-box-content">
                        <div class="info-box-title">ملاحظة</div>
                        <div>النقاط المميزة بـ <span class="public-badge">Public</span> لا تحتاج Authorization header</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>القسم</th>
                                <th>عدد النقاط</th>
                                <th>الوصف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>المصادقة</td><td>3</td><td>تسجيل دخول، إنشاء حساب، تجديد</td></tr>
                            <tr><td>الفعاليات</td><td>2</td><td>قائمة + تفاصيل</td></tr>
                            <tr><td>التصنيفات</td><td>1</td><td>قائمة التصنيفات</td></tr>
                            <tr><td>المتحدثون</td><td>2</td><td>قائمة + تفاصيل</td></tr>
                            <tr><td>المنظمون</td><td>2</td><td>قائمة + تفاصيل</td></tr>
                            <tr><td>الحضور</td><td>3</td><td>تسجيل + عرض + تذاكري</td></tr>
                            <tr><td>الشركات</td><td>4</td><td>تسجيل + عرض + قائمة + تحديث</td></tr>
                            <tr><td>الأكشاك</td><td>5</td><td>قائمة + أنواع + حجوزات</td></tr>
                            <tr><td>الشهادات</td><td>4</td><td>تحقق + فحص + طلب + شهاداتي</td></tr>
                            <tr><td>الكوبونات</td><td>3</td><td>تحقق + تطبيق + قائمة</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Authentication Info -->
            <section id="authentication" class="section">
                <div class="section-header">
                    <div class="section-icon auth">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"></path></svg>
                    </div>
                    <div>
                        <h2>المصادقة</h2>
                        <p class="section-description">استخدام JWT للنقاط المحمية</p>
                    </div>
                </div>

                <p style="margin-bottom: 20px;">
                    بعض النقاط تتطلب مصادقة (مثل تذاكري، شهاداتي). استخدم التوكن من تسجيل الدخول:
                </p>

                <div class="code-block">
                    <div class="code-header">
                        <span class="code-title">Authorization Header</span>
                    </div>
                    <div class="code-content">
                        <pre><span class="key">Authorization:</span> Bearer eyJhbGciOiJIUzI1NiIs...</pre>
                    </div>
                </div>
            </section>

            <!-- Auth Endpoints -->
            <section id="auth-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon auth">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path></svg>
                    </div>
                    <div>
                        <h2>المصادقة</h2>
                        <p class="section-description">تسجيل الدخول وإنشاء الحسابات</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/auth/login</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تسجيل الدخول والحصول على توكن</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"email"</span>: <span class="string">"user@example.com"</span>,
    <span class="key">"password"</span>: <span class="string">"password123"</span>
}</pre>
                            </div>
                        </div>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"data"</span>: {
        <span class="key">"token"</span>: <span class="string">"eyJhbGciOiJIUzI1NiIs..."</span>,
        <span class="key">"refresh_token"</span>: <span class="string">"abc123..."</span>,
        <span class="key">"expires_in"</span>: <span class="number">3600</span>,
        <span class="key">"user"</span>: {
            <span class="key">"id"</span>: <span class="number">1</span>,
            <span class="key">"email"</span>: <span class="string">"user@example.com"</span>,
            <span class="key">"name"</span>: <span class="string">"أحمد محمد"</span>
        }
    }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/auth/register</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">إنشاء حساب جديد</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"email"</span>: <span class="string">"newuser@example.com"</span>,
    <span class="key">"password"</span>: <span class="string">"secure-password"</span>,
    <span class="key">"name"</span>: <span class="string">"أحمد محمد"</span>,
    <span class="key">"phone"</span>: <span class="string">"+966501234567"</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/auth/refresh</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تجديد التوكن باستخدام refresh_token</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"refresh_token"</span>: <span class="string">"your-refresh-token"</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Events Endpoints -->
            <section id="events-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon events">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"></path></svg>
                    </div>
                    <div>
                        <h2>الفعاليات</h2>
                        <p class="section-description">عرض الفعاليات المنشورة</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/events</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">جلب الفعاليات المنشورة مع الفلاتر</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>page</td><td>integer</td><td>رقم الصفحة (افتراضي: 1)</td></tr>
                                    <tr><td>per_page</td><td>integer</td><td>عدد العناصر (افتراضي: 10، الحد: 100)</td></tr>
                                    <tr><td>category</td><td>integer</td><td>فلترة حسب التصنيف (ID أو slug)</td></tr>
                                    <tr><td>type</td><td>string</td><td><code>upcoming</code> (قادمة) أو <code>past</code> (سابقة)</td></tr>
                                    <tr><td>search</td><td>string</td><td>البحث في العنوان</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"data"</span>: {
        <span class="key">"items"</span>: [
            {
                <span class="key">"id"</span>: <span class="number">1</span>,
                <span class="key">"title"</span>: <span class="string">"مؤتمر التقنية 2025"</span>,
                <span class="key">"description"</span>: <span class="string">"..."</span>,
                <span class="key">"start_date"</span>: <span class="string">"2025-06-15"</span>,
                <span class="key">"end_date"</span>: <span class="string">"2025-06-17"</span>,
                <span class="key">"venue_name"</span>: <span class="string">"مركز المؤتمرات"</span>,
                <span class="key">"image"</span>: <span class="string">"https://..."</span>,
                <span class="key">"categories"</span>: [<span class="string">"تقنية"</span>]
            }
        ],
        <span class="key">"pagination"</span>: {
            <span class="key">"total"</span>: <span class="number">50</span>,
            <span class="key">"page"</span>: <span class="number">1</span>,
            <span class="key">"per_page"</span>: <span class="number">10</span>,
            <span class="key">"total_pages"</span>: <span class="number">5</span>
        }
    }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/events/<span>{id}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">جلب تفاصيل فعالية (يشمل التذاكر والمتحدثين)</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"data"</span>: {
        <span class="key">"id"</span>: <span class="number">1</span>,
        <span class="key">"title"</span>: <span class="string">"مؤتمر التقنية 2025"</span>,
        <span class="key">"description"</span>: <span class="string">"وصف كامل..."</span>,
        <span class="key">"start_date"</span>: <span class="string">"2025-06-15"</span>,
        <span class="key">"end_date"</span>: <span class="string">"2025-06-17"</span>,
        <span class="key">"start_time"</span>: <span class="string">"09:00:00"</span>,
        <span class="key">"end_time"</span>: <span class="string">"17:00:00"</span>,
        <span class="key">"venue_name"</span>: <span class="string">"مركز المؤتمرات"</span>,
        <span class="key">"venue_address"</span>: <span class="string">"الرياض"</span>,
        <span class="key">"map_url"</span>: <span class="string">"https://maps.google.com/..."</span>,
        <span class="key">"image"</span>: <span class="string">"https://..."</span>,
        <span class="key">"gallery"</span>: [<span class="string">"https://..."</span>],
        <span class="key">"tickets"</span>: [
            {
                <span class="key">"id"</span>: <span class="number">1</span>,
                <span class="key">"name"</span>: <span class="string">"تذكرة عادية"</span>,
                <span class="key">"price"</span>: <span class="number">100</span>,
                <span class="key">"available"</span>: <span class="number">50</span>
            }
        ],
        <span class="key">"speakers"</span>: [...],
        <span class="key">"extra_fields"</span>: [...]
    }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Categories Endpoints -->
            <section id="categories-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon categories">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11L12 2zm0 3.84L13.93 9h-3.87L12 5.84zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5s2.01 4.5 4.5 4.5 4.5-2.01 4.5-4.5-2.01-4.5-4.5-4.5zm0 7a2.5 2.5 0 010-5 2.5 2.5 0 010 5zM3 21.5h8v-8H3v8z"></path></svg>
                    </div>
                    <div>
                        <h2>التصنيفات</h2>
                        <p class="section-description">تصنيفات الفعاليات</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/categories</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">جلب جميع التصنيفات</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"data"</span>: [
        {
            <span class="key">"id"</span>: <span class="number">1</span>,
            <span class="key">"name"</span>: <span class="string">"تقنية"</span>,
            <span class="key">"slug"</span>: <span class="string">"technology"</span>,
            <span class="key">"description"</span>: <span class="string">"فعاليات تقنية"</span>,
            <span class="key">"events_count"</span>: <span class="number">15</span>
        }
    ]
}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Speakers Endpoints -->
            <section id="speakers-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon speakers">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>
                    </div>
                    <div>
                        <h2>المتحدثون</h2>
                        <p class="section-description">متحدثو الفعاليات</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/speakers</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">جلب المتحدثين (يمكن الفلترة حسب الفعالية)</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>event_id</td><td>integer</td><td>فلترة حسب الفعالية (اختياري)</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"data"</span>: [
        {
            <span class="key">"id"</span>: <span class="number">1</span>,
            <span class="key">"name"</span>: <span class="string">"د. أحمد محمد"</span>,
            <span class="key">"title"</span>: <span class="string">"خبير تقني"</span>,
            <span class="key">"bio"</span>: <span class="string">"..."</span>,
            <span class="key">"photo"</span>: <span class="string">"https://..."</span>,
            <span class="key">"social"</span>: {
                <span class="key">"twitter"</span>: <span class="string">"@ahmed"</span>,
                <span class="key">"linkedin"</span>: <span class="string">"..."</span>
            }
        }
    ]
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/speakers/<span>{id}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تفاصيل متحدث محدد</p>
                    </div>
                </div>
            </section>

            <!-- Organizers Endpoints -->
            <section id="organizers-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon speakers">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path></svg>
                    </div>
                    <div>
                        <h2>المنظمون</h2>
                        <p class="section-description">منظمو الفعاليات</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/organizers</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">جلب المنظمين (يمكن الفلترة حسب الفعالية)</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>event_id</td><td>integer</td><td>فلترة حسب الفعالية (اختياري)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/organizers/<span>{id}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تفاصيل منظم محدد</p>
                    </div>
                </div>
            </section>

            <!-- Attendees Endpoints -->
            <section id="attendees-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon attendees">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3z"></path></svg>
                    </div>
                    <div>
                        <h2>الحضور</h2>
                        <p class="section-description">تسجيل الحضور في الفعاليات</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/attendees/register</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تسجيل حاضر جديد في فعالية</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"event_id"</span>: <span class="number">1</span>,
    <span class="key">"ticket_id"</span>: <span class="number">1</span>,
    <span class="key">"name"</span>: <span class="string">"أحمد محمد"</span>,
    <span class="key">"email"</span>: <span class="string">"ahmed@example.com"</span>,
    <span class="key">"phone"</span>: <span class="string">"+966501234567"</span>,
    <span class="key">"coupon_code"</span>: <span class="string">"DISCOUNT20"</span>,  <span class="comment">// اختياري</span>
    <span class="key">"extra_fields"</span>: {                   <span class="comment">// اختياري</span>
        <span class="key">"company"</span>: <span class="string">"شركة ABC"</span>,
        <span class="key">"job_title"</span>: <span class="string">"مدير"</span>
    }
}</pre>
                            </div>
                        </div>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"message"</span>: <span class="string">"تم التسجيل بنجاح"</span>,
    <span class="key">"data"</span>: {
        <span class="key">"id"</span>: <span class="number">123</span>,
        <span class="key">"ticket_code"</span>: <span class="string">"TKT-A1B2C3D4"</span>,
        <span class="key">"name"</span>: <span class="string">"أحمد محمد"</span>,
        <span class="key">"ticket_name"</span>: <span class="string">"تذكرة عادية"</span>,
        <span class="key">"ticket_price"</span>: <span class="number">80</span>,
        <span class="key">"coupon_discount"</span>: <span class="number">20</span>,
        <span class="key">"payment_status"</span>: <span class="string">"pending"</span>,
        <span class="key">"requires_payment"</span>: <span class="boolean">true</span>
    }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/attendees/<span>{ticket_code}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">عرض تفاصيل التذكرة بالكود</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/attendees/my-tickets</span>
                        <span class="auth-badge">Auth</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">جلب تذاكر المستخدم الحالي (أو بالبريد الإلكتروني)</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>email</td><td>string</td><td>البريد الإلكتروني (إذا لم يكن مسجل دخول)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Companies Endpoints -->
            <section id="companies-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon companies">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2z"></path></svg>
                    </div>
                    <div>
                        <h2>الشركات</h2>
                        <p class="section-description">تسجيل الشركات في الفعاليات</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/companies/register</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تسجيل شركة جديدة في فعالية</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"event_id"</span>: <span class="number">1</span>,
    <span class="key">"company_name"</span>: <span class="string">"شركة التقنية المتقدمة"</span>,
    <span class="key">"company_name_ar"</span>: <span class="string">"شركة التقنية المتقدمة"</span>,
    <span class="key">"industry"</span>: <span class="string">"تقنية المعلومات"</span>,
    <span class="key">"company_size"</span>: <span class="string">"50-100"</span>,
    <span class="key">"website"</span>: <span class="string">"https://example.com"</span>,

    <span class="key">"contact_name"</span>: <span class="string">"أحمد محمد"</span>,
    <span class="key">"contact_title"</span>: <span class="string">"مدير التسويق"</span>,
    <span class="key">"contact_email"</span>: <span class="string">"ahmed@company.com"</span>,
    <span class="key">"contact_phone"</span>: <span class="string">"+966501234567"</span>,

    <span class="key">"country"</span>: <span class="string">"السعودية"</span>,
    <span class="key">"city"</span>: <span class="string">"الرياض"</span>,
    <span class="key">"address"</span>: <span class="string">"شارع العليا"</span>,

    <span class="key">"social_media"</span>: {
        <span class="key">"twitter"</span>: <span class="string">"@company"</span>,
        <span class="key">"linkedin"</span>: <span class="string">"company-page"</span>
    },
    <span class="key">"products"</span>: [<span class="string">"منتج 1"</span>, <span class="string">"منتج 2"</span>]
}</pre>
                            </div>
                        </div>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"message"</span>: <span class="string">"تم تسجيل الشركة بنجاح"</span>,
    <span class="key">"data"</span>: {
        <span class="key">"id"</span>: <span class="number">1</span>,
        <span class="key">"company_code"</span>: <span class="string">"COMP-A1B2C3D4-E5F6G7H8"</span>,
        <span class="key">"company_name"</span>: <span class="string">"شركة التقنية المتقدمة"</span>,
        <span class="key">"status"</span>: <span class="string">"active"</span>,
        <span class="key">"payment_status"</span>: <span class="string">"pending"</span>
    }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/companies/<span>{company_code}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">عرض تفاصيل الشركة بالكود</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/companies</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">قائمة الشركات المسجلة في فعالية</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters (مطلوب)</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>event_id</td><td>integer</td><td>ID الفعالية (مطلوب)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method put">PUT</span>
                        <span class="endpoint-path">/companies/<span>{company_code}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تحديث بيانات الشركة</p>
                    </div>
                </div>
            </section>

            <!-- Booths Endpoints -->
            <section id="booths-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon booths">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z"></path></svg>
                    </div>
                    <div>
                        <h2>الأكشاك</h2>
                        <p class="section-description">عرض وحجز الأكشاك</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/booths</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">قائمة الأكشاك لفعالية</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters (مطلوب)</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>event_id</td><td>integer</td><td>ID الفعالية (مطلوب)</td></tr>
                                    <tr><td>status</td><td>string</td><td>available, booked (اختياري)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/booths/<span>{id}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">تفاصيل كشك محدد</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/booth-types</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">أنواع الأكشاك المتاحة</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/booth-bookings</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">حجز كشك</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"booth_id"</span>: <span class="number">1</span>,
    <span class="key">"company_name"</span>: <span class="string">"شركة ABC"</span>,
    <span class="key">"contact_name"</span>: <span class="string">"أحمد"</span>,
    <span class="key">"contact_email"</span>: <span class="string">"ahmed@abc.com"</span>,
    <span class="key">"contact_phone"</span>: <span class="string">"+966501234567"</span>,
    <span class="key">"notes"</span>: <span class="string">"ملاحظات إضافية"</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/booth-bookings/<span>{booking_code}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">عرض تفاصيل حجز بالكود</p>
                    </div>
                </div>
            </section>

            <!-- Certificates Endpoints -->
            <section id="certificates-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon certificates">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"></path></svg>
                    </div>
                    <div>
                        <h2>الشهادات</h2>
                        <p class="section-description">شهادات الحضور</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/certificates/verify/<span>{code}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">التحقق من صحة الشهادة</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response (صالحة)</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"data"</span>: {
        <span class="key">"valid"</span>: <span class="boolean">true</span>,
        <span class="key">"certificate_number"</span>: <span class="string">"CERT-2025-A1B2C3D4"</span>,
        <span class="key">"attendee_name"</span>: <span class="string">"أحمد محمد"</span>,
        <span class="key">"event"</span>: {
            <span class="key">"title"</span>: <span class="string">"مؤتمر التقنية 2025"</span>,
            <span class="key">"start_date"</span>: <span class="string">"2025-06-15"</span>
        },
        <span class="key">"issued_at"</span>: <span class="string">"2025-06-17 10:00:00"</span>
    }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/certificates/check</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">فحص أهلية الحصول على شهادة</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>ticket_code</td><td>string</td><td>كود التذكرة</td></tr>
                                    <tr><td>أو email + event_id</td><td>string, int</td><td>البريد مع ID الفعالية</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/certificates/request</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">طلب إصدار شهادة</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"ticket_code"</span>: <span class="string">"TKT-A1B2C3D4"</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/certificates/my</span>
                        <span class="auth-badge">Auth</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">جلب شهاداتي (بالتسجيل أو البريد)</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>email</td><td>string</td><td>البريد الإلكتروني (إذا لم يكن مسجل دخول)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Coupons Endpoints -->
            <section id="coupons-endpoints" class="section">
                <div class="section-header">
                    <div class="section-icon coupons">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42z"></path></svg>
                    </div>
                    <div>
                        <h2>الكوبونات</h2>
                        <p class="section-description">كوبونات الخصم</p>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/coupons/validate</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">التحقق من صلاحية كوبون</p>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Request</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"code"</span>: <span class="string">"DISCOUNT20"</span>,
    <span class="key">"event_id"</span>: <span class="number">1</span>,
    <span class="key">"ticket_id"</span>: <span class="number">1</span>,
    <span class="key">"amount"</span>: <span class="number">100</span>
}</pre>
                            </div>
                        </div>
                        <div class="code-block">
                            <div class="code-header"><span class="code-title">Response</span></div>
                            <div class="code-content">
                                <pre>{
    <span class="key">"success"</span>: <span class="boolean">true</span>,
    <span class="key">"data"</span>: {
        <span class="key">"valid"</span>: <span class="boolean">true</span>,
        <span class="key">"code"</span>: <span class="string">"DISCOUNT20"</span>,
        <span class="key">"discount_type"</span>: <span class="string">"percentage"</span>,
        <span class="key">"discount_value"</span>: <span class="number">20</span>,
        <span class="key">"discount_amount"</span>: <span class="number">20</span>,
        <span class="key">"final_amount"</span>: <span class="number">80</span>
    }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/coupons/check/<span>{code}</span></span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">فحص سريع لكوبون</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>event_id</td><td>integer</td><td>ID الفعالية (اختياري)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-header" onclick="toggleEndpoint(this)">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/coupons</span>
                        <span class="public-badge">Public</span>
                        <svg class="endpoint-toggle" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">قائمة الكوبونات المتاحة لفعالية</p>
                        <h4 style="margin: 15px 0 10px;">Query Parameters</h4>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                                <tbody>
                                    <tr><td>event_id</td><td>integer</td><td>ID الفعالية (اختياري)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Errors Section -->
            <section id="errors" class="section">
                <div class="section-header">
                    <div class="section-icon booths">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path></svg>
                    </div>
                    <div>
                        <h2>رموز الأخطاء</h2>
                        <p class="section-description">فهم رسائل الخطأ</p>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>HTTP Status</th>
                                <th>الوصف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>200</td><td>نجاح</td></tr>
                            <tr><td>201</td><td>تم الإنشاء بنجاح</td></tr>
                            <tr><td>400</td><td>بيانات غير صالحة</td></tr>
                            <tr><td>401</td><td>غير مصرح</td></tr>
                            <tr><td>403</td><td>ممنوع</td></tr>
                            <tr><td>404</td><td>غير موجود</td></tr>
                            <tr><td>422</td><td>خطأ في التحقق</td></tr>
                            <tr><td>429</td><td>تجاوز الحد المسموح</td></tr>
                            <tr><td>500</td><td>خطأ في الخادم</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="code-block">
                    <div class="code-header"><span class="code-title">Error Response Format</span></div>
                    <div class="code-content">
                        <pre>{
    <span class="key">"success"</span>: <span class="boolean">false</span>,
    <span class="key">"message"</span>: <span class="string">"رسالة الخطأ"</span>,
    <span class="key">"errors"</span>: {
        <span class="key">"field_name"</span>: [<span class="string">"خطأ 1"</span>, <span class="string">"خطأ 2"</span>]
    }
}</pre>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <footer class="footer">
        <p>SC Events Mobile API v2.0.0 | تم التحديث: فبراير 2026</p>
    </footer>

    <script>
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            body.setAttribute('data-theme', currentTheme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', body.getAttribute('data-theme'));
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.body.setAttribute('data-theme', savedTheme);
        }

        function toggleEndpoint(header) {
            const endpoint = header.closest('.endpoint');
            endpoint.classList.toggle('open');
        }

        function copyCode(btn) {
            const codeBlock = btn.closest('.code-block');
            const code = codeBlock.querySelector('pre').textContent;
            navigator.clipboard.writeText(code).then(() => {
                btn.classList.add('copied');
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> تم النسخ';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg> نسخ';
                }, 2000);
            });
        }

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Active nav link
        const sections = document.querySelectorAll('.section');
        const navLinks = document.querySelectorAll('.nav-link');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (scrollY >= sectionTop - 100) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
