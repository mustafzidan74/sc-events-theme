-- 128 injected spam posts.
--
-- Every published "post" on this site is warez SEO spam — TeamViewer keygens,
-- AutoCAD cracks, KMSpico — written to post_author 2 between 2026-04-11 and
-- 2026-05-01. One of them, titled and bodied 0x88247ac9, is a marker: tooling
-- writes it to confirm it has write access. 117 comments hang off them.
--
-- They are moved to trash rather than deleted, so WordPress can restore them
-- from the admin if any of this turns out to be wanted. Trashed posts return
-- 404, which is what search engines need to see.
--
-- The site's own content — events, speakers, tickets, pages — is not touched:
-- this only matches post_type 'post'. The one post by author 1 is WordPress's
-- own "Hello world!" and is left alone.
--
-- Run on production only after confirming the same rows are there. Afterwards,
-- ask Google Search Console to remove the URLs; trashing them here does not
-- clear them from the index on its own.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS scev_sc_spamfix_20260909 (
  post_id    BIGINT      NOT NULL,
  old_status VARCHAR(20) NOT NULL,
  post_title TEXT        NULL,
  PRIMARY KEY (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO scev_sc_spamfix_20260909 (post_id, old_status, post_title)
SELECT ID, post_status, post_title FROM scev_posts
WHERE post_type = 'post' AND post_status = 'publish' AND post_author = 2;

-- WordPress needs these two to offer a Restore button.
INSERT INTO scev_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_wp_trash_meta_status', post_status FROM scev_posts
WHERE post_type = 'post' AND post_status = 'publish' AND post_author = 2;

INSERT INTO scev_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_wp_trash_meta_time', UNIX_TIMESTAMP() FROM scev_posts
WHERE post_type = 'post' AND post_status = 'publish' AND post_author = 2;

UPDATE scev_posts SET post_status = 'trash'
WHERE post_type = 'post' AND post_status = 'publish' AND post_author = 2;

COMMIT;

-- To undo:
--
--   UPDATE scev_posts p JOIN scev_sc_spamfix_20260909 b ON b.post_id = p.ID
--     SET p.post_status = b.old_status;
--   DELETE FROM scev_postmeta
--     WHERE meta_key IN ('_wp_trash_meta_status', '_wp_trash_meta_time')
--       AND post_id IN (SELECT post_id FROM scev_sc_spamfix_20260909);
--
-- To make it permanent later, empty the trash from the admin, or:
--
--   DELETE FROM scev_comments WHERE comment_post_ID IN (SELECT post_id FROM scev_sc_spamfix_20260909);
--   DELETE FROM scev_postmeta WHERE post_id IN (SELECT post_id FROM scev_sc_spamfix_20260909);
--   DELETE FROM scev_posts    WHERE ID      IN (SELECT post_id FROM scev_sc_spamfix_20260909);
