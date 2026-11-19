-- 4.4.0: app tokens carry a scope. Existing tokens keep "bookings" only; new ones get "bookings footpaths".
ALTER TABLE `#__ra_app_tokens` ADD COLUMN `scope` VARCHAR(100) NOT NULL DEFAULT 'bookings' AFTER `client_id`;
