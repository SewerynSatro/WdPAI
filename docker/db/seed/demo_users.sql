BEGIN;

CREATE TEMP TABLE heartbeat_seed_people ON COMMIT DROP AS
WITH base AS (
    SELECT
        gs,
        ARRAY[
            'Ala', 'Bartek', 'Celina', 'Dawid', 'Ela', 'Filip', 'Gosia', 'Hubert',
            'Iga', 'Jan', 'Kasia', 'Lena', 'Maja', 'Natalia', 'Ola', 'Pawel',
            'Tomek', 'Zosia', 'Ania', 'Milosz'
        ] AS first_names,
        ARRAY[
            'Neon', 'LoFi', 'Vinyl', 'Bass', 'Indie', 'Synth', 'Jazz', 'Echo',
            'Tempo', 'Groove', 'Dream', 'Pulse', 'Wave', 'Mixtape', 'Reverb'
        ] AS nicknames,
        ARRAY[
            'Szukam ludzi do koncertow, playlist i nocnych rozmow o muzyce.',
            'Najlepiej dogaduje sie przy dobrej kawie i jeszcze lepszym refrenie.',
            'Lubie odkrywac nowe brzmienia i wymieniac sie albumami.',
            'Weekend zaczynam od koncertu albo dlugiego spaceru ze sluchawkami.',
            'Muzyka jest dla mnie filtrem do poznawania ludzi i miejsc.',
            'Cenie szczere rozmowy, kameralne koncerty i dobrze ulozone playlisty.'
        ] AS bios,
        ARRAY[
            'Warszawa', 'Krakow', 'Wroclaw', 'Poznan', 'Gdansk', 'Lodz',
            'Katowice', 'Lublin', 'Bialystok', 'Rzeszow', 'Szczecin', 'Bydgoszcz',
            'Olsztyn', 'Torun', 'Kielce', 'Opole', 'Zielona Gora', 'Prague',
            'Berlin', 'Bratislava', 'Vilnius', 'Lviv'
        ] AS place_names,
        ARRAY[
            52.2297, 50.0647, 51.1079, 52.4064, 54.3520, 51.7592,
            50.2649, 51.2465, 53.1325, 50.0412, 53.4285, 53.1235,
            53.7784, 53.0138, 50.8661, 50.6751, 51.9356, 50.0755,
            52.5200, 48.1486, 54.6872, 49.8397
        ]::double precision[] AS place_lats,
        ARRAY[
            21.0122, 19.9450, 17.0385, 16.9252, 18.6466, 19.4550,
            19.0238, 22.5684, 23.1688, 21.9991, 14.5528, 18.0084,
            20.4801, 18.5984, 20.6286, 17.9213, 15.5062, 14.4378,
            13.4050, 17.1077, 25.2797, 24.0297
        ]::double precision[] AS place_lngs
    FROM generate_series(1, 20) AS gs
)
SELECT
    gs,
    format('hb_seed_%s@example.test', lpad(gs::text, 3, '0')) AS email,
    first_names[((gs - 1) % array_length(first_names, 1)) + 1] AS first_name,
    first_names[((gs - 1) % array_length(first_names, 1)) + 1]
        || ' '
        || nicknames[((gs - 1) % array_length(nicknames, 1)) + 1]
        || ' '
        || lpad(gs::text, 3, '0') AS display_name,
    bios[((gs - 1) % array_length(bios, 1)) + 1] AS bio,
    (CURRENT_DATE
        - make_interval(years => (18 + (gs % 28)))
        - (((gs * 11) % 365) * INTERVAL '1 day'))::date AS birth_date,
    (ARRAY['female', 'male', 'female', 'male', 'non-binary', 'other'])
        [((gs - 1) % 6) + 1] AS gender,
    (ARRAY['everyone', 'everyone', 'female', 'male'])
        [((gs - 1) % 4) + 1] AS looking_for,
    round((place_lats[((gs - 1) % array_length(place_lats, 1)) + 1]
        + (((gs * 37) % 100) - 50) / 700.0)::numeric, 6)::double precision AS latitude,
    round((place_lngs[((gs - 1) % array_length(place_lngs, 1)) + 1]
        + (((gs * 53) % 100) - 50) / 700.0)::numeric, 6)::double precision AS longitude,
    (ARRAY[25, 50, 75, 100, 150, 200])[((gs - 1) % 6) + 1] AS max_distance_km,
    '@hb_seed_' || lpad(gs::text, 3, '0') AS instagram_handle,
    'hb_seed_' || lpad(gs::text, 3, '0') AS spotify_handle
