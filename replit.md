# EduLoka

## Overview
Aplikasi Learning Management System (LMS) berbasis PHP untuk manajemen pembelajaran interaktif dengan fitur lengkap.

## Stack Teknologi
- **Backend**: PHP 8.4 dengan PDO
- **Database**: PostgreSQL
- **Frontend**: Bootstrap 5, JavaScript Vanilla
- **Server**: PHP Built-in Server

## Fitur Utama

### Sistem Autentikasi
- 3 Role: Admin, Pengajar, Mahasiswa
- Session-based authentication
- Secure password hashing

### Manajemen Konten
- Program Studi (CRUD)
- Mata Kuliah (CRUD)
- Enrollment System
- Aktivitas: Materi, Video, Quiz, Tugas, Forum Diskusi

### Fitur Pembelajaran
- Quiz dengan auto-grading
- Sistem tugas dengan upload file
- Forum diskusi dengan threading
- Presensi dengan QR code
- File management
- Sistem notifikasi

### Fitur Tambahan
- Dual Language (Indonesia & English) dengan real-time switching
- Light & Dark Theme
- Responsive Design
- Real-time notifications
- AI Chatbot Assistant (powered by OpenAI)

### Gamification System (NEW)
- **Points System**: Poin otomatis untuk quiz (10-100), tugas (50), presensi (10), forum (5), materi (5), video (5)
- **Badge System**: 10 jenis badge (Quiz Master, Perfect Attendance, Active Learner, dll)
- **Leaderboard**: Global dan per-kursus dengan real-time ranking
- **Progress Tracking**: Pelacakan kemajuan kursus per mahasiswa

### Certificate System (NEW)
- **Sertifikat Digital**: Generate otomatis setelah menyelesaikan 80%+ kursus
- **Template Sertifikat**: Desain profesional dengan nomor unik
- **Verifikasi Online**: Sistem verifikasi sertifikat dengan kode unik
- **PDF Export**: Cetak/unduh sertifikat sebagai PDF

### Advanced Analytics (NEW)
- **Dashboard Admin**: Statistik pengguna, kursus, enrollment, dan gamification
- **Dashboard Pengajar**: Analytics per kursus dengan progress mahasiswa
- **Leaderboard Integration**: Top learners dan top courses

## Struktur Database

### Tabel Utama:
- `users` - Pengguna dengan role
- `program_studi` - Program studi
- `kursus` - Mata kuliah
- `kursus_enrollments` - Pendaftaran mahasiswa
- `aktivitas` - Aktivitas pembelajaran
- `tugas` - Tugas/assignments
- `kuis` - Kuis/ujian
- `presensi` - Kehadiran
- `forum_diskusi` - Forum diskusi
- `files` - File/dokumen
- `notifications` - Notifikasi

### Tabel Gamification (NEW):
- `badges` - Definisi badge yang tersedia
- `user_badges` - Badge yang diperoleh user
- `gamification_points` - Riwayat poin user
- `leaderboard_cache` - Cache leaderboard per kursus
- `global_leaderboard` - Ranking global

### Tabel Certificates (NEW):
- `certificates` - Sertifikat yang diterbitkan
- `certificate_templates` - Template sertifikat

### Tabel Analytics (NEW):
- `user_course_progress` - Progress mahasiswa per kursus
- `analytics_summary` - Summary analytics per kursus

## Login Demo
- Username: `admin` | Password: `admin123` | Role: Administrator
- Username: `pengajar1` | Password: `password123` | Role: Pengajar
- Username: `mahasiswa1` | Password: `password123` | Role: Mahasiswa

## Struktur Folder
```
.
├── api/                    # API endpoints
├── assets/                 # Static assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── uploads/           # Upload directory
├── components/            # Reusable components (header, footer)
├── config/                # Configuration files
│   ├── config.php        # Main config
│   ├── database.php      # Database connection
│   ├── lang_id.php       # Indonesian translations
│   └── lang_en.php       # English translations
├── includes/              # Helper services
│   ├── csrf_helper.php   # CSRF protection
│   ├── session_manager.php # Secure sessions
│   ├── rate_limiter.php  # Login rate limiting
│   ├── input_validator.php # Input validation
│   ├── email_service.php # Email notifications
│   └── export_service.php # PDF/Excel export
├── modules/               # Feature modules
│   ├── admin/            # Admin features
│   ├── pengajar/         # Lecturer features
│   └── mahasiswa/        # Student features
├── index.php              # Dashboard
├── login.php              # Login page
└── course_view.php        # Course detail page
```

