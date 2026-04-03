CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "two_factor_secret" text,
  "two_factor_recovery_codes" text,
  "two_factor_confirmed_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "areas"(
  "id" integer primary key autoincrement not null,
  "dataset_id" integer not null,
  "code" varchar not null,
  "name" varchar not null,
  "echoid" varchar,
  "group_id" varchar,
  "sort_order" integer not null default '0',
  "message_count" integer,
  "unread_count" integer,
  "last_read_msgno" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("dataset_id") references "datasets"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "datasets"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "source_type" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "drafts"(
  "id" integer primary key autoincrement not null,
  "dataset_id" integer not null,
  "area_id" integer,
  "reply_to_message_id" integer,
  "subject" varchar not null,
  "from_name" varchar not null,
  "to_name" varchar not null,
  "body_text" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("dataset_id") references "datasets"("id") on delete cascade,
  foreign key("area_id") references "areas"("id") on delete set null,
  foreign key("reply_to_message_id") references "messages"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "messages"(
  "id" integer primary key autoincrement not null,
  "dataset_id" integer not null,
  "area_id" integer not null,
  "msgno" integer not null,
  "external_id" varchar,
  "subject" varchar not null,
  "from_name" varchar not null,
  "from_address" varchar,
  "to_name" varchar not null,
  "to_address" varchar,
  "body_text" text not null,
  "reply_to_msgno" integer,
  "reply_to_external_id" varchar,
  "reply1st_msgno" integer,
  "replynext_msgno" integer,
  "thread_key" varchar,
  "attributes_raw" integer not null default '0',
  "posted_at" datetime,
  "arrived_at" datetime,
  "is_read" tinyint(1) not null default '0',
  "is_marked" tinyint(1) not null default '0',
  "is_bookmarked" tinyint(1) not null default '0',
  "raw_metadata_json" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("dataset_id") references "datasets"("id") on delete cascade,
  foreign key("area_id") references "areas"("id") on delete cascade
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_08_14_170933_add_two_factor_columns_to_users_table',1);
INSERT INTO migrations VALUES(5,'2026_03_30_074351_create_areas_table',2);
INSERT INTO migrations VALUES(6,'2026_03_30_074351_create_datasets_table',2);
INSERT INTO migrations VALUES(7,'2026_03_30_074352_create_drafts_table',2);
INSERT INTO migrations VALUES(8,'2026_03_30_074352_create_messages_table',2);
