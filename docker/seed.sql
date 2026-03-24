-- ============================================================
-- Sailing App – Kompletní seed data pro testování
-- Admin heslo: admin123 | Členské heslo: crew123
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- LODĚ
-- ============================================================
INSERT INTO boats (id, name, description) VALUES
(1, 'Stella', 'Bavaria Cruiser 46'),
(2, 'Modrá laguna', 'Jeanneau Sun Odyssey 440');

-- ============================================================
-- UŽIVATELÉ (10 členů – 5 na loď)
-- ============================================================
-- Loď 1 (Stella): id 1–5
-- Loď 2 (Modrá laguna): id 6–10
INSERT INTO users (id, name, phone, email, boat_id) VALUES
(1,  'Pavel Novák',        '+420 601 111 111', 'pavel@example.com',    1),
(2,  'Jana Horáková',      '+420 602 222 222', 'jana@example.com',     1),
(3,  'Tomáš Krejčí',       '+420 603 333 333', 'tomas@example.com',    1),
(4,  'Kateřina Dvořáková', '+420 604 444 444', NULL,                   1),
(5,  'Ondřej Fiala',       NULL,               'ondrej@example.com',   1),
(6,  'Lucie Marková',      '+420 606 666 666', 'lucie@example.com',    2),
(7,  'Martin Blaha',       '+420 607 777 777', NULL,                   2),
(8,  'Eva Procházková',    NULL,               'eva@example.com',      2),
(9,  'Jakub Černý',        '+420 609 999 999', 'jakub@example.com',    2),
(10, 'Tereza Veselá',      NULL,               NULL,                   2);

-- ============================================================
-- NASTAVENÍ APLIKACE
-- ============================================================
INSERT INTO settings (setting_key, setting_value) VALUES
('installed',             '1'),
('trip_name',             'vol.5 – Jadran 2025'),
('trip_date_from',        '2025-07-15'),
('trip_date_to',          '2025-07-25'),
('admin_password',        '$2b$12$81bKXXZGg52ntRZGEOnjlegvoO82ygMgVQ3OlwqcemFgxf8eYPQ4S'),
('member_password',       '$2b$12$FeOTpAx02r7ewr.CpZoqr.iwBbmedWvBo5Ieh1lxJfII60rJ5.DMu'),
('exchange_rate',         '25.21'),
('exchange_rate_updated', '2025-07-14');

-- ============================================================
-- ITINERÁŘ (12 dnů)
-- ============================================================
INSERT INTO itinerary (day_number, date, title, description, location_from, location_to, type, sort_order) VALUES
(0,  '2025-07-14', 'Odjezd z ČR',            'Ráno odjezd auty přes Rakousko do Itálie. Sraz 5:00 na Zličíně.',            'Praha',          'Caorle',         'car',     0),
(1,  '2025-07-15', 'Příjezd do mariny',       'Přebírka lodí, nákupy proviantu, seznámení s lodí. Večer grilování.',       'Caorle Marina',  'Caorle Marina',  'port',    1),
(2,  '2025-07-16', 'Caorle → Poreč',          'První etapa! Přejezd přes Jadran, cca 40 NM. Vítr by měl být NE 3–4 Bf.', 'Caorle',         'Poreč',          'sailing', 2),
(3,  '2025-07-17', 'Poreč → Rovinj',          'Krátká etapa podél istarského pobřeží. Odpoledne prohlídka Rovinje.',      'Poreč',          'Rovinj',         'sailing', 3),
(4,  '2025-07-18', 'Volný den – Rovinj',      'Šnorchlování u Červeného ostrova, procházka starým městem.',                'Rovinj',         'Rovinj',         'port',    4),
(5,  '2025-07-19', 'Rovinj → Pula',           'Plavba kolem mysu Kamenjak. Zastávka v zátoce na koupání.',                'Rovinj',         'Pula',           'sailing', 5),
(6,  '2025-07-20', 'Pula – prohlídka',        'Celý den v Pule – amfiteátr, Augustův chrám, rybí trh.',                   'Pula',           'Pula',           'port',    6),
(7,  '2025-07-21', 'Pula → Cres',             'Přejezd na ostrov Cres. Kotvení v zátoce Valun.',                          'Pula',           'Cres (Valun)',   'sailing', 7),
(8,  '2025-07-22', 'Cres → Mali Lošinj',      'Přejezd přes průliv. Mali Lošinj – město s palma promenádou.',            'Cres',           'Mali Lošinj',    'sailing', 8),
(9,  '2025-07-23', 'Volný den – Mali Lošinj', 'Relaxace, potápění, výlet na Veli Lošinj pěšky.',                          'Mali Lošinj',    'Mali Lošinj',    'port',    9),
(10, '2025-07-24', 'Mali Lošinj → Caorle',    'Zpáteční přejezd přes Jadran. Dlouhá etapa, start brzy ráno.',             'Mali Lošinj',    'Caorle',         'sailing', 10),
(11, '2025-07-25', 'Předání lodí a návrat',    'Úklid lodí, předání v marině. Odpoledne odjezd auty domů.',               'Caorle',         'Praha',          'car',     11);

