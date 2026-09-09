-- Stale references and one impossible time.
--
-- Run once per database (local first, then production). Every change is backed
-- up into scev_sc_datafix_20260909 first, so it can be undone — the rollback
-- statements are at the foot of this file.
--
-- Written against the scev_ prefix. Change it if yours differs.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS scev_sc_datafix_20260909 (
  src_table   VARCHAR(64)  NOT NULL,
  row_id      BIGINT       NOT NULL,
  col         VARCHAR(64)  NOT NULL,
  old_value   VARCHAR(255) NULL,
  PRIMARY KEY (src_table, row_id, col)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1. Attendees point at posts that no longer exist.
--    wp_post_id held the Eventin attendee post id captured during migration.
--    Those posts were deleted and their ids were later reused by coupon posts,
--    so 6,322 attendees now "belong to" a coupon and 744 to nothing at all.
--    No attendee has ever had an sc_attendee post. Two places read this: the
--    dashboard hands it out as the attendee's id, and the attendance summary
--    is calculated from it — both wrong while it points elsewhere. NULL is
--    what "no post" means here, and 4,515 rows already say that.
INSERT IGNORE INTO scev_sc_datafix_20260909 (src_table, row_id, col, old_value)
SELECT 'sc_attendees', a.id, 'wp_post_id', a.wp_post_id
FROM scev_sc_attendees a
LEFT JOIN scev_posts p ON p.ID = a.wp_post_id AND p.post_type = 'sc_attendee'
WHERE a.wp_post_id > 0 AND p.ID IS NULL;

UPDATE scev_sc_attendees a
LEFT JOIN scev_posts p ON p.ID = a.wp_post_id AND p.post_type = 'sc_attendee'
SET a.wp_post_id = NULL
WHERE a.wp_post_id > 0 AND p.ID IS NULL;

-- 2. Same problem on two events: event 1 points at an attachment, event 2 at a
--    coupon. The other three events already carry NULL.
INSERT IGNORE INTO scev_sc_datafix_20260909 (src_table, row_id, col, old_value)
SELECT 'sc_events', e.id, 'wp_post_id', e.wp_post_id
FROM scev_sc_events e
LEFT JOIN scev_posts p ON p.ID = e.wp_post_id AND p.post_type = 'sc_event'
WHERE e.wp_post_id > 0 AND p.ID IS NULL;

UPDATE scev_sc_events e
LEFT JOIN scev_posts p ON p.ID = e.wp_post_id AND p.post_type = 'sc_event'
SET e.wp_post_id = NULL
WHERE e.wp_post_id > 0 AND p.ID IS NULL;

-- 3. IDC 2026 runs from 21:00 to 18:00, which cannot happen. The two sibling
--    congresses at the same venue both run 09:00-17:00, and so does the design
--    for this one, so that is what it is set to. Change it here if the real
--    hours differ.
INSERT IGNORE INTO scev_sc_datafix_20260909 (src_table, row_id, col, old_value)
SELECT 'sc_events', id, 'start_time', start_time FROM scev_sc_events
WHERE id = 5 AND start_time = '21:00:00' AND end_time = '18:00:00';

INSERT IGNORE INTO scev_sc_datafix_20260909 (src_table, row_id, col, old_value)
SELECT 'sc_events', id, 'end_time', end_time FROM scev_sc_events
WHERE id = 5 AND start_time = '21:00:00' AND end_time = '18:00:00';

UPDATE scev_sc_events
SET start_time = '09:00:00', end_time = '17:00:00'
WHERE id = 5 AND start_time = '21:00:00' AND end_time = '18:00:00';

COMMIT;

-- To undo:
--
--   UPDATE scev_sc_attendees a
--     JOIN scev_sc_datafix_20260909 b
--       ON b.src_table = 'sc_attendees' AND b.row_id = a.id AND b.col = 'wp_post_id'
--     SET a.wp_post_id = b.old_value;
--
--   UPDATE scev_sc_events e
--     JOIN scev_sc_datafix_20260909 b
--       ON b.src_table = 'sc_events' AND b.row_id = e.id AND b.col = 'wp_post_id'
--     SET e.wp_post_id = b.old_value;
--
--   UPDATE scev_sc_events e
--     JOIN scev_sc_datafix_20260909 b
--       ON b.src_table = 'sc_events' AND b.row_id = e.id AND b.col = 'start_time'
--     SET e.start_time = b.old_value;
--
--   UPDATE scev_sc_events e
--     JOIN scev_sc_datafix_20260909 b
--       ON b.src_table = 'sc_events' AND b.row_id = e.id AND b.col = 'end_time'
--     SET e.end_time = b.old_value;
