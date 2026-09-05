<?php
/**
 * Platform Features Showcase Page
 * Professional presentation for potential clients
 *
 * @package sc_events
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة إدارة الفعاليات - عرض المميزات</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #0ea5e9;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #1e293b;
            --darker: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --gradient-1: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            --gradient-2: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
            --gradient-3: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--darker);
            color: var(--light);
            overflow-x: hidden;
            line-height: 1.7;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: var(--darker);
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(14, 165, 233, 0.1) 0%, transparent 40%);
            animation: bgPulse 15s ease-in-out infinite;
        }

        @keyframes bgPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            position: relative;
        }

        .hero-content {
            max-width: 900px;
            z-index: 1;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            color: #a5b4fc;
            margin-bottom: 2rem;
            animation: fadeInUp 0.8s ease;
        }

        .hero-badge i {
            color: var(--accent);
        }

        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 900;
            margin-bottom: 1.5rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--gray);
            margin-bottom: 2.5rem;
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease 0.6s both;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.5);
        }

        .btn-outline {
            background: transparent;
            color: var(--light);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
        }

        /* Stats Section */
        .stats {
            padding: 4rem 2rem;
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-card:nth-child(1) .stat-icon { background: var(--gradient-1); }
        .stat-card:nth-child(2) .stat-icon { background: var(--gradient-2); }
        .stat-card:nth-child(3) .stat-icon { background: var(--gradient-3); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--light);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* Section Styles */
        .section {
            padding: 6rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-badge {
            display: inline-block;
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .section-desc {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 2.5rem;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-1);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 25px 50px -12px rgba(99, 102, 241, 0.15);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .feature-card:nth-child(1) .feature-icon { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
        .feature-card:nth-child(2) .feature-icon { background: rgba(14, 165, 233, 0.15); color: #38bdf8; }
        .feature-card:nth-child(3) .feature-icon { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .feature-card:nth-child(4) .feature-icon { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .feature-card:nth-child(5) .feature-icon { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .feature-card:nth-child(6) .feature-icon { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        .feature-card:nth-child(7) .feature-icon { background: rgba(236, 72, 153, 0.15); color: #f472b6; }
        .feature-card:nth-child(8) .feature-icon { background: rgba(20, 184, 166, 0.15); color: #2dd4bf; }
        .feature-card:nth-child(9) .feature-icon { background: rgba(251, 146, 60, 0.15); color: #fb923c; }
        .feature-card:nth-child(10) .feature-icon { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .feature-card:nth-child(11) .feature-icon { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .feature-card:nth-child(12) .feature-icon { background: rgba(244, 114, 182, 0.15); color: #f472b6; }

        .feature-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .feature-desc {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            line-height: 1.8;
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            color: #cbd5e1;
            font-size: 0.9rem;
        }

        .feature-list li i {
            color: var(--success);
            font-size: 0.8rem;
        }

        /* Screenshots Section */
        .screenshots {
            background: rgba(30, 41, 59, 0.3);
            padding: 6rem 2rem;
        }

        .screenshots-slider {
            display: flex;
            gap: 2rem;
            overflow-x: auto;
            padding: 2rem;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }

        .screenshots-slider::-webkit-scrollbar {
            height: 8px;
        }

        .screenshots-slider::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .screenshots-slider::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .screenshot-card {
            min-width: 400px;
            background: var(--dark);
            border-radius: 20px;
            overflow: hidden;
            scroll-snap-align: start;
            transition: transform 0.3s ease;
        }

        .screenshot-card:hover {
            transform: scale(1.02);
        }

        .screenshot-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .screenshot-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .screenshot-dot:nth-child(1) { background: #ef4444; }
        .screenshot-dot:nth-child(2) { background: #fbbf24; }
        .screenshot-dot:nth-child(3) { background: #22c55e; }

        .screenshot-title {
            margin-right: auto;
            font-size: 0.85rem;
            color: var(--gray);
        }

        .screenshot-body {
            padding: 1.5rem;
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
        }

        /* Pricing Section */
        .pricing {
            padding: 6rem 2rem;
        }

        .pricing-card {
            max-width: 500px;
            margin: 0 auto;
            background: linear-gradient(145deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
            border: 2px solid rgba(99, 102, 241, 0.3);
            border-radius: 32px;
            padding: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .pricing-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 60%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .pricing-content {
            position: relative;
            z-index: 1;
        }

        .pricing-badge {
            background: var(--gradient-1);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .pricing-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .pricing-desc {
            color: var(--gray);
            margin-bottom: 2rem;
        }

        .pricing-features {
            text-align: right;
            margin-bottom: 2rem;
        }

        .pricing-features li {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .pricing-features li:last-child {
            border-bottom: none;
        }

        .pricing-features li i {
            width: 24px;
            height: 24px;
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        /* Tech Stack */
        .tech-stack {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .tech-badge {
            background: rgba(255, 255, 255, 0.05);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tech-badge i {
            color: var(--primary);
        }

        /* CTA Section */
        .cta {
            padding: 6rem 2rem;
            text-align: center;
            background: linear-gradient(180deg, transparent 0%, rgba(99, 102, 241, 0.1) 100%);
        }

        .cta h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .cta p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .contact-info {
            display: flex;
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 3rem;
        }

        .contact-card {
            background: rgba(30, 41, 59, 0.8);
            padding: 1.5rem 2rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .contact-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .contact-card i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .contact-card span {
            font-size: 1.1rem;
        }

        /* Footer */
        footer {
            padding: 2rem;
            text-align: center;
            color: var(--gray);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.8s ease both;
        }

        /* Scroll animations */
        .reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease;
        }

        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .features-grid {
                grid-template-columns: 1fr;
            }

            .screenshot-card {
                min-width: 300px;
            }

            .contact-info {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Print styles */
        @media print {
            .bg-animation { display: none; }
            body { background: white; color: black; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-rocket"></i>
                <span>منصة متكاملة لإدارة الفعاليات</span>
            </div>
            <h1>نظام إدارة الفعاليات الاحترافي</h1>
            <p>حل شامل ومتكامل لإدارة الفعاليات والمؤتمرات والورش التدريبية. صُمم خصيصاً لتلبية احتياجات المنظمين والشركات في المنطقة العربية.</p>
            <div class="hero-buttons">
                <a href="#features" class="btn btn-primary">
                    <i class="fas fa-eye"></i>
                    استعرض المميزات
                </a>
                <a href="#contact" class="btn btn-outline">
                    <i class="fas fa-phone"></i>
                    تواصل معنا
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number">+12</div>
                <div class="stat-label">وحدة متكاملة</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-infinity"></i></div>
                <div class="stat-number">∞</div>
                <div class="stat-label">فعاليات غير محدودة</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number">∞</div>
                <div class="stat-label">حضور غير محدود</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-icon"><i class="fas fa-headset"></i></div>
                <div class="stat-number">24/7</div>
                <div class="stat-label">دعم فني متواصل</div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section" id="features">
        <div class="section-header reveal">
            <span class="section-badge">المميزات</span>
            <h2 class="section-title">كل ما تحتاجه في منصة واحدة</h2>
            <p class="section-desc">نظام شامل يغطي جميع جوانب إدارة الفعاليات من التخطيط إلى التنفيذ والتقييم</p>
        </div>

        <div class="features-grid">
            <!-- Feature 1: Events Management -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="feature-title">إدارة الفعاليات</h3>
                <p class="feature-desc">إنشاء وإدارة فعاليات متعددة بكل سهولة مع تحكم كامل في التفاصيل والإعدادات</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> إنشاء فعاليات متعددة الأيام</li>
                    <li><i class="fas fa-check"></i> تحديد المواقع والأماكن</li>
                    <li><i class="fas fa-check"></i> جدولة الجلسات والأنشطة</li>
                    <li><i class="fas fa-check"></i> إضافة صور ووصف تفصيلي</li>
                    <li><i class="fas fa-check"></i> تصنيف الفعاليات بفئات</li>
                </ul>
            </div>

            <!-- Feature 2: Tickets System -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <h3 class="feature-title">نظام التذاكر المتقدم</h3>
                <p class="feature-desc">نظام تذاكر مرن يدعم أنواع متعددة وتسعير ديناميكي</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> تذاكر متعددة الأنواع (VIP, عادية, مجانية)</li>
                    <li><i class="fas fa-check"></i> تسعير مرن وخصومات</li>
                    <li><i class="fas fa-check"></i> تحديد الكمية المتاحة</li>
                    <li><i class="fas fa-check"></i> تواريخ بدء وانتهاء البيع</li>
                    <li><i class="fas fa-check"></i> حقول مخصصة لكل نوع تذكرة</li>
                </ul>
            </div>

            <!-- Feature 3: Attendees Management -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="feature-title">إدارة الحضور</h3>
                <p class="feature-desc">تسجيل ومتابعة الحضور مع إمكانية الاستيراد والتصدير</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> تسجيل يدوي وتلقائي</li>
                    <li><i class="fas fa-check"></i> استيراد من Excel/CSV</li>
                    <li><i class="fas fa-check"></i> تصدير قوائم الحضور</li>
                    <li><i class="fas fa-check"></i> حقول مخصصة للبيانات</li>
                    <li><i class="fas fa-check"></i> تتبع حالة الحضور</li>
                </ul>
            </div>

            <!-- Feature 4: QR Scanner -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h3 class="feature-title">ماسح QR الذكي</h3>
                <p class="feature-desc">تسجيل حضور سريع عبر مسح رمز QR من الكاميرا مباشرة</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> مسح سريع من الكاميرا</li>
                    <li><i class="fas fa-check"></i> تأكيد فوري للحضور</li>
                    <li><i class="fas fa-check"></i> عرض بيانات الحاضر</li>
                    <li><i class="fas fa-check"></i> منع التسجيل المكرر</li>
                    <li><i class="fas fa-check"></i> يعمل على الجوال والكمبيوتر</li>
                </ul>
            </div>

            <!-- Feature 5: Certificates -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3 class="feature-title">نظام الشهادات</h3>
                <p class="feature-desc">إصدار شهادات احترافية مع قوالب قابلة للتخصيص</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> قوالب شهادات متعددة</li>
                    <li><i class="fas fa-check"></i> تخصيص كامل للتصميم</li>
                    <li><i class="fas fa-check"></i> إصدار فردي وجماعي</li>
                    <li><i class="fas fa-check"></i> رمز تحقق فريد</li>
                    <li><i class="fas fa-check"></i> تصدير PDF عالي الجودة</li>
                </ul>
            </div>

            <!-- Feature 6: Coupons -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <h3 class="feature-title">أكواد الخصم</h3>
                <p class="feature-desc">إنشاء وإدارة أكواد الخصم بمرونة كاملة</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> خصم نسبي أو ثابت</li>
                    <li><i class="fas fa-check"></i> إنشاء مجموعة أكواد</li>
                    <li><i class="fas fa-check"></i> تحديد عدد الاستخدام</li>
                    <li><i class="fas fa-check"></i> صلاحية زمنية</li>
                    <li><i class="fas fa-check"></i> ربط بفعاليات محددة</li>
                </ul>
            </div>

            <!-- Feature 7: Speakers -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-microphone"></i>
                </div>
                <h3 class="feature-title">إدارة المتحدثين</h3>
                <p class="feature-desc">قاعدة بيانات شاملة للمتحدثين والخبراء</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> ملفات تعريفية كاملة</li>
                    <li><i class="fas fa-check"></i> صور ونبذة تعريفية</li>
                    <li><i class="fas fa-check"></i> روابط التواصل الاجتماعي</li>
                    <li><i class="fas fa-check"></i> ربط بالفعاليات</li>
                    <li><i class="fas fa-check"></i> عرض في صفحة الفعالية</li>
                </ul>
            </div>

            <!-- Feature 8: Organizers -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="feature-title">إدارة المنظمين</h3>
                <p class="feature-desc">إدارة الجهات والشركات المنظمة للفعاليات</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> بيانات الشركة/الجهة</li>
                    <li><i class="fas fa-check"></i> الشعار ومعلومات التواصل</li>
                    <li><i class="fas fa-check"></i> ربط بفعاليات متعددة</li>
                    <li><i class="fas fa-check"></i> عرض في صفحة الفعالية</li>
                </ul>
            </div>

            <!-- Feature 9: Reports -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3 class="feature-title">التقارير والإحصائيات</h3>
                <p class="feature-desc">تقارير تفصيلية ورسوم بيانية تفاعلية</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> إحصائيات الحضور</li>
                    <li><i class="fas fa-check"></i> تقارير المبيعات</li>
                    <li><i class="fas fa-check"></i> رسوم بيانية تفاعلية</li>
                    <li><i class="fas fa-check"></i> تصدير التقارير</li>
                    <li><i class="fas fa-check"></i> مقارنة الفعاليات</li>
                </ul>
            </div>

            <!-- Feature 10: Customers -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3 class="feature-title">إدارة العملاء</h3>
                <p class="feature-desc">قاعدة بيانات العملاء مع سجل كامل للمشاركات</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> ملفات تعريفية للعملاء</li>
                    <li><i class="fas fa-check"></i> سجل المشاركات</li>
                    <li><i class="fas fa-check"></i> تاريخ الحجوزات</li>
                    <li><i class="fas fa-check"></i> بحث وتصفية متقدم</li>
                </ul>
            </div>

            <!-- Feature 11: Categories -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3 class="feature-title">التصنيفات</h3>
                <p class="feature-desc">تنظيم الفعاليات في تصنيفات مرنة</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> تصنيفات رئيسية وفرعية</li>
                    <li><i class="fas fa-check"></i> أيقونات وألوان مخصصة</li>
                    <li><i class="fas fa-check"></i> تصفية الفعاليات بالتصنيف</li>
                    <li><i class="fas fa-check"></i> عرض في الواجهة الأمامية</li>
                </ul>
            </div>

            <!-- Feature 12: Settings & Support -->
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <h3 class="feature-title">الإعدادات والدعم</h3>
                <p class="feature-desc">تخصيص كامل للنظام مع دعم فني متواصل</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> إعدادات عامة مرنة</li>
                    <li><i class="fas fa-check"></i> تخصيص الألوان والشعار</li>
                    <li><i class="fas fa-check"></i> إعدادات البريد الإلكتروني</li>
                    <li><i class="fas fa-check"></i> نظام تذاكر الدعم</li>
                    <li><i class="fas fa-check"></i> دعم فني 24/7</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Screenshots Section -->
    <section class="screenshots">
        <div class="section-header reveal" style="max-width: 1400px; margin: 0 auto; padding: 0 2rem;">
            <span class="section-badge">معرض الصور</span>
            <h2 class="section-title">لقطات من النظام</h2>
            <p class="section-desc">استعرض واجهات النظام المختلفة وتعرف على تجربة المستخدم</p>
        </div>

        <div class="screenshots-slider">
            <div class="screenshot-card">
                <div class="screenshot-header">
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-title">لوحة التحكم الرئيسية</span>
                </div>
                <div class="screenshot-body">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
            </div>

            <div class="screenshot-card">
                <div class="screenshot-header">
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-title">إدارة الفعاليات</span>
                </div>
                <div class="screenshot-body">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>

            <div class="screenshot-card">
                <div class="screenshot-header">
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-title">قائمة الحضور</span>
                </div>
                <div class="screenshot-body">
                    <i class="fas fa-users"></i>
                </div>
            </div>

            <div class="screenshot-card">
                <div class="screenshot-header">
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-title">ماسح QR</span>
                </div>
                <div class="screenshot-body">
                    <i class="fas fa-qrcode"></i>
                </div>
            </div>

            <div class="screenshot-card">
                <div class="screenshot-header">
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-title">إصدار الشهادات</span>
                </div>
                <div class="screenshot-body">
                    <i class="fas fa-certificate"></i>
                </div>
            </div>

            <div class="screenshot-card">
                <div class="screenshot-header">
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-dot"></span>
                    <span class="screenshot-title">التقارير</span>
                </div>
                <div class="screenshot-body">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="section-header reveal">
            <span class="section-badge">العرض</span>
            <h2 class="section-title">احصل على النظام الآن</h2>
            <p class="section-desc">رخصة كاملة مع جميع المميزات والتحديثات المستقبلية</p>
        </div>

        <div class="pricing-card reveal">
            <div class="pricing-content">
                <span class="pricing-badge">عرض خاص</span>
                <h3 class="pricing-title">رخصة كاملة</h3>
                <p class="pricing-desc">كل ما تحتاجه لإدارة فعالياتك باحترافية</p>

                <ul class="pricing-features">
                    <li><i class="fas fa-check"></i> <span>جميع المميزات المذكورة أعلاه</span></li>
                    <li><i class="fas fa-check"></i> <span>فعاليات وحضور غير محدود</span></li>
                    <li><i class="fas fa-check"></i> <span>تحديثات مجانية لمدة سنة</span></li>
                    <li><i class="fas fa-check"></i> <span>دعم فني لمدة سنة</span></li>
                    <li><i class="fas fa-check"></i> <span>تركيب وإعداد مجاني</span></li>
                    <li><i class="fas fa-check"></i> <span>تدريب على الاستخدام</span></li>
                    <li><i class="fas fa-check"></i> <span>الكود المصدري كامل</span></li>
                    <li><i class="fas fa-check"></i> <span>رخصة استخدام دائمة</span></li>
                </ul>

                <a href="#contact" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fas fa-envelope"></i>
                    تواصل للحصول على عرض سعر
                </a>

                <div class="tech-stack">
                    <div class="tech-badge"><i class="fab fa-wordpress"></i> WordPress</div>
                    <div class="tech-badge"><i class="fab fa-php"></i> PHP</div>
                    <div class="tech-badge"><i class="fab fa-js"></i> JavaScript</div>
                    <div class="tech-badge"><i class="fas fa-database"></i> MySQL</div>
                    <div class="tech-badge"><i class="fab fa-css3-alt"></i> CSS3</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="contact">
        <div class="reveal">
            <h2>جاهز للبدء؟</h2>
            <p>تواصل معنا الآن للحصول على عرض تجريبي مجاني وعرض سعر مخصص</p>

            <a href="#" class="btn btn-primary btn-lg">
                <i class="fas fa-rocket"></i>
                احجز عرض تجريبي مجاني
            </a>

            <div class="contact-info">
                <div class="contact-card">
                    <i class="fab fa-whatsapp"></i>
                    <span>+966 XX XXX XXXX</span>
                </div>
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <span>sales@example.com</span>
                </div>
                <div class="contact-card">
                    <i class="fas fa-globe"></i>
                    <span>www.example.com</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>© <?php echo date('Y'); ?> جميع الحقوق محفوظة - نظام إدارة الفعاليات</p>
    </footer>

    <script>
        // Scroll reveal animation
        function reveal() {
            const reveals = document.querySelectorAll('.reveal');
            reveals.forEach(element => {
                const windowHeight = window.innerHeight;
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;

                if (elementTop < windowHeight - elementVisible) {
                    element.classList.add('active');
                }
            });
        }

        window.addEventListener('scroll', reveal);
        window.addEventListener('load', reveal);

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Counter animation
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 30);
        }

        // Animate counters when visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counters = entry.target.querySelectorAll('.stat-number');
                    counters.forEach(counter => {
                        const text = counter.textContent;
                        if (text.includes('+')) {
                            const num = parseInt(text.replace('+', ''));
                            animateCounter(counter, num);
                            counter.textContent = '+' + counter.textContent;
                        }
                    });
                    observer.unobserve(entry.target);
                }
            });
        });

        const statsSection = document.querySelector('.stats');
        if (statsSection) observer.observe(statsSection);
    </script>
</body>
</html>