FROM base;

WITH upserted AS (
    INSERT INTO users (firstname, email, password, display_name, role, is_active)
    SELECT
        first_name,
        email,
        '$2y$10$uZ2u4DkqQYpY0IL4i4TPP.PYJQJZLzOa2N6tP8nWmmykOPkiE3U7i',
        display_name,
        'USER',
        TRUE
    FROM heartbeat_seed_people
    ON CONFLICT (email) DO UPDATE SET
        firstname = EXCLUDED.firstname,
        display_name = EXCLUDED.display_name,
        is_active = TRUE,
        updated_at = CURRENT_TIMESTAMP
    RETURNING id, email
)
INSERT INTO user_profiles (
    user_id,
    bio,
    birth_date,
    gender,
    looking_for,
    instagram_handle,
    spotify_handle,
    latitude,
    longitude,
    max_distance_km,
    onboarding_completed
)
SELECT
    u.id,
    p.bio,
    p.birth_date,
    p.gender,
    p.looking_for,
    p.instagram_handle,
    p.spotify_handle,
    p.latitude,
    p.longitude,
    p.max_distance_km,
    TRUE
FROM upserted u
JOIN heartbeat_seed_people p ON p.email = u.email
ON CONFLICT (user_id) DO UPDATE SET
    bio = EXCLUDED.bio,
    birth_date = EXCLUDED.birth_date,
    gender = EXCLUDED.gender,
    looking_for = EXCLUDED.looking_for,
    instagram_handle = EXCLUDED.instagram_handle,
    spotify_handle = EXCLUDED.spotify_handle,
    latitude = EXCLUDED.latitude,
    longitude = EXCLUDED.longitude,
    max_distance_km = EXCLUDED.max_distance_km,
    onboarding_completed = TRUE,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO provider_accounts (
    user_id,
    provider_key,
    access_token,
    refresh_token,
    token_expires_at
)
SELECT
    u.id,
    'spotify',
    'demo-access-token-' || p.gs,
    'demo-refresh-token-' || p.gs,
    CURRENT_TIMESTAMP + INTERVAL '30 days'
FROM users u
JOIN heartbeat_seed_people p ON p.email = u.email
ON CONFLICT (user_id, provider_key) DO UPDATE SET
    access_token = EXCLUDED.access_token,
    refresh_token = EXCLUDED.refresh_token,
    token_expires_at = EXCLUDED.token_expires_at,
    updated_at = CURRENT_TIMESTAMP;

WITH genre_catalog AS (
    SELECT ARRAY[
        'polish indie', 'pop', 'rock', 'electronic', 'hip hop', 'jazz',
        'alternative', 'folk', 'synthpop', 'r&b', 'metal', 'house',
        'techno', 'soul', 'ambient', 'punk'
    ] AS genres
),
seeded AS (
    SELECT u.id AS user_id, p.gs
    FROM users u
    JOIN heartbeat_seed_people p ON p.email = u.email
)
INSERT INTO user_genres (user_id, provider_key, genre, weight)
SELECT
    s.user_id,
    'spotify',
    c.genres[((s.gs + g.pos) % array_length(c.genres, 1)) + 1],
    100 - (g.pos * 12)
FROM seeded s
CROSS JOIN genre_catalog c
CROSS JOIN generate_series(0, 4) AS g(pos)
ON CONFLICT (user_id, provider_key, genre) DO UPDATE SET
    weight = EXCLUDED.weight,
    synced_at = CURRENT_TIMESTAMP;

WITH artist_catalog AS (
    SELECT ARRAY[
        'Dawid Podsiadlo', 'Sanah', 'Kortez', 'Mela Koteluk', 'Taco Hemingway',
        'Brodka', 'Artur Rojek', 'Myslovitz', 'The Dumplings', 'Nosowska',
        'O.S.T.R.', 'Kwiat Jabloni', 'Zalewski', 'Ralph Kaminski', 'Natalia Przybysz',
        'Bokka', 'Fisz Emade Tworzywo', 'Sorry Boys', 'Bitamina', 'Bass Astral'
    ] AS artists
),
seeded AS (
    SELECT u.id AS user_id, p.gs
    FROM users u
    JOIN heartbeat_seed_people p ON p.email = u.email
),
artist_rows AS (
    SELECT
        s.user_id,
        s.gs,
        tr.time_range,
        r.rank,
        ((s.gs + r.rank + tr.range_offset) % array_length(c.artists, 1)) + 1 AS artist_idx,
        c.artists[((s.gs + r.rank + tr.range_offset) % array_length(c.artists, 1)) + 1] AS artist_name
    FROM seeded s
    CROSS JOIN artist_catalog c
    CROSS JOIN (VALUES ('long_term', 0), ('medium_term', 4), ('short_term', 8)) AS tr(time_range, range_offset)
    CROSS JOIN generate_series(1, 8) AS r(rank)
)
INSERT INTO user_artists (
    user_id,
    provider_key,
    time_range,
    artist_id,
    artist_name,
    artist_image_url,
    rank
)
SELECT
    user_id,
    'spotify',
    time_range,
    'demo-artist-' || artist_idx,
    artist_name,
    'https://picsum.photos/seed/hb-artist-' || artist_idx || '/320/320',
    rank
