-- EduLoka Database Schema - Current Production Schema
-- This is the actual database structure currently in use with all features implemented

-- Drop existing tables (in correct order to respect foreign keys)
DROP TABLE IF EXISTS activity_logs CASCADE;
DROP TABLE IF EXISTS activity_access CASCADE;
DROP TABLE IF EXISTS messages CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS forum_diskusi CASCADE;
DROP TABLE IF EXISTS presensi_records CASCADE;
DROP TABLE IF EXISTS presensi CASCADE;
DROP TABLE IF EXISTS kuis_jawaban CASCADE;
DROP TABLE IF EXISTS kuis_soal CASCADE;
DROP TABLE IF EXISTS question_options CASCADE;
DROP TABLE IF EXISTS question_bank CASCADE;
DROP TABLE IF EXISTS kuis CASCADE;
DROP TABLE IF EXISTS tugas_submission CASCADE;
DROP TABLE IF EXISTS tugas CASCADE;
DROP TABLE IF EXISTS aktivitas_tipe CASCADE;
DROP TABLE IF EXISTS files CASCADE;
DROP TABLE IF EXISTS aktivitas CASCADE;
DROP TABLE IF EXISTS course_sessions CASCADE;
DROP TABLE IF EXISTS app_settings CASCADE;
DROP TABLE IF EXISTS kursus_enrollments CASCADE;
DROP TABLE IF EXISTS kursus CASCADE;
DROP TABLE IF EXISTS gradebook CASCADE;
DROP TABLE IF EXISTS program_studi CASCADE;
DROP TABLE IF EXISTS fakultas CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Users table dengan role dan profile
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'pengajar', 'mahasiswa')),
    avatar VARCHAR(255),
    profile_photo VARCHAR(255),
    language VARCHAR(5) DEFAULT 'id',
    theme VARCHAR(10) DEFAULT 'light',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fakultas (Faculty)