-- ============================================================
-- CHECKLIST (18 položek, 4 kategorie)
-- ============================================================
INSERT INTO checklist (category, item_name, description, sort_order) VALUES
-- Povinné
('povinne',    'Pas nebo občanský průkaz',   'Platný doklad totožnosti',                   1),
('povinne',    'Lodní průkaz / VMP',         'Pokud jsi skipper – průkaz vůdce malého plavidla', 2),
('povinne',    'Cestovní pojištění',         'Zahrnující vodní sporty a repatriaci',        3),
('povinne',    'Kopie dokladů',              'Naskenované v telefonu nebo cloudu',           4),
-- Oblečení
('obleceni',   'Plavky (2–3 ks)',            NULL,                                          1),
('obleceni',   'Nepromokavá bunda',          'Větrovka na noční plavbu',                    2),
('obleceni',   'Kšiltovka / klobouk',        'Ochrana proti slunci',                        3),
('obleceni',   'Lodní boty',                 'S bílou nešpinící podrážkou!',                4),
('obleceni',   'Tričko s UV ochranou',       'Lepší než opalovací krém při plavbě',         5),
-- Vybavení
('vybaveni',   'Sluneční brýle',             'Polarizované – odstraní odlesky od vody',     1),
('vybaveni',   'Opalovací krém SPF 50+',     'Voděodolný, na lodi se spálíte rychle',       2),
('vybaveni',   'Síťová taška místo kufru',   'Snáze se vejde do kajuty',                    3),
('vybaveni',   'Čelovka',                    'Pro noční směny a příchod do přístavu',        4),
('vybaveni',   'Nůž / multitool',            NULL,                                          5),
-- Doporučené
('doporucene', 'Tablety na mořskou nemoc',   'Kinedryl / Dramamine – vzít preventivně',     1),
('doporucene', 'Šnorchl a maska',            'Pro zastávky v zátoce',                        2),
('doporucene', 'GoPro / vodotěsná kamera',   NULL,                                          3),
('doporucene', 'Hra na večer',               'Karty, Člověče, UNO...',                       4);

-- ============================================================
-- VÝDAJE POKLADNY (15 výdajů)
-- ============================================================
-- Uživatelé: 1–5 = loď 1, 6–10 = loď 2
-- Kurz CZK/EUR: 25.21

INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by) VALUES
-- Den 0 – přípravy
(1,  2,  2500.00, 'CZK',  99.17, 25.21, 'Léky a lékárnička',                    'ostatni',  '2025-07-13 14:00:00', 'both', 2),
(2,  1,  3200.00, 'CZK', 126.93, 25.21, 'Dálniční známky AT + HR',              'ostatni',  '2025-07-14 06:00:00', 'both', 1),
-- Den 1 – příjezd, nákupy
(3,  1,   180.00, 'EUR', 180.00, 25.21, 'Velký nákup potravin v marině',        'ostatni',  '2025-07-15 16:00:00', 'both', 1),
(4,  6,   120.00, 'EUR', 120.00, 25.21, 'Velký nákup potravin – loď 2',        'ostatni',  '2025-07-15 16:30:00', 'boat2', 6),
(5,  3,    45.00, 'EUR',  45.00, 25.21, 'Grilování – maso a zelenina',          'ostatni',  '2025-07-15 18:00:00', 'both', 3),
-- Den 2–3 – plavba
(6,  4,    85.00, 'EUR',  85.00, 25.21, 'Diesel – Stella',                      'ostatni',  '2025-07-16 08:00:00', 'boat1', 4),
(7,  9,    78.00, 'EUR',  78.00, 25.21, 'Diesel – Modrá laguna',               'ostatni',  '2025-07-16 08:30:00', 'boat2', 9),
(8,  7,    65.00, 'EUR',  65.00, 25.21, 'Přístav Poreč – poplatek obě lodě',   'ostatni',  '2025-07-16 15:00:00', 'both', 7),
(9,  1,    42.00, 'EUR',  42.00, 25.21, 'Večeře v Poreči – pizza pro všechny', 'ostatni',  '2025-07-16 20:00:00', 'both', 1),
(10, 6,    55.00, 'EUR',  55.00, 25.21, 'Přístav Rovinj',                       'ostatni',  '2025-07-17 14:00:00', 'both', 6),
-- Den 4 – volný den
(11, 8,  1200.00, 'CZK',  47.60, 25.21, 'Zmrzlina a kafíčka pro všechny',      'ostatni',  '2025-07-18 11:00:00', 'both', 8),
(12, 5,    38.00, 'EUR',  38.00, 25.21, 'Šnorchl výbava – půjčovna',           'ostatni',  '2025-07-18 14:00:00', 'boat1', 5),
-- Den 5–6
(13, 9,    92.00, 'EUR',  92.00, 25.21, 'Diesel + voda – Modrá laguna',        'ostatni',  '2025-07-19 07:00:00', 'boat2', 9),
(14, 3,    75.00, 'EUR',  75.00, 25.21, 'Diesel + voda – Stella',              'ostatni',  '2025-07-19 07:30:00', 'boat1', 3),
(15, 7,   110.00, 'EUR', 110.00, 25.21, 'Večeře v Pule – rybí restaurant',     'ostatni',  '2025-07-20 20:00:00', 'both', 7);

-- ============================================================
-- SPLITS (přesné rozložení na osoby)
-- ============================================================

-- Výdaj 1: 99.17 EUR / 10 lidí = 9.917 → 9.91 × 3 + 9.92 × 7 = 29.73 + 69.44 = 99.17
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(1, 1, 9.92), (1, 2, 9.92), (1, 3, 9.92), (1, 4, 9.92), (1, 5, 9.92),
(1, 6, 9.91), (1, 7, 9.91), (1, 8, 9.91), (1, 9, 9.92), (1, 10, 9.92);

-- Výdaj 2: 126.93 EUR / 10 lidí = 12.693 → 12.69 × 7 + 12.70 × 3 = 88.83 + 38.10 = 126.93
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(2, 1, 12.70), (2, 2, 12.70), (2, 3, 12.70), (2, 4, 12.69), (2, 5, 12.69),
(2, 6, 12.69), (2, 7, 12.69), (2, 8, 12.69), (2, 9, 12.69), (2, 10, 12.69);

-- Výdaj 3: 180.00 EUR / 10 = 18.00
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(3, 1, 18.00), (3, 2, 18.00), (3, 3, 18.00), (3, 4, 18.00), (3, 5, 18.00),
(3, 6, 18.00), (3, 7, 18.00), (3, 8, 18.00), (3, 9, 18.00), (3, 10, 18.00);