FROM artist_rows
ON CONFLICT (user_id, provider_key, time_range, artist_id) DO UPDATE SET
    artist_name = EXCLUDED.artist_name,
    artist_image_url = EXCLUDED.artist_image_url,
    rank = EXCLUDED.rank,
    synced_at = CURRENT_TIMESTAMP;

WITH track_catalog AS (
    SELECT ARRAY[
        'Nocny puls', 'Miasto gra', 'Dobre fale', 'Swiatlo nad Wisla',
        'Powrot na peron', 'Letni bit', 'Cichy refren', 'Neonowy deszcz',
        'Spacer po plytach', 'Kawa i bas', 'Daleki koncert', 'Mapa dzwiekow',
        'Szum miasta', 'Zielony rytm', 'Polnocny groove', 'Wspolny takt'
    ] AS tracks
),
artist_catalog AS (
    SELECT ARRAY[
        'Dawid Podsiadlo', 'Sanah', 'Kortez', 'Mela Koteluk', 'Taco Hemingway',
        'Brodka', 'Artur Rojek', 'Myslovitz', 'The Dumplings', 'Nosowska',
        'O.S.T.R.', 'Kwiat Jabloni', 'Zalewski', 'Ralph Kaminski', 'Natalia Przybysz',
        'Bokka', 'Fisz Emade Tworzywo', 'Sorry Boys', 'Bitamina', 'Bass Astral'
    ] AS artists
),
seeded AS (
    SELECT u.id AS user_id, p.gs
    FROM users u
    JOIN heartbeat_seed_people p ON p.email = u.email
),
track_rows AS (
    SELECT
        s.user_id,
        s.gs,
        tr.time_range,
        r.rank,
        ((s.gs + r.rank + tr.range_offset) % array_length(tc.tracks, 1)) + 1 AS track_idx,
        ((s.gs + r.rank + tr.range_offset) % array_length(ac.artists, 1)) + 1 AS artist_idx,
        tc.tracks[((s.gs + r.rank + tr.range_offset) % array_length(tc.tracks, 1)) + 1] AS track_name,
        ac.artists[((s.gs + r.rank + tr.range_offset) % array_length(ac.artists, 1)) + 1] AS artist_name
    FROM seeded s
    CROSS JOIN track_catalog tc
    CROSS JOIN artist_catalog ac
    CROSS JOIN (VALUES ('long_term', 0), ('medium_term', 5), ('short_term', 10)) AS tr(time_range, range_offset)
    CROSS JOIN generate_series(1, 8) AS r(rank)
)
INSERT INTO user_tracks (
    user_id,
    provider_key,
    time_range,
    track_id,
    track_name,
    artist_name,
    album_name,
    album_image_url,
    duration_ms,
    spotify_url,
    rank
)
SELECT
    user_id,
    'spotify',
    time_range,
    'demo-track-' || track_idx || '-' || artist_idx,
    track_name,
    artist_name,
    'HeartBeat Demo ' || artist_idx,
    'https://picsum.photos/seed/hb-album-' || artist_idx || '-' || track_idx || '/320/320',
    180000 + (track_idx * 7000),
    'https://open.spotify.com/track/demo' || track_idx || artist_idx,
    rank
FROM track_rows
ON CONFLICT (user_id, provider_key, time_range, track_id) DO UPDATE SET
    track_name = EXCLUDED.track_name,
    artist_name = EXCLUDED.artist_name,
    album_name = EXCLUDED.album_name,
    album_image_url = EXCLUDED.album_image_url,
    duration_ms = EXCLUDED.duration_ms,
    spotify_url = EXCLUDED.spotify_url,
    rank = EXCLUDED.rank,
    synced_at = CURRENT_TIMESTAMP;

