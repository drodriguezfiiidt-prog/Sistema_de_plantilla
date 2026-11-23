-- Esquema SQLite de GestioBioV3 (version PHP).
-- Los precios se guardan en centavos (INTEGER) para evitar errores de coma
-- flotante; la capa de servicios los formatea como 1.234,56.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    full_name     TEXT    NOT NULL DEFAULT '',
    is_staff      INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS equipment_families (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL UNIQUE,
    prefix          TEXT    NOT NULL,
    allows_quotes   INTEGER NOT NULL DEFAULT 1,
    support_channel TEXT    NOT NULL DEFAULT '',
    is_active       INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT    NOT NULL,
    updated_at      TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS items (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    name              TEXT    NOT NULL UNIQUE,
    price_cents       INTEGER NOT NULL DEFAULT 0 CHECK (price_cents >= 0),
    is_mandatory_base INTEGER NOT NULL DEFAULT 0,
    is_active         INTEGER NOT NULL DEFAULT 1,
    created_at        TEXT    NOT NULL,
    updated_at        TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS item_family (
    item_id   INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
    family_id INTEGER NOT NULL REFERENCES equipment_families(id) ON DELETE CASCADE,
    PRIMARY KEY (item_id, family_id)
);

CREATE TABLE IF NOT EXISTS diagnostic_tickets (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_number    TEXT    NOT NULL,
    serial_number    TEXT    NOT NULL,
    family_id        INTEGER REFERENCES equipment_families(id) ON DELETE SET NULL,
    warranty_status  TEXT    NOT NULL DEFAULT 'Vencida',
    technical_report TEXT    NOT NULL DEFAULT '',
    total_cents      INTEGER NOT NULL DEFAULT 0 CHECK (total_cents >= 0),
    document_body    TEXT    NOT NULL DEFAULT '',
    created_by       INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at       TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS ticket_items (
    ticket_id INTEGER NOT NULL REFERENCES diagnostic_tickets(id) ON DELETE CASCADE,
    item_id   INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
    PRIMARY KEY (ticket_id, item_id)
);

CREATE INDEX IF NOT EXISTS idx_tickets_number ON diagnostic_tickets(ticket_number);
CREATE INDEX IF NOT EXISTS idx_tickets_serial ON diagnostic_tickets(serial_number);
CREATE INDEX IF NOT EXISTS idx_tickets_created ON diagnostic_tickets(created_at);
