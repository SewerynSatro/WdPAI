CREATE TABLE users (
    -- Klucz główny generowany automatycznie
    id SERIAL PRIMARY KEY,
    
    -- Dane użytkownika z podstawową walidacją
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password TEXT NOT NULL,
    
    -- Opcjonalne pole profilowe
    full_name VARCHAR(100),
    
    -- Metadane systemowe
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);