DELETE FROM user_recent_plays
WHERE provider_key = 'spotify'
  AND track_id LIKE 'demo-recent-%'
  AND user_id IN (
      SELECT u.id
      FROM users u
      JOIN heartbeat_seed_people p ON p.email = u.email
  );

WITH recent_rows AS (
    SELECT
        u.id AS user_id,
        p.gs,
        r.rank,
        ((p.gs + r.rank) % 16) + 1 AS track_idx,
        ((p.gs + r.rank) % 20) + 1 AS artist_idx
    FROM users u
    JOIN heartbeat_seed_people p ON p.email = u.email
    CROSS JOIN generate_series(1, 10) AS r(rank)
)
INSERT INTO user_recent_plays (
    user_id,
    provider_key,
    track_id,
    track_name,
    artist_name,
    album_name,
    album_image_url,
    played_at
)
SELECT
    user_id,
    'spotify',
    'demo-recent-' || track_idx || '-' || artist_idx,
    (ARRAY[
        'Nocny puls', 'Miasto gra', 'Dobre fale', 'Swiatlo nad Wisla',
        'Powrot na peron', 'Letni bit', 'Cichy refren', 'Neonowy deszcz',
        'Spacer po plytach', 'Kawa i bas', 'Daleki koncert', 'Mapa dzwiekow',
        'Szum miasta', 'Zielony rytm', 'Polnocny groove', 'Wspolny takt'
    ])[track_idx],
    (ARRAY[
        'Dawid Podsiadlo', 'Sanah', 'Kortez', 'Mela Koteluk', 'Taco Hemingway',
        'Brodka', 'Artur Rojek', 'Myslovitz', 'The Dumplings', 'Nosowska',
        'O.S.T.R.', 'Kwiat Jabloni', 'Zalewski', 'Ralph Kaminski', 'Natalia Przybysz',
        'Bokka', 'Fisz Emade Tworzywo', 'Sorry Boys', 'Bitamina', 'Bass Astral'
    ])[artist_idx],
    'HeartBeat Demo ' || artist_idx,
    'https://picsum.photos/seed/hb-recent-' || artist_idx || '-' || track_idx || '/320/320',
    CURRENT_TIMESTAMP - ((rank * 3 + gs % 48) * INTERVAL '1 hour')
FROM recent_rows;

WITH now_rows AS (
    SELECT
        u.id AS user_id,
        p.gs,
        ((p.gs + 3) % 16) + 1 AS track_idx,
        ((p.gs + 5) % 20) + 1 AS artist_idx
    FROM users u
    JOIN heartbeat_seed_people p ON p.email = u.email
)
INSERT INTO user_now_playing (
    user_id,
    track_id,
    track_name,
    artist_name,
    album_image_url,
    spotify_url,
    is_playing
)
SELECT
    user_id,
    'demo-now-' || track_idx || '-' || artist_idx,
    (ARRAY[
        'Nocny puls', 'Miasto gra', 'Dobre fale', 'Swiatlo nad Wisla',
        'Powrot na peron', 'Letni bit', 'Cichy refren', 'Neonowy deszcz',
        'Spacer po plytach', 'Kawa i bas', 'Daleki koncert', 'Mapa dzwiekow',
        'Szum miasta', 'Zielony rytm', 'Polnocny groove', 'Wspolny takt'
    ])[track_idx],
    (ARRAY[
        'Dawid Podsiadlo', 'Sanah', 'Kortez', 'Mela Koteluk', 'Taco Hemingway',
        'Brodka', 'Artur Rojek', 'Myslovitz', 'The Dumplings', 'Nosowska',
        'O.S.T.R.', 'Kwiat Jabloni', 'Zalewski', 'Ralph Kaminski', 'Natalia Przybysz',
        'Bokka', 'Fisz Emade Tworzywo', 'Sorry Boys', 'Bitamina', 'Bass Astral'
    ])[artist_idx],
    'https://picsum.photos/seed/hb-now-' || artist_idx || '-' || track_idx || '/320/320',
    'https://open.spotify.com/track/demo-now-' || track_idx || artist_idx,
    (gs % 3 = 0)
FROM now_rows
ON CONFLICT (user_id) DO UPDATE SET
    track_id = EXCLUDED.track_id,
    track_name = EXCLUDED.track_name,
    artist_name = EXCLUDED.artist_name,
    album_image_url = EXCLUDED.album_image_url,
    spotify_url = EXCLUDED.spotify_url,
    is_playing = EXCLUDED.is_playing,
    updated_at = CURRENT_TIMESTAMP;

COMMIT;
