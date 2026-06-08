-- HeartBeat base application database schema.
-- This keeps the existing app columns while adding the tables needed by the target app.

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,

    firstname VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password TEXT NOT NULL,

    display_name VARCHAR(50),
    role VARCHAR(20) NOT NULL DEFAULT 'USER',

    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);

UPDATE users
SET email = 'archived_' || id || '_' || email
WHERE email = 'admin@heartbeat.dev'
  AND id != 1;

INSERT INTO users (id, firstname, email, password, display_name, role, is_active)
VALUES (
    1,
    'HeartBeat Admin',
    'admin@heartbeat.dev',
    '$2y$10$46SH2KEGxzFUlnAFFGYo3uELCVSRMN7EMKvQKymp/W5e0GJVc3C7m',
    'HeartBeat Admin',
    'ADMIN',
    TRUE
)
ON CONFLICT (id) DO UPDATE SET
    firstname = EXCLUDED.firstname,
    email = EXCLUDED.email,
    password = EXCLUDED.password,
    display_name = EXCLUDED.display_name,
    role = EXCLUDED.role,
    is_active = TRUE,
    updated_at = CURRENT_TIMESTAMP;

SELECT setval('users_id_seq', GREATEST((SELECT MAX(id) FROM users), 1), TRUE);

CREATE TABLE IF NOT EXISTS user_profiles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    bio TEXT,
    birth_date DATE,
    gender VARCHAR(20),
    looking_for VARCHAR(20),
    avatar_path VARCHAR(500),
    instagram_handle VARCHAR(100),
    facebook_handle VARCHAR(100),
    spotify_handle VARCHAR(100),
    latitude DOUBLE PRECISION,
    longitude DOUBLE PRECISION,
    max_distance_km INTEGER NOT NULL DEFAULT 50,
    onboarding_completed BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS provider_accounts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider_key VARCHAR(30) NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT,
    token_expires_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, provider_key)
);

CREATE TABLE IF NOT EXISTS user_artists (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider_key VARCHAR(30) NOT NULL,
    time_range VARCHAR(20) NOT NULL DEFAULT 'medium_term',
    artist_id VARCHAR(100) NOT NULL,
    artist_name VARCHAR(255) NOT NULL,
    artist_image_url VARCHAR(500),
    rank INTEGER NOT NULL DEFAULT 0,
    synced_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, provider_key, time_range, artist_id)
);
CREATE INDEX IF NOT EXISTS idx_user_artists_user ON user_artists (user_id);

CREATE TABLE IF NOT EXISTS user_tracks (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider_key VARCHAR(30) NOT NULL,
    time_range VARCHAR(20) NOT NULL DEFAULT 'medium_term',
    track_id VARCHAR(100) NOT NULL,
    track_name VARCHAR(255) NOT NULL,
    artist_name VARCHAR(255) NOT NULL,
    album_name VARCHAR(255),
    album_image_url VARCHAR(500),
    duration_ms INTEGER NOT NULL DEFAULT 0,
    spotify_url VARCHAR(500),
    rank INTEGER NOT NULL DEFAULT 0,
    synced_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, provider_key, time_range, track_id)
);
CREATE INDEX IF NOT EXISTS idx_user_tracks_user ON user_tracks (user_id);

CREATE TABLE IF NOT EXISTS user_recent_plays (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider_key VARCHAR(30) NOT NULL,
    track_id VARCHAR(100) NOT NULL,
    track_name VARCHAR(255) NOT NULL,
    artist_name VARCHAR(255) NOT NULL,
    album_name VARCHAR(255),
    album_image_url VARCHAR(500),
    played_at TIMESTAMP WITH TIME ZONE NOT NULL,
    synced_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_user_recent_user ON user_recent_plays (user_id);

CREATE TABLE IF NOT EXISTS user_genres (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider_key VARCHAR(30) NOT NULL,
    genre VARCHAR(100) NOT NULL,
    weight INTEGER NOT NULL DEFAULT 1,
    synced_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, provider_key, genre)
);
CREATE INDEX IF NOT EXISTS idx_user_genres_user ON user_genres (user_id);

