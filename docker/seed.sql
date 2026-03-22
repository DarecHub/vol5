-- Sailing App – výchozí data pro testování
-- Admin heslo: admin123
-- Členské heslo: crew123

SET NAMES utf8mb4;

-- Lodě
INSERT INTO boats (id, name, description) VALUES
(1, 'Loď 1', 'Hlavní loď'),
(2, 'Loď 2', 'Druhá loď');

-- Testovací uživatelé
INSERT INTO users (name, phone, email, boat_id) VALUES
('Pavel Novák',   '+420 601 111 111', 'pavel@example.com', 1),
('Jana Horáková', '+420 602 222 222', 'jana@example.com',  1),
('Tomáš Krejčí',  '+420 603 333 333', 'tomas@example.com', 1),
('Lucie Marková',  NULL,              'lucie@example.com', 2),
('Martin Blaha',   '+420 605 555 555', NULL,               2),
('Eva Procházková', NULL,             NULL,                2);

-- Nastavení aplikace
INSERT INTO settings (setting_key, setting_value) VALUES
('installed',            '1'),
('trip_name',            'Loď vol.5 – Itálie 2025'),
('trip_date_from',       '2025-07-15'),
('trip_date_to',         '2025-07-25'),
('admin_password',       '$2b$12$81bKXXZGg52ntRZGEOnjlegvoO82ygMgVQ3OlwqcemFgxf8eYPQ4S'),
('member_password',      '$2b$12$FeOTpAx02r7ewr.CpZoqr.iwBbmedWvBo5Ieh1lxJfII60rJ5.DMu'),
('exchange_rate',        '25.00'),
('exchange_rate_updated', '2025-07-01');

-- Itinerář
INSERT INTO itinerary (day_number, date, title, description, location_from, location_to, type, sort_order) VALUES
(0, '2025-07-14', 'Odjezd z ČR', 'Ráno odjezd auty, přes Rakousko do Itálie', 'Praha', 'Caorle', 'car', 0),
(1, '2025-07-15', 'Příjezd do mariny', 'Přebírka lodí, nákupy, noční pobyt v marině', 'Caorle Marina', 'Caorle Marina', 'port', 1),
(2, '2025-07-16', 'Vyplutí – Caorle → Poreč', 'První etapa plavby podél chorvatského pobřeží', 'Caorle', 'Poreč', 'sailing', 2),
(3, '2025-07-17', 'Poreč → Rovinj', 'Procházka Rovinjí, večeře na náměstí', 'Poreč', 'Rovinj', 'sailing', 3),
(4, '2025-07-18', 'Volný den Rovinj', 'Odpočinek, šnorchlování, prohlídka města', 'Rovinj', 'Rovinj', 'port', 4),
(5, '2025-07-19', 'Rovinj → Pula', 'Průjezd Fažanou, zastávka Brijuni', 'Rovinj', 'Pula', 'sailing', 5),
(10, '2025-07-25', 'Zpáteční cesta auty', 'Předání lodí, cesta domů', 'Caorle', 'Praha', 'car', 10);

-- Checklist
INSERT INTO checklist (category, item_name, description, sort_order) VALUES
('povinne', 'Pas nebo OP', 'Platný doklad totožnosti', 1),
('povinne', 'Lodní průkaz / patentka', 'Pokud jsi skipper', 2),
('povinne', 'Cestovní pojištění', 'Zahrnující vodní sporty', 3),
('obleceni', 'Plavky (2-3 ks)', NULL, 1),
('obleceni', 'Nepromokavá bunda', 'Na plavbu při větru', 2),
('obleceni', 'Kšiltovka / klobouk', NULL, 3),
('obleceni', 'Lodní boty', 'S bílou podrážkou!', 4),
('vybaveni', 'Sluneční brýle', 'Polarizované ideálně', 1),
('vybaveni', 'Opalovací krém SPF50+', NULL, 2),
('vybaveni', 'Síťová taška místo kufru', 'Snáze se ukládá na lodi', 3),
('doporucene', 'Seasick tablety', 'Preventivně před vyplutím', 1),
('doporucene', 'Snorkl a maska', NULL, 2),
('doporucene', 'Kamera / GoPro', NULL, 3);