CREATE TABLE fakultas (
    id SERIAL PRIMARY KEY,
    kode VARCHAR(20) UNIQUE NOT NULL,
    nama_id VARCHAR(100) NOT NULL,
    nama_en VARCHAR(100) NOT NULL,
    dekan_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    deskripsi_id TEXT,
    deskripsi_en TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Program Studi
CREATE TABLE program_studi (
    id SERIAL PRIMARY KEY,
    fakultas_id INTEGER REFERENCES fakultas(id) ON DELETE CASCADE,
    kode VARCHAR(20) UNIQUE NOT NULL,
    nama_id VARCHAR(100) NOT NULL,
    nama_en VARCHAR(100) NOT NULL,
    jenjang VARCHAR(20) NOT NULL DEFAULT 'S1' CHECK (jenjang IN ('S1', 'S2', 'S3', 'Profesi')),
    kepala_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    deskripsi_id TEXT,
    deskripsi_en TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kursus/Mata Kuliah
CREATE TABLE kursus (
    id SERIAL PRIMARY KEY,
    program_studi_id INTEGER REFERENCES program_studi(id) ON DELETE CASCADE,
    pengajar_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    kode VARCHAR(20) UNIQUE NOT NULL,
    nama_id VARCHAR(100) NOT NULL,
    nama_en VARCHAR(100) NOT NULL,
    deskripsi_id TEXT,
    deskripsi_en TEXT,
    sks INTEGER NOT NULL,
    semester INTEGER,
    thumbnail VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kursus Enrollments
CREATE TABLE kursus_enrollments (
    id SERIAL PRIMARY KEY,
    kursus_id INTEGER REFERENCES kursus(id) ON DELETE CASCADE,
    mahasiswa_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'completed', 'dropped')),
    nilai_akhir DECIMAL(5,2),
    UNIQUE(kursus_id, mahasiswa_id)
);

-- Gradebook (Daftar Nilai)
CREATE TABLE gradebook (
    id SERIAL PRIMARY KEY,
    kursus_id INTEGER NOT NULL REFERENCES kursus(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    final_score NUMERIC,
    grade VARCHAR(2),
    notes TEXT,
    updated_at TIMESTAMP,
    attendance_score NUMERIC,
    participation_score NUMERIC,
    assignment_score NUMERIC,
    uts_score NUMERIC,
    uas_score NUMERIC,
    attendance_total INTEGER,
    meetings_total INTEGER,
    UNIQUE(kursus_id, user_id)
);

-- App Settings
CREATE TABLE app_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Course Sessions (Sesi Pertemuan)
CREATE TABLE course_sessions (
    id SERIAL PRIMARY KEY,
    kursus_id INTEGER NOT NULL REFERENCES kursus(id) ON DELETE CASCADE,
    nomor_pertemuan INTEGER NOT NULL,
    judul_id VARCHAR(255) NOT NULL,
    judul_en VARCHAR(255) NOT NULL,
    deskripsi_id TEXT,
    deskripsi_en TEXT,
    tanggal_mulai DATE,
    tanggal_akhir DATE,
    urutan INTEGER,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Aktivitas (materi, video, quiz, tugas, forum)
CREATE TABLE aktivitas (
    id SERIAL PRIMARY KEY,
    kursus_id INTEGER REFERENCES kursus(id) ON DELETE CASCADE,
    judul_id VARCHAR(200) NOT NULL,
    judul_en VARCHAR(200) NOT NULL,
    deskripsi_id TEXT,
    deskripsi_en TEXT,
    tipe VARCHAR(20) NOT NULL CHECK (tipe IN ('materi', 'video', 'quiz', 'tugas', 'forum')),
    urutan INTEGER DEFAULT 0,
    is_published BOOLEAN DEFAULT TRUE,
    session_id INTEGER REFERENCES course_sessions(id) ON DELETE SET NULL,
    video_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Aktivitas Tipe
CREATE TABLE aktivitas_tipe (
    id SERIAL PRIMARY KEY,
    aktivitas_id INTEGER REFERENCES aktivitas(id) ON DELETE CASCADE,
    tipe VARCHAR(20) NOT NULL CHECK (tipe IN ('materi', 'video', 'quiz', 'tugas', 'forum')),
    UNIQUE(aktivitas_id, tipe)
);

-- Files (untuk materi dan video)
CREATE TABLE files (
    id SERIAL PRIMARY KEY,
    aktivitas_id INTEGER REFERENCES aktivitas(id) ON DELETE CASCADE,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INTEGER,
    video_url VARCHAR(500),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tugas
CREATE TABLE tugas (
    id SERIAL PRIMARY KEY,
    aktivitas_id INTEGER REFERENCES aktivitas(id) ON DELETE CASCADE,
    instruksi_id TEXT NOT NULL,
    instruksi_en TEXT NOT NULL,
    deadline TIMESTAMP,
    max_score INTEGER DEFAULT 100,
    allow_late_submission BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tugas Submission
CREATE TABLE tugas_submission (
    id SERIAL PRIMARY KEY,
    tugas_id INTEGER REFERENCES tugas(id) ON DELETE CASCADE,
    mahasiswa_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    file_path VARCHAR(500),
    jawaban_text TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(5,2),
    feedback TEXT,
    graded_by INTEGER REFERENCES users(id),
    graded_at TIMESTAMP,
    UNIQUE(tugas_id, mahasiswa_id)
);

-- Kuis
CREATE TABLE kuis (
    id SERIAL PRIMARY KEY,
    aktivitas_id INTEGER REFERENCES aktivitas(id) ON DELETE CASCADE,
    durasi INTEGER,
    max_attempts INTEGER DEFAULT 1,
    passing_score NUMERIC DEFAULT 60,
    show_correct_answers BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kuis Soal
CREATE TABLE kuis_soal (
    id SERIAL PRIMARY KEY,
    kuis_id INTEGER REFERENCES kuis(id) ON DELETE CASCADE,
    soal_id VARCHAR(255),
    soal_en VARCHAR(255),
    tipe VARCHAR(50) DEFAULT 'multiple_choice',
    pilihan_json TEXT,
    jawaban_benar VARCHAR(255),
    poin INTEGER DEFAULT 10,
    urutan INTEGER,
    keywords TEXT,
    keyword_weight NUMERIC DEFAULT 1.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kuis Jawaban (student answers)
CREATE TABLE kuis_jawaban (
    id SERIAL PRIMARY KEY,
    kuis_id INTEGER REFERENCES kuis(id) ON DELETE CASCADE,
    mahasiswa_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    soal_id INTEGER REFERENCES kuis_soal(id) ON DELETE CASCADE,
    jawaban TEXT,
    is_correct BOOLEAN,
    score NUMERIC,
    attempt INTEGER DEFAULT 1,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Question Bank (Bank Soal)
CREATE TABLE question_bank (
    id SERIAL PRIMARY KEY,
    kursus_id INTEGER NOT NULL REFERENCES kursus(id) ON DELETE CASCADE,
    category VARCHAR(100),
    question_text TEXT NOT NULL,
    question_type VARCHAR(50),
    difficulty_level VARCHAR(20),
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Question Options
CREATE TABLE question_options (
    id SERIAL PRIMARY KEY,
    question_id INTEGER NOT NULL REFERENCES question_bank(id),
    option_text TEXT NOT NULL,
    is_correct BOOLEAN,
    display_order INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Presensi
CREATE TABLE presensi (
    id SERIAL PRIMARY KEY,
    kursus_id INTEGER REFERENCES kursus(id) ON DELETE CASCADE,
    pertemuan INTEGER NOT NULL,
    tanggal DATE NOT NULL,
    qr_code VARCHAR(255),
    qr_expired_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Presensi Records
CREATE TABLE presensi_records (
    id SERIAL PRIMARY KEY,
    presensi_id INTEGER REFERENCES presensi(id) ON DELETE CASCADE,
    mahasiswa_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'hadir' CHECK (status IN ('hadir', 'izin', 'sakit', 'alpha')),
    waktu_presensi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(presensi_id, mahasiswa_id)
);

-- Forum Diskusi
CREATE TABLE forum_diskusi (
    id SERIAL PRIMARY KEY,
    aktivitas_id INTEGER REFERENCES aktivitas(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES forum_diskusi(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    konten TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages (Sistem Pesan)
CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    judul_id VARCHAR(200) NOT NULL,
    judul_en VARCHAR(200) NOT NULL,
    pesan_id TEXT NOT NULL,
    pesan_en TEXT NOT NULL,
    tipe VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Activity Access Tracking
CREATE TABLE activity_access (
    id SERIAL PRIMARY KEY,
    aktivitas_id INTEGER REFERENCES aktivitas(id) ON DELETE CASCADE,
    mahasiswa_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Activity Logs
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INTEGER,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_kursus_enrollments_mahasiswa ON kursus_enrollments(mahasiswa_id);
CREATE INDEX idx_kursus_enrollments_kursus ON kursus_enrollments(kursus_id);
CREATE INDEX idx_aktivitas_kursus ON aktivitas(kursus_id);
CREATE INDEX idx_aktivitas_session ON aktivitas(session_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_forum_parent ON forum_diskusi(parent_id);
CREATE INDEX idx_course_sessions_kursus ON course_sessions(kursus_id);
CREATE INDEX idx_gradebook_kursus_user ON gradebook(kursus_id, user_id);
CREATE INDEX idx_activity_access ON activity_access(aktivitas_id, mahasiswa_id);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id, created_at);
CREATE INDEX idx_program_studi_fakultas ON program_studi(fakultas_id);
CREATE INDEX idx_program_studi_jenjang ON program_studi(jenjang);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, role) 
VALUES ('admin', 'admin@emodule.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');