CREATE TABLE IF NOT EXISTS user_now_playing (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    track_id VARCHAR(100),
    track_name VARCHAR(255),
    artist_name VARCHAR(255),
    album_image_url VARCHAR(500),
    spotify_url VARCHAR(500),
    is_playing BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_now_playing_track ON user_now_playing (track_id);

CREATE TABLE IF NOT EXISTS swipes (
    id SERIAL PRIMARY KEY,
    swiper_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    target_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    direction VARCHAR(10) NOT NULL,
    swiped_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swiper_id, target_id),
    CHECK (swiper_id != target_id),
    CHECK (direction IN ('LIKE', 'PASS'))
);
CREATE INDEX IF NOT EXISTS idx_swipes_swiper ON swipes (swiper_id);
CREATE INDEX IF NOT EXISTS idx_swipes_target ON swipes (target_id);

CREATE TABLE IF NOT EXISTS matches (
    id SERIAL PRIMARY KEY,
    user_a_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user_b_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    matched_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_a_id, user_b_id),
    CHECK (user_a_id < user_b_id)
);
CREATE INDEX IF NOT EXISTS idx_matches_user_a ON matches (user_a_id);
CREATE INDEX IF NOT EXISTS idx_matches_user_b ON matches (user_b_id);

CREATE TABLE IF NOT EXISTS user_reports (
    id SERIAL PRIMARY KEY,
    reporter_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reported_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reason VARCHAR(255) NOT NULL DEFAULT 'Reported from profile',
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP WITH TIME ZONE,
    action_note VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (reporter_id, reported_user_id),
    CHECK (reporter_id != reported_user_id),
    CHECK (status IN ('OPEN', 'RESOLVED'))
);
CREATE INDEX IF NOT EXISTS idx_user_reports_reported ON user_reports (reported_user_id);
CREATE INDEX IF NOT EXISTS idx_user_reports_status ON user_reports (status);

CREATE OR REPLACE FUNCTION touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_touch_updated_at ON users;
CREATE TRIGGER trg_users_touch_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_user_profiles_touch_updated_at ON user_profiles;
CREATE TRIGGER trg_user_profiles_touch_updated_at
BEFORE UPDATE ON user_profiles
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_provider_accounts_touch_updated_at ON provider_accounts;
CREATE TRIGGER trg_provider_accounts_touch_updated_at
BEFORE UPDATE ON provider_accounts
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_user_reports_touch_updated_at ON user_reports;
CREATE TRIGGER trg_user_reports_touch_updated_at
BEFORE UPDATE ON user_reports
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_user_now_playing_touch_updated_at ON user_now_playing;
CREATE TRIGGER trg_user_now_playing_touch_updated_at
BEFORE UPDATE ON user_now_playing
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

CREATE OR REPLACE FUNCTION heartbeat_distance_km(
    lat_a DOUBLE PRECISION,
    lng_a DOUBLE PRECISION,
    lat_b DOUBLE PRECISION,
    lng_b DOUBLE PRECISION
)
RETURNS NUMERIC AS $$
BEGIN
    IF lat_a IS NULL OR lng_a IS NULL OR lat_b IS NULL OR lng_b IS NULL THEN
        RETURN NULL;
    END IF;

    RETURN ROUND((
        6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(lat_a))
            * COS(RADIANS(lat_b))
            * COS(RADIANS(lng_b) - RADIANS(lng_a))
            + SIN(RADIANS(lat_a))
            * SIN(RADIANS(lat_b))
        )))
    )::numeric, 1);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE VIEW admin_report_summary AS
SELECT
    r.reported_user_id,
    COUNT(*) AS total_reports,
    COUNT(*) FILTER (WHERE r.status = 'OPEN') AS open_reports,
    COUNT(*) FILTER (WHERE r.status = 'RESOLVED') AS resolved_reports,
    MAX(r.created_at) AS last_reported_at,
    MAX(r.reviewed_at) AS last_reviewed_at,
    u.email,
    COALESCE(u.display_name, u.firstname) AS display_name,
    u.role,
    u.is_active
FROM user_reports r
JOIN users u ON u.id = r.reported_user_id
GROUP BY r.reported_user_id, u.email, u.display_name, u.firstname, u.role, u.is_active;