-- Výdaj 4: 120.00 EUR / 5 (boat2: 6–10) = 24.00
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(4, 6, 24.00), (4, 7, 24.00), (4, 8, 24.00), (4, 9, 24.00), (4, 10, 24.00);

-- Výdaj 5: 45.00 EUR / 10 = 4.50
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(5, 1, 4.50), (5, 2, 4.50), (5, 3, 4.50), (5, 4, 4.50), (5, 5, 4.50),
(5, 6, 4.50), (5, 7, 4.50), (5, 8, 4.50), (5, 9, 4.50), (5, 10, 4.50);

-- Výdaj 6: 85.00 EUR / 5 (boat1: 1–5) = 17.00
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(6, 1, 17.00), (6, 2, 17.00), (6, 3, 17.00), (6, 4, 17.00), (6, 5, 17.00);

-- Výdaj 7: 78.00 EUR / 5 (boat2: 6–10) = 15.60
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(7, 6, 15.60), (7, 7, 15.60), (7, 8, 15.60), (7, 9, 15.60), (7, 10, 15.60);

-- Výdaj 8: 65.00 EUR / 10 = 6.50
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(8, 1, 6.50), (8, 2, 6.50), (8, 3, 6.50), (8, 4, 6.50), (8, 5, 6.50),
(8, 6, 6.50), (8, 7, 6.50), (8, 8, 6.50), (8, 9, 6.50), (8, 10, 6.50);

-- Výdaj 9: 42.00 EUR / 10 = 4.20
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(9, 1, 4.20), (9, 2, 4.20), (9, 3, 4.20), (9, 4, 4.20), (9, 5, 4.20),
(9, 6, 4.20), (9, 7, 4.20), (9, 8, 4.20), (9, 9, 4.20), (9, 10, 4.20);

-- Výdaj 10: 55.00 EUR / 10 = 5.50
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(10, 1, 5.50), (10, 2, 5.50), (10, 3, 5.50), (10, 4, 5.50), (10, 5, 5.50),
(10, 6, 5.50), (10, 7, 5.50), (10, 8, 5.50), (10, 9, 5.50), (10, 10, 5.50);

-- Výdaj 11: 47.60 EUR / 10 = 4.76
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(11, 1, 4.76), (11, 2, 4.76), (11, 3, 4.76), (11, 4, 4.76), (11, 5, 4.76),
(11, 6, 4.76), (11, 7, 4.76), (11, 8, 4.76), (11, 9, 4.76), (11, 10, 4.76);

-- Výdaj 12: 38.00 EUR / 5 (boat1: 1–5) = 7.60
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(12, 1, 7.60), (12, 2, 7.60), (12, 3, 7.60), (12, 4, 7.60), (12, 5, 7.60);

-- Výdaj 13: 92.00 EUR / 5 (boat2: 6–10) = 18.40
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(13, 6, 18.40), (13, 7, 18.40), (13, 8, 18.40), (13, 9, 18.40), (13, 10, 18.40);

-- Výdaj 14: 75.00 EUR / 5 (boat1: 1–5) = 15.00
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(14, 1, 15.00), (14, 2, 15.00), (14, 3, 15.00), (14, 4, 15.00), (14, 5, 15.00);

-- Výdaj 15: 110.00 EUR / 10 = 11.00
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(15, 1, 11.00), (15, 2, 11.00), (15, 3, 11.00), (15, 4, 11.00), (15, 5, 11.00),
(15, 6, 11.00), (15, 7, 11.00), (15, 8, 11.00), (15, 9, 11.00), (15, 10, 11.00);

