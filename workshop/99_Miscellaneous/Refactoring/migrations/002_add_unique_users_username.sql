-- 002_add_unique_users_username.sql
ALTER TABLE users
    ADD UNIQUE KEY uq_users_username (username);
