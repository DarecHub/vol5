-- Sailing App – databázové schema
-- Vygenerováno z kódu aplikace

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Nastavení aplikace
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lodě (vždy 2)
CREATE TABLE IF NOT EXISTS boats (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL DEFAULT 'Loď',
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uživatelé (členové posádky)
CREATE TABLE IF NOT EXISTS users (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(100) NOT NULL,
    phone   VARCHAR(50)  DEFAULT NULL,
    email   VARCHAR(150) DEFAULT NULL,
    boat_id INT          NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Výdaje pokladny
CREATE TABLE IF NOT EXISTS wallet_expenses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    paid_by       INT          NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    currency      VARCHAR(3)   NOT NULL DEFAULT 'EUR',
    amount_eur    DECIMAL(10,2) NOT NULL,
    exchange_rate DECIMAL(10,4) DEFAULT NULL,
    description   TEXT         NOT NULL,
    category      VARCHAR(50)  NOT NULL DEFAULT 'ostatni',
    expense_date  DATETIME     NOT NULL,
    split_type    VARCHAR(20)  NOT NULL DEFAULT 'both',
    created_by    INT          DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    KEY idx_paid_by (paid_by),
    KEY idx_expense_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rozdělení výdajů na osoby
CREATE TABLE IF NOT EXISTS wallet_expense_splits (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    expense_id INT           NOT NULL,
    user_id    INT           NOT NULL,
    amount_eur DECIMAL(10,2) NOT NULL,
    KEY idx_expense_id (expense_id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log výdajů
CREATE TABLE IF NOT EXISTS wallet_audit_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    expense_id  INT          NOT NULL,
    changed_by  INT          DEFAULT NULL,
    change_type VARCHAR(20)  NOT NULL,
    old_values  TEXT         DEFAULT NULL,
    new_values  TEXT         DEFAULT NULL,
    changed_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    KEY idx_expense_id (expense_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Označená vyrovnání
CREATE TABLE IF NOT EXISTS wallet_settled (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT      NOT NULL,
    to_user_id   INT      NOT NULL,
    settled_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    settled_by   INT      DEFAULT NULL,
    UNIQUE KEY uniq_pair (from_user_id, to_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nákupní seznam
CREATE TABLE IF NOT EXISTS shopping_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    boat_id     INT          NOT NULL,
    category    VARCHAR(50)  NOT NULL DEFAULT 'ostatni',
    item_name   VARCHAR(200) NOT NULL,
    quantity    VARCHAR(100) DEFAULT NULL,
    assigned_to INT          DEFAULT NULL,
    price       DECIMAL(10,2) DEFAULT NULL,
    currency    VARCHAR(3)   NOT NULL DEFAULT 'EUR',
    note        TEXT         DEFAULT NULL,
    is_bought   TINYINT(1)   NOT NULL DEFAULT 0,
    bought_by   INT          DEFAULT NULL,
    created_by  INT          DEFAULT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    KEY idx_boat_id (boat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deník plavby
CREATE TABLE IF NOT EXISTS logbook (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    boat_id          INT          NOT NULL,
    date             DATE         NOT NULL,
    location_from    VARCHAR(200) NOT NULL DEFAULT '',
    location_to      VARCHAR(200) NOT NULL DEFAULT '',
    nautical_miles   DECIMAL(6,1) NOT NULL DEFAULT 0,
    departure_time   TIME         DEFAULT NULL,
    arrival_time     TIME         DEFAULT NULL,
    skipper_user_id  INT          DEFAULT NULL,
    note             TEXT         DEFAULT NULL,
    created_by       INT          DEFAULT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    KEY idx_boat_date (boat_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jídelníček (tabulka se jmenuje menu_plan, ne menu_entries)
CREATE TABLE IF NOT EXISTS menu_plan (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    boat_id          INT         NOT NULL,
    date             DATE        NOT NULL,
    meal_type        VARCHAR(20) NOT NULL DEFAULT 'obed',
    cook_user_id     INT         DEFAULT NULL,
    meal_description TEXT        DEFAULT NULL,
    note             TEXT        DEFAULT NULL,
    created_by       INT         DEFAULT NULL,
    created_at       DATETIME    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_boat_date_meal (boat_id, date, meal_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auta
CREATE TABLE IF NOT EXISTS cars (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    driver_user_id INT          NOT NULL,
    car_name       VARCHAR(100) DEFAULT NULL,
    seats          INT          NOT NULL DEFAULT 5,
    note           TEXT         DEFAULT NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spolujezdci
CREATE TABLE IF NOT EXISTS car_passengers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    car_id     INT NOT NULL,
    user_id    INT NOT NULL,
    added_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_car_id (car_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itinerář
CREATE TABLE IF NOT EXISTS itinerary (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    day_number    INT          NOT NULL DEFAULT 0,
    date          DATE         DEFAULT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT         DEFAULT NULL,
    location_from VARCHAR(100) DEFAULT NULL,
    location_to   VARCHAR(100) DEFAULT NULL,
    type          VARCHAR(20)  NOT NULL DEFAULT 'sailing',
    sort_order    INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist (co s sebou)
CREATE TABLE IF NOT EXISTS checklist (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(50)  NOT NULL DEFAULT 'doporucene',
    item_name   VARCHAR(200) NOT NULL,
    description TEXT         DEFAULT NULL,
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
