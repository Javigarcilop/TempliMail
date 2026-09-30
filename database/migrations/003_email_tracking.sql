-- Migracion 003: seguimiento de aperturas y clics por entrega.
--   docker compose exec -T db mysql -uroot templimail_db < database/migrations/003_email_tracking.sql

ALTER TABLE email_deliveries
  ADD COLUMN opened_at    DATETIME NULL AFTER sent_at,
  ADD COLUMN open_count   INT NOT NULL DEFAULT 0 AFTER opened_at,
  ADD COLUMN clicked_at   DATETIME NULL AFTER open_count,
  ADD COLUMN click_count  INT NOT NULL DEFAULT 0 AFTER clicked_at;
