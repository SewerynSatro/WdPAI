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

CREATE TABLE IF NOT EXISTS user_profiles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    bio TEXT,
    city VARCHAR(100),
    birth_date DATE,
    gender VARCHAR(20),
    looking_for VARCHAR(20),
    avatar_path VARCHAR(500),
    instagram_handle VARCHAR(100),
    facebook_handle VARCHAR(100),
    spotify_handle VARCHAR(100),
    latitude DOUBLE PRECISION,
    longitude DOUBLE PRECISION,
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