-- Testovací výdaje pokladny
INSERT INTO wallet_expenses (paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by) VALUES
(1, 120.00, 'EUR', 120.00, 25.00, 'Nákup potravin v marině', 'ostatni', '2025-07-15 18:00:00', 'both', 1),
(4, 80.00,  'EUR',  80.00, 25.00, 'Diesel + voda', 'ostatni', '2025-07-16 09:00:00', 'both', 4),
(2, 2500.00, 'CZK', 100.00, 25.00, 'Leky z lekárny', 'ostatni', '2025-07-14 10:00:00', 'boat1', 2);

-- Splits pro výdaje (všichni pro oba výdaje EUR, jen loď 1 pro CZK výdaj)
-- Výdaj 1 (120 EUR): všichni 6 lidí = 20 EUR/os
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(1, 1, 20.00), (1, 2, 20.00), (1, 3, 20.00), (1, 4, 20.00), (1, 5, 20.00), (1, 6, 20.00);

-- Výdaj 2 (80 EUR): všichni 6 lidí = ~13.33 EUR/os
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(2, 1, 13.35), (2, 2, 13.33), (2, 3, 13.33), (2, 4, 13.33), (2, 5, 13.33), (2, 6, 13.33);

-- Výdaj 3 (100 EUR): loď 1 = 3 lidi = ~33.33 EUR/os
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(3, 1, 33.34), (3, 2, 33.33), (3, 3, 33.33);

-- Nákupní seznam – loď 1
INSERT INTO shopping_items (boat_id, category, item_name, quantity, assigned_to, price, currency, note, is_bought) VALUES
(1, 'potraviny', 'Těstoviny', '2 kg', 1, 2.50, 'EUR', NULL, 0),
(1, 'potraviny', 'Rajčata', '1 kg', NULL, NULL, 'EUR', 'čerstvá', 0),
(1, 'napoje', 'Voda (6L barel)', '4 ks', 2, 8.00, 'EUR', NULL, 1),
(1, 'alkohol', 'Prosecco', '3 lahve', 1, 15.00, 'EUR', NULL, 0),
(1, 'hygiena', 'Sunscreen SPF50', '2 ks', NULL, 12.00, 'EUR', NULL, 0);

-- Nákupní seznam – loď 2
INSERT INTO shopping_items (boat_id, category, item_name, quantity, assigned_to, price, currency, note, is_bought) VALUES
(2, 'potraviny', 'Rýže', '1 kg', 4, 1.80, 'EUR', NULL, 0),
(2, 'potraviny', 'Kuřecí maso', '2 kg', NULL, NULL, 'EUR', NULL, 0),
(2, 'napoje', 'Džusy', '4 ks', 5, 6.00, 'EUR', NULL, 0);

-- Deník
INSERT INTO logbook (boat_id, date, location_from, location_to, nautical_miles, departure_time, arrival_time, skipper_user_id, note, created_by) VALUES
(1, '2025-07-16', 'Caorle', 'Poreč', 42.5, '08:00:00', '14:30:00', 1, 'Krásný den, vítr NE 3–4 Bf, dojeli jsme za 6 hodin.', 1),
(1, '2025-07-17', 'Poreč', 'Rovinj', 18.2, '09:00:00', '12:00:00', 1, 'Klidné moře, šnorchlování v zátoce.', 2),
(2, '2025-07-16', 'Caorle', 'Poreč', 41.8, '08:15:00', '14:45:00', 4, 'Trochu vlnění, ale bez problémů.', 4),
(2, '2025-07-17', 'Poreč', 'Funtana', 8.5, '10:00:00', '12:00:00', 4, 'Kratší etapa, zastávka na oběd.', 5);