-- ============================================================
-- AUDIT LOG (záznamy o vytvoření výdajů + 1 editace)
-- ============================================================
INSERT INTO wallet_audit_log (expense_id, changed_by, change_type, old_values, new_values, changed_at) VALUES
(1,  2, 'created', NULL, '{"amount":"2500.00","currency":"CZK","description":"Léky a lékárnička"}', '2025-07-13 14:00:00'),
(2,  1, 'created', NULL, '{"amount":"3200.00","currency":"CZK","description":"Dálniční známky AT + HR"}', '2025-07-14 06:00:00'),
(3,  1, 'created', NULL, '{"amount":"180.00","currency":"EUR","description":"Velký nákup potravin v marině"}', '2025-07-15 16:00:00'),
(4,  6, 'created', NULL, '{"amount":"120.00","currency":"EUR","description":"Velký nákup potravin – loď 2"}', '2025-07-15 16:30:00'),
(5,  3, 'created', NULL, '{"amount":"45.00","currency":"EUR","description":"Grilování – maso a zelenina"}', '2025-07-15 18:00:00'),
(9,  1, 'created', NULL, '{"amount":"42.00","currency":"EUR","description":"Večeře v Poreči"}', '2025-07-16 20:00:00'),
(9,  1, 'edited',  '{"description":"Večeře v Poreči"}', '{"description":"Večeře v Poreči – pizza pro všechny"}', '2025-07-16 20:15:00'),
(15, 7, 'created', NULL, '{"amount":"110.00","currency":"EUR","description":"Večeře v Pule – rybí restaurant"}', '2025-07-20 20:00:00');

-- ============================================================
-- VYROVNÁNÍ (1 označené)
-- ============================================================
INSERT INTO wallet_settled (from_user_id, to_user_id, settled_at, settled_by) VALUES
(10, 1, '2025-07-18 12:00:00', 10);

-- ============================================================
-- NÁKUPNÍ SEZNAM – LOĎ 1 (12 položek)
-- ============================================================
INSERT INTO shopping_items (boat_id, category, item_name, quantity, assigned_to, price, currency, note, is_bought, bought_by, created_by) VALUES
-- Potraviny
(1, 'potraviny', 'Těstoviny penne',        '2 kg',     1,    2.50,  'EUR', NULL,                              0, NULL, 1),
(1, 'potraviny', 'Rajčata čerstvá',         '1 kg',     NULL, NULL,  'EUR', 'Na salát a omáčku',               0, NULL, 2),
(1, 'potraviny', 'Olivový olej',            '1 litr',   4,    7.90,  'EUR', 'Extra virgin',                    1, 4,    1),
(1, 'potraviny', 'Sýr parmazán',            '300 g',    NULL, 5.50,  'EUR', NULL,                              1, 1,    3),
(1, 'potraviny', 'Chleba / bagety',         '4 ks',     2,    NULL,  'EUR', 'Koupit ráno čerstvé',             0, NULL, 2),
-- Nápoje
(1, 'napoje',    'Voda balená (6L)',         '4 barely', 2,    8.00,  'EUR', NULL,                              1, 2,    1),
(1, 'napoje',    'Pomerančový džus',         '2 litry',  NULL, 3.20,  'EUR', NULL,                              0, NULL, 5),
-- Alkohol
(1, 'alkohol',   'Prosecco',                '4 lahve',  1,    20.00, 'EUR', 'Na oslavu vyplutí',               0, NULL, 1),
(1, 'alkohol',   'Bílé víno místní',        '3 lahve',  NULL, NULL,  'EUR', 'Koupit v Chorvatsku',             0, NULL, 3),
-- Hygiena
(1, 'hygiena',   'Opalovací krém SPF 50',   '2 ks',     NULL, 12.00, 'EUR', NULL,                              0, NULL, 4),
-- Lékárnička
(1, 'lekarna',   'Ibuprofen',               '1 balení', 2,    NULL,  'CZK', NULL,                              1, 2,    2),
-- Ostatní
(1, 'ostatni',   'Lodní lano 10mm',          '20 m',     5,    180.00,'CZK', 'Jako záloha ke stávajícímu',      0, NULL, 5);

