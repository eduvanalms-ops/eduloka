-- EduLoka Actual Production Data - Extracted from Live Database
-- This file contains the actual data currently in use in the application

-- ===== FAKULTAS (FACULTY) =====
INSERT INTO fakultas (id, kode, nama_id, nama_en, dekan_id, deskripsi_id, deskripsi_en, created_at) VALUES
(1, 'FIP', 'Fakultas Ilmu Pendidikan', 'Faculty of Education', NULL, 'Fakultas yang bertanggung jawab pada pendidikan dan pengajaran', 'Faculty responsible for education and teaching', CURRENT_TIMESTAMP),
(2, 'FBSH', 'Fakultas Bahasa, Seni, dan Humaniora', 'Faculty of Languages, Arts, and Humanities', NULL, 'Fakultas yang fokus pada bahasa, seni, dan humaniora', 'Faculty focused on languages, arts, and humanities', CURRENT_TIMESTAMP),
(3, 'FISE', 'Fakultas Ilmu Sosial dan Ekonomi', 'Faculty of Social Sciences and Economics', NULL, 'Fakultas yang mengembangkan ilmu sosial dan ekonomi', 'Faculty developing social sciences and economics', CURRENT_TIMESTAMP),
(4, 'FT', 'Fakultas Teknik', 'Faculty of Engineering', NULL, 'Fakultas yang mendalami teknik dan teknologi', 'Faculty studying engineering and technology', CURRENT_TIMESTAMP),
(5, 'FK', 'Fakultas Kedokteran', 'Faculty of Medicine', NULL, 'Fakultas kesehatan dan kedokteran', 'Faculty of health and medicine', CURRENT_TIMESTAMP),
(6, 'FMIPA', 'Fakultas Matematika dan Ilmu Pengetahuan Alam', 'Faculty of Mathematics and Natural Sciences', NULL, 'Fakultas yang fokus pada matematika dan MIPA', 'Faculty focused on mathematics and natural sciences', CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- ===== USERS (ACTUAL) =====
INSERT INTO users (id, username, email, password, full_name, role, avatar, language, theme, created_at, updated_at, profile_photo) VALUES
(1, 'admin', 'admin@eduloka.org', '$2y$12$xGQYiVQShfLS5JJXNKSRY.ZFm9q4hfaAunmBrEClN0UhXVfadWlEq', 'Administrator', 'admin', NULL, 'id', 'light', '2025-11-20 22:26:20.498989', '2025-11-20 22:26:20.498989', '/assets/uploads/profiles/profile_1_1763730627.png'),
(2, 'pengajar1', 'dosen1@eduloka.org', '$2y$12$cg8hBJm.qQxClVXNsfq/mOOKiiHeJll70.Zs5TwGjr9du4FduVL/m', 'Akun Demo Pengajar1', 'pengajar', NULL, 'id', 'light', '2025-11-20 22:33:22.607987', '2025-11-20 22:33:22.607987', NULL),
(3, 'pengajar2', 'dosen2@eduloka.org', '$2y$12$cg8hBJm.qQxClVXNsfq/mOOKiiHeJll70.Zs5TwGjr9du4FduVL/m', 'Prof. Sandy Ramdhani, Escool', 'pengajar', NULL, 'id', 'light', '2025-11-20 22:33:22.607987', '2025-11-20 22:33:22.607987', NULL),
(4, 'mahasiswa1', 'student1@eduloka.org', '$2y$12$cg8hBJm.qQxClVXNsfq/mOOKiiHeJll70.Zs5TwGjr9du4FduVL/m', 'Akun Demo Mahasiswa1', 'mahasiswa', NULL, 'id', 'light', '2025-11-20 22:33:22.607987', '2025-11-20 22:33:22.607987', NULL),
(5, 'amirbagja', 'student2@eduloka.org', '$2y$12$cg8hBJm.qQxClVXNsfq/mOOKiiHeJll70.Zs5TwGjr9du4FduVL/m', 'Amir Bagja', 'mahasiswa', NULL, 'id', 'light', '2025-11-20 22:33:22.607987', '2025-11-20 22:33:22.607987', NULL),
(7, 'samsullutfi', 'samsullutfi@eduloka.org', '$2y$12$d/XcuPOkh4dR01xPiA4NpuiJYMr4Vv0Or5ISzUmM3ARBIU42xIVEO', 'Dr. H. Samsul Lutfi, S.Pd., M.Pd.', 'pengajar', NULL, 'id', 'light', '2025-11-20 22:53:26.176807', '2025-11-20 22:53:26.176807', NULL),
(8, 'husnulmukti', 'husnulmukti@eduloka.org', '$2y$12$89a/u7zK7w23jJm3g9W3y.8SiGkkaBSmKNAbA.zDH4Yh5/vpl1pxG', 'Husnul Mukti', 'mahasiswa', NULL, 'id', 'light', '2025-11-20 23:15:48.608112', '2025-11-20 23:15:48.608112', NULL)
ON CONFLICT (username) DO NOTHING;

-- ===== PROGRAM STUDI (ACTUAL) =====
INSERT INTO program_studi (id, fakultas_id, kode, nama_id, nama_en, jenjang, kepala_id, deskripsi_id, deskripsi_en, created_at) VALUES
(1, 4, 'TI', 'Teknik Informatika', 'Informatics Engineering', 'S1', NULL, 'Program studi yang mempelajari ilmu komputer dan teknologi informasi', 'A program that studies computer science and information technology', '2025-11-20 22:33:22.731862'),
(2, 3, 'SI', 'Sistem Informasi', 'Information Systems', 'S1', NULL, 'Program studi yang fokus pada pengelolaan sistem informasi bisnis', 'A program focused on business information systems management', '2025-11-20 22:33:22.731862'),
(3, 6, 'IF', 'Ilmu Komputer', 'Computer Science', 'S2', NULL, 'Program studi yang mempelajari teori dan praktik komputasi', 'A program that studies computational theory and practice', '2025-11-20 22:33:22.731862'),
(4, 1, 'PIN', 'Pendidikan Informatika', 'Informatics Education', 'S1', NULL, 'Program studi yang fokus pada pendidikan teknologi informasi', 'Program focused on information technology education', '2025-11-20 22:52:07.874397')
ON CONFLICT (kode) DO NOTHING;

-- ===== KURSUS (ACTUAL) =====
INSERT INTO kursus (id, program_studi_id, pengajar_id, kode, nama_id, nama_en, deskripsi_id, deskripsi_en, sks, semester, thumbnail, is_active, created_at) VALUES
(7, 4, 7, 'PIN1121', 'Etika Profesi', 'Ethics in Profession', 'Mata kuliah Etika Profesi membahas prinsip-prinsip moral, norma, dan standar perilaku yang harus dipegang dalam menjalankan sebuah profesi untuk memastikan profesionalisme, tanggung jawab, dan integritas. Mata kuliah ini bertujuan agar mahasiswa mampu memahami, mengimplementasikan, dan menerapkan etika profesi dalam praktik kerja mereka, termasuk etiket, kode etik, dan penyelesaian kasus moral yang relevan dengan bidang keilmuan masing-masing. ', 'This course discusses moral principles, norms, and behavioral standards that must be upheld in carrying out a profession to ensure professionalism, responsibility, and integrity.', 2, 3, NULL, true, '2025-11-21 04:05:07.855752'),
(8, 4, 3, 'PPG865', 'KE-NWDI-AN', 'Islamic Values and Nahdlatul Wathan Studies', 'Mata kuliah KE-NWDI-AN memiliki fokus utama pada pengenalan dan pendalaman pemahaman tentang ajaran Islam, sejarah, pemikiran, serta perjuangan pendiri Nahdlatul Wathan (NW) dan organisasinya. Tujuannya adalah membentuk mahasiswa menjadi pribadi yang beriman dan bertakwa, serta mampu memahami dan meneruskan nilai-nilai perjuangan NW di bidang pendidikan, sosial, dan dakwah. ', 'Focus on Islamic teachings, history, and philosophy of Nahdlatul Wathan organization and its values', 2, 1, NULL, true, '2025-11-21 04:59:03.270088'),
(9, 3, 2, 'IF101', 'Pengantar Pemrograman', 'Introduction to Programming', 'Mata kuliah dasar pemrograman menggunakan Python', 'Fundamental programming course using Python for beginners', 3, 1, NULL, true, '2025-11-21 05:00:48.025256'),
(10, 1, 7, 'TI201', 'Struktur Data dan Algoritma', 'Data Structures and Algorithms', 'Mempelajari berbagai struktur data dan algoritma fundamental', 'Learn various data structures and fundamental algorithms', 4, 5, NULL, true, '2025-11-21 05:02:42.693193')
ON CONFLICT (kode) DO NOTHING;

-- ===== KURSUS ENROLLMENTS (ACTUAL) =====
INSERT INTO kursus_enrollments (id, kursus_id, mahasiswa_id, enrolled_at, status, nilai_akhir) VALUES
(7, 8, 8, '2025-11-21 05:09:07.846443', 'active', NULL),
(8, 7, 8, '2025-11-21 05:09:15.445007', 'active', NULL),
(9, 9, 8, '2025-11-21 05:09:24.507196', 'active', NULL),
(10, 10, 8, '2025-11-21 05:09:35.587313', 'active', NULL),
(11, 7, 5, '2025-11-21 05:14:32.550546', 'active', NULL),
(12, 8, 5, '2025-11-21 05:14:34.059244', 'active', NULL),
(13, 10, 5, '2025-11-21 05:14:36.939772', 'active', NULL),
(14, 9, 5, '2025-11-21 05:14:38.706488', 'active', NULL)
ON CONFLICT (kursus_id, mahasiswa_id) DO NOTHING;

-- ===== COURSE SESSIONS (ACTUAL) =====
INSERT INTO course_sessions (id, kursus_id, nomor_pertemuan, judul_id, judul_en, deskripsi_id, deskripsi_en, tanggal_mulai, tanggal_akhir, urutan, is_published, created_at) VALUES
(1, 7, 1, 'Pengantar Mata Kuliah', 'Pengantar Mata Kuliah', 'Pengantar Mata Kuliah', 'Pengantar Mata Kuliah', '2025-11-22', '2025-11-22', 0, true, '2025-11-22 02:30:51.271019'),
(4, 7, 2, 'Teori Belajar dan Media', 'Learning Theory and Media', 'Teori Belajar dan Media', NULL, '2025-11-24', '2025-11-24', 0, true, '2025-11-22 02:48:07.393173'),
(5, 7, 3, 'Prinsip dan Kriteria Pemilihan Media', 'Principles and Criteria for Media Selection', NULL, NULL, '2025-11-25', '2025-11-25', 0, true, '2025-11-22 14:16:27.10427'),
(6, 7, 4, 'Etiket di Kantor', 'Office Etiquette', 'Mahasiswa mempelajari tata cara berperilaku di lingkungan kantor, termasuk sopan santun, sikap kerja, aturan berpakaian, serta cara berkomunikasi profesional baik secara langsung maupun melalui media komunikasi kantor.', NULL, NULL, NULL, 0, true, '2025-11-22 14:48:48.904456')
ON CONFLICT DO NOTHING;

-- ===== AKTIVITAS (ACTUAL) =====
INSERT INTO aktivitas (id, kursus_id, judul_id, judul_en, deskripsi_id, deskripsi_en, tipe, urutan, is_published, created_at, session_id, video_url) VALUES
(12, 10, 'Pertemuan 1', 'Meeting 1', 'https://www.youtube.com/watch?v=vFeGhbZKc3U', NULL, 'video', 0, true, '2025-11-21 07:18:10.277205', NULL, NULL),
(14, 7, 'Pengantar Mata Kuliah', 'Pengantar Mata Kuliah', 'Pengantar Mata Kuliah', 'Pengantar Mata Kuliah', 'materi', 0, true, '2025-11-22 02:31:29.708928', 1, NULL),
(17, 7, 'Materi Kedua', 'Second Material', 'Video Pembelajaran', NULL, 'video', 0, true, '2025-11-22 03:00:16.733989', 4, 'https://www.youtube.com/embed/vFeGhbZKc3U'),
(19, 7, 'Sistematika Etika Profesi', 'Systematics of Professional Ethics', 'Sistematika Etika Profesi', NULL, 'materi', 0, true, '2025-11-22 14:36:44.256951', 5, NULL),
(21, 7, 'Tugas Mahasiswa', 'Student Assignment', 'Tugas Pertemuan 4', NULL, 'tugas', 0, true, '2025-11-22 15:31:21.714282', 6, NULL),
(22, 7, 'Tugas Pertemuan 2', 'Meeting 2 Assignment', 'Tugas Pertemuan 2', NULL, 'tugas', 0, true, '2025-11-22 16:39:22.07977', 4, NULL),
(24, 7, 'Kegiatan Refleksi Mahasiswa', 'Student Reflection Activity', NULL, 'Kegiatan Refleksi Mahasiswa', 'forum', 0, true, '2025-11-23 01:44:12.289543', 1, NULL),
(25, 7, 'Kegiatan Refleksi Mahasiswa', 'Student Reflection Activity', 'Silahkan kerjakan studi kasus berikut1', NULL, 'forum', 0, true, '2025-11-23 07:09:30.757426', 5, NULL)
ON CONFLICT DO NOTHING;

-- ===== TUGAS (ACTUAL) =====
INSERT INTO tugas (id, aktivitas_id, instruksi_id, instruksi_en, deadline, max_score, allow_late_submission, created_at) VALUES
(1, 21, 'Kerjakan tugas ini dengan baik', 'Complete this assignment well', NULL, 100, true, '2025-11-22 15:33:22.287915'),
(2, 22, 'Tugas Pertemuan 2', 'Tugas Pertemuan 2', NULL, 100, true, '2025-11-22 16:39:48.979478')
ON CONFLICT DO NOTHING;

-- ===== FORUM DISKUSI (ACTUAL) =====
INSERT INTO forum_diskusi (id, aktivitas_id, parent_id, user_id, konten, created_at, updated_at) VALUES
(1, 24, NULL, 1, 'Asacjdajbabb c bcabbhbbb', '2025-11-23 07:16:42.56095', '2025-11-23 07:16:42.56095'),
(2, 25, NULL, 7, 'coba dulu', '2025-11-23 07:17:17.302566', '2025-11-23 07:17:17.302566'),
(3, 25, NULL, 7, 'Uji coba dulu ya kak', '2025-11-23 07:20:38.152515', '2025-11-23 07:20:38.152515'),
(4, 25, NULL, 1, 'Terimakasih atas responnya', '2025-11-23 07:25:30.212176', '2025-11-23 07:25:30.212176'),
(5, 24, NULL, 8, '>Terimakasih pak', '2025-11-23 07:56:50.738167', '2025-11-23 07:56:50.738167'),
(6, 25, NULL, 8, '>Terimakasih Pak', '2025-11-23 07:57:43.121649', '2025-11-23 07:57:43.121649'),
(7, 24, NULL, 5, '>Untuk hal ini, bagaimana selanjutnya?', '2025-11-23 09:10:43.565171', '2025-11-23 09:10:43.565171'),
(8, 25, NULL, 5, '>baik pak', '2025-11-23 09:12:15.568304', '2025-11-23 09:12:15.568304')
ON CONFLICT DO NOTHING;

-- ===== GRADEBOOK (ACTUAL) =====
INSERT INTO gradebook (id, kursus_id, user_id, final_score, grade, notes, updated_at, attendance_score, participation_score, assignment_score, uts_score, uas_score, attendance_total, meetings_total) VALUES
(1, 7, 5, 86.93, 'A', NULL, '2025-11-23 09:14:27.709497', 83.33, 92.86, 90.00, 80.00, 90.00, NULL, NULL),
(3, 7, 8, 63.60, 'C', NULL, '2025-11-23 08:00:46.556321', 83.33, 100.00, 83.00, 78.00, 0.00, NULL, NULL)
ON CONFLICT (kursus_id, user_id) DO NOTHING;

-- ===== APP SETTINGS =====
INSERT INTO app_settings (setting_key, setting_value, created_at, updated_at) VALUES
('app_name', 'EduLoka', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('app_version', '1.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('language_default', 'id', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('theme_default', 'light', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (setting_key) DO NOTHING;

-- ===== NOTIFICATIONS (Selamat datang untuk mahasiswa) =====
INSERT INTO notifications (user_id, judul_id, judul_en, pesan_id, pesan_en, tipe, is_read) VALUES
(4, 'Selamat Datang!', 'Welcome!', 'Selamat datang di EduLoka. Mulai belajar sekarang!', 'Welcome to EduLoka. Start learning now!', 'info', false),
(5, 'Selamat Datang!', 'Welcome!', 'Selamat datang di EduLoka. Mulai belajar sekarang!', 'Welcome to EduLoka. Start learning now!', 'info', false),
(8, 'Selamat Datang!', 'Welcome!', 'Selamat datang di EduLoka. Mulai belajar sekarang!', 'Welcome to EduLoka. Start learning now!', 'info', false)
ON CONFLICT DO NOTHING;
