
-- Fix forum_diskusi sequence
SELECT setval('forum_diskusi_id_seq', COALESCE((SELECT MAX(id) FROM forum_diskusi), 0) + 1, false);

-- Fix activity_access sequence (if exists)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'activity_access_id_seq') THEN
        PERFORM setval('activity_access_id_seq', COALESCE((SELECT MAX(id) FROM activity_access), 0) + 1, false);
    END IF;
END $$;

-- Fix tugas sequence
SELECT setval('tugas_id_seq', COALESCE((SELECT MAX(id) FROM tugas), 0) + 1, false);

-- Fix kuis sequence
SELECT setval('kuis_id_seq', COALESCE((SELECT MAX(id) FROM kuis), 0) + 1, false);

-- Verify sequences
SELECT 'forum_diskusi' as table_name, 
       (SELECT MAX(id) FROM forum_diskusi) as max_id,
       nextval('forum_diskusi_id_seq') as next_seq,
       currval('forum_diskusi_id_seq') as current_seq;

SELECT 'tugas' as table_name,
       (SELECT MAX(id) FROM tugas) as max_id,
       nextval('tugas_id_seq') as next_seq,
       currval('tugas_id_seq') as current_seq;

-- Create unique constraint on activity_access if not exists
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'activity_access_unique_constraint'
    ) THEN
        ALTER TABLE activity_access 
        ADD CONSTRAINT activity_access_unique_constraint 
        UNIQUE (aktivitas_id, mahasiswa_id);
    END IF;
END $$;