-- ============================================================
-- NÁKUPNÍ SEZNAM – LOĎ 2 (10 položek)
-- ============================================================
INSERT INTO shopping_items (boat_id, category, item_name, quantity, assigned_to, price, currency, note, is_bought, bought_by, created_by) VALUES
(2, 'potraviny', 'Rýže basmati',            '1 kg',     6,    1.80,  'EUR', NULL,                              0, NULL, 6),
(2, 'potraviny', 'Kuřecí prsa',             '2 kg',     NULL, NULL,  'EUR', 'Marinovat den předem',            0, NULL, 7),
(2, 'potraviny', 'Zelenina na gril',        '1 kg',     8,    4.50,  'EUR', 'Cukety, papriky, cibule',         1, 8,    6),
(2, 'potraviny', 'Vejce',                   '20 ks',    NULL, 3.00,  'EUR', NULL,                              0, NULL, 9),
(2, 'napoje',    'Pivo (plech)',             '24 ks',    7,    18.00, 'EUR', 'Mix světlé a tmavé',              0, NULL, 7),
(2, 'napoje',    'Minerálka',               '6 lahví',  9,    4.80,  'EUR', NULL,                              1, 9,    6),
(2, 'alkohol',   'Rum',                     '1 lahev',  NULL, NULL,  'EUR', 'Na Mojito',                       0, NULL, 10),
(2, 'alkohol',   'Limetky',                 '10 ks',    10,   2.50,  'EUR', 'K rumu',                          0, NULL, 10),
(2, 'hygiena',   'Repelent proti komárům',  '1 ks',     NULL, 89.00, 'CZK', NULL,                              0, NULL, 8),
(2, 'ostatni',   'Pytle na odpad',           '1 role',   6,    1.20,  'EUR', NULL,                              1, 6,    6);

-- ============================================================
-- DENÍK PLAVBY – LOĎ 1 (Stella)
-- ============================================================
INSERT INTO logbook (boat_id, date, location_from, location_to, nautical_miles, departure_time, arrival_time, skipper_user_id, note, created_by) VALUES
(1, '2025-07-16', 'Caorle',     'Poreč',       42.5, '08:00:00', '14:30:00', 1, 'Výborný vítr NE 3–4 Bf, celou cestu pod plachtami. Moře hladké.',                1),
(1, '2025-07-17', 'Poreč',      'Rovinj',      18.2, '09:30:00', '12:00:00', 1, 'Klidné moře, šnorchlování v zátoce pod Červeným ostrovem.',                     2),
(1, '2025-07-19', 'Rovinj',     'Pula',        24.8, '07:00:00', '12:30:00', 3, 'Obeplutí mysu Kamenjak. Krásné útesy, delfíni!',                                 3),
(1, '2025-07-21', 'Pula',       'Cres (Valun)', 35.2, '06:30:00', '14:00:00', 1, 'Delší etapa přes otevřené moře. Vítr zesílil na 5 Bf odpoledne.',                1),
(1, '2025-07-22', 'Cres',       'Mali Lošinj', 15.6, '09:00:00', '11:30:00', 5, 'Průjezd úzkým průlivem – krásný zážitek. Ondřej poprvé u kormidla.',            5),
(1, '2025-07-24', 'Mali Lošinj','Caorle',      44.8, '05:00:00', '15:00:00', 1, 'Nejdelší etapa. Start za tmy, západ slunce na moři. Příjezd unaveně ale šťastně.', 1);

-- ============================================================
-- DENÍK PLAVBY – LOĎ 2 (Modrá laguna)
-- ============================================================
INSERT INTO logbook (boat_id, date, location_from, location_to, nautical_miles, departure_time, arrival_time, skipper_user_id, note, created_by) VALUES
(2, '2025-07-16', 'Caorle',     'Poreč',        41.8, '08:15:00', '14:45:00', 6, 'Trochu vlnění na začátku, pak se uklidnilo. Dobrá plavba.',                     6),
(2, '2025-07-17', 'Poreč',      'Rovinj',       17.5, '10:00:00', '12:30:00', 6, 'Kratší etapa, stavili jsme v zátoce na koupání.',                                7),
(2, '2025-07-19', 'Rovinj',     'Pula',         25.1, '07:30:00', '13:00:00', 9, 'Jakub za kormidlem, zvládl to skvěle. Zastávka Fažana na oběd.',                9),
(2, '2025-07-21', 'Pula',       'Cres (Valun)', 34.8, '06:45:00', '14:15:00', 6, 'Náročný den – vlny 1.5m, ale loď se chovala výborně.',                          6),
(2, '2025-07-22', 'Cres',       'Mali Lošinj',  16.2, '09:15:00', '12:00:00', 9, 'Klidný den, šnorchlování cestou.',                                               8),
(2, '2025-07-24', 'Mali Lošinj','Caorle',       45.1, '04:45:00', '15:30:00', 6, 'Noční start – krásné hvězdy. Nejdelší etapa, ale všichni v pohodě.',             6);