## Development Notes

### Database Connection
Database menggunakan environment variables dari Replit:
- `DATABASE_URL`
- `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`

### Theme & Language
- Theme disimpan di session dan database user
- Language disimpan di session, cookie, dan database user
- Toggle tersedia di navbar untuk logged-in users

### File Uploads
- Maksimal ukuran: 10MB
- Path: `assets/uploads/`
- Support multiple file types

## User Preferences
- Default language: Indonesia (id)
- Default theme: Light
- Language dapat diubah via dropdown di navbar
- Theme dapat diubah via toggle button di navbar

## Completed Features
- Authentication system (3 roles)
- Database schema (14+ tables)
- CRUD for Program Studi dan Kursus
- Course enrollment system
- Activity framework (5 types: materi, video, quiz, tugas, forum)
- Dual language system (ID/EN)
- Light/Dark theme
- Notification system
- Responsive UI
- SQL injection prevention
- CSRF protection
- Quiz module dengan manajemen soal dan auto-grading
- Sistem presensi dengan QR code
- Dashboard analytics dengan Chart.js
- Tugas submission dan penilaian
- Forum diskusi dengan threading

## Recent Changes
- [2025-12-12] Fixed forum discussion reply bug - removed invalid enrollment.progress reference
- [2025-12-12] Implemented smart activity tracking: auto-mark for materi/video, manual for forum/quiz/tugas
- [2025-12-12] Added real-time language switcher without page refresh
- [2025-12-12] Integrated AI Chatbot assistant powered by OpenAI GPT-4o-mini
- [2025-12-12] Database tables verified: gamification, certificates, analytics all functional
- [2025-12-10] Project setup di Replit dengan PHP 8.2
- [2025-12-10] Database schema setup dengan PostgreSQL
- [2025-12-10] Seed data dengan sample users, kursus, dan aktivitas
- [2025-12-10] Gamification System: badges, points, leaderboard
- [2025-12-10] Certificate System: auto-generate, verification, PDF export
- [2025-12-10] Advanced Analytics: dashboard dengan statistik lengkap
- [2025-12-10] Integrasi gamification ke quiz dan presensi
- [2025-12-10] Database-level duplicate prevention dengan UNIQUE index untuk gamification points
- [2025-12-10] UI untuk mahasiswa: halaman gamification dan certificates di sidebar
- [2025-12-10] Public certificate verification page (verify_certificate.php)

## API Endpoints
- `/api/quiz_submit.php` - Submit quiz dan auto-grading (+ gamification)
- `/api/quiz_manage.php` - CRUD soal quiz
- `/api/quiz_results.php` - Get hasil quiz mahasiswa
- `/api/attendance_qr.php` - Generate/scan QR presensi (+ gamification)
- `/api/notifications.php` - Notification management
- `/api/gamification.php` - Gamification API (points, badges, leaderboard)
- `/api/certificate.php` - Certificate management (issue, verify, list)
- `/api/analytics.php` - Analytics API (dashboard, course stats)
- `/api/chatbot.php` - AI Chatbot assistant (OpenAI GPT-4o-mini)
- `/api/track_activity_access.php` - Smart activity tracking (auto/manual completion)
- `/api/get_translations.php` - Dynamic translations for real-time language switching
- `/api/set_language.php` - Language preference switching
- `/api/post_forum.php` - Forum discussion posts with gamification integration

## Security Features
- CSRF Protection di semua forms dan API endpoints
- Secure Session Management dengan timeout dan fingerprinting
- Rate Limiting untuk login (5 attempts per 15 minutes)
- Input validation dengan sanitizer
- Prepared statements untuk semua database queries

## Environment Variables Required
- `DATABASE_URL` - PostgreSQL connection string (provided by Replit)
- `OPENAI_API_KEY` - OpenAI API key for AI Chatbot feature (optional)
- `SESSION_SECRET` - Session encryption key

## Activity Tracking System
- **Materi & Video**: Auto-mark as completed when opened
- **Forum**: Mark as completed when posting a reply
- **Quiz**: Mark as completed when submitting answers  
- **Tugas**: Mark as completed when submitting assignment
- Points are automatically awarded based on activity type