-- ============================================================
-- JÍDELNÍČEK (obědy pro obě lodě, ne všechny dny – pro test empty state)
-- ============================================================
INSERT INTO menu_plan (boat_id, date, meal_type, cook_user_id, meal_description, note, created_by) VALUES
-- Loď 1
(1, '2025-07-15', 'obed', 1,    'Grilované klobásy s bramborem',     'První den – jednoduché',     1),
(1, '2025-07-16', 'obed', 2,    'Těstovinový salát s tuňákem',       'Studené jídlo na moři',       2),
(1, '2025-07-17', 'obed', 3,    'Spaghetti aglio olio',              'Italská klasika',             3),
(1, '2025-07-18', 'obed', 4,    'Řecký salát s fetou',               'Volný den – lehký oběd',      4),
(1, '2025-07-19', 'obed', 5,    'Rizoto s mořskými plody',           'Ondra se předvedl!',          5),
(1, '2025-07-21', 'obed', 1,    'Guláš z konzervy + rohlíky',        'Na moři nic složitého',       1),
(1, '2025-07-22', 'obed', 2,    'Caprese + bageta',                  NULL,                          2),
-- dny 20 a 23 záměrně prázdné
-- Loď 2
(2, '2025-07-15', 'obed', 6,    'Kuřecí steak s rýží',              'Přivezeno z ČR',              6),
(2, '2025-07-16', 'obed', 7,    'Wrap s kuřetem a zeleninou',        NULL,                          7),
(2, '2025-07-17', 'obed', 8,    'Penne all\'arrabbiata',             'Eva to dělá skvěle',          8),
(2, '2025-07-18', 'obed', 9,    'Grilovaná zelenina + halloumi',     'Vegetariánský den',           9),
(2, '2025-07-19', 'obed', 10,   'Rybí tacos',                        'Z čerstvě koupeného úlovku', 10),
(2, '2025-07-21', 'obed', 6,    'Jednohrnec – čočková polévka',      NULL,                          6),
(2, '2025-07-22', 'obed', 7,    'Bruschetta s rajčaty',              'Rychlé a dobré',              7);

-- ============================================================
-- AUTA (3 auta, různá obsazenost)
-- ============================================================
INSERT INTO cars (id, driver_user_id, car_name, seats, note) VALUES
(1, 1, 'Škoda Octavia Combi', 5, 'Střešní box na výbavu'),
(2, 6, 'VW Golf',             5, NULL),
(3, 9, 'Ford Transit Custom', 8, 'Dodávka – místo na SUP a potápěčskou výbavu');

-- Spolujezdci
-- Auto 1 (Pavel, 5 míst): Jana, Tomáš, Kateřina = 4/5 obsazeno
INSERT INTO car_passengers (car_id, user_id) VALUES
(1, 2), (1, 3), (1, 4);

-- Auto 2 (Lucie, 5 míst): Martin, Eva = 3/5 obsazeno
INSERT INTO car_passengers (car_id, user_id) VALUES
(2, 7), (2, 8);

-- Auto 3 (Jakub, 8 míst): Tereza = 2/8 obsazeno
INSERT INTO car_passengers (car_id, user_id) VALUES
(3, 10);

-- Nepřiřazení: Ondřej (5) – záměrně bez auta pro test
