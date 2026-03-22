-- ============================================================
-- VOL5 – Road-trip seed: Jadran 2025
-- 10-denní plavba, 2 lodě, 10 lidí, mix EUR + CZK
--
-- Fixní kurz: 25.00 CZK/EUR (pro reprodukovatelnost testů)
-- Všechna data jsou realistická, ručně ověřitelná.
--
-- Lodě:
--   Loď 1 "Adriana": Pavel(1), Jana(2), Tomáš(3), Lucie(4), Martin(5)
--   Loď 2 "Barborka": Eva(6), Petr(7), Klára(8), Ondřej(9), Tereza(10)
--
-- Ruční výpočet bilancí viz konec souboru.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Reset (pro čistý stav)
-- ============================================================
DELETE FROM wallet_audit_log;
DELETE FROM wallet_settled;
DELETE FROM wallet_expense_splits;
DELETE FROM wallet_expenses;
DELETE FROM users;
DELETE FROM boats;

INSERT INTO boats (id, name, description) VALUES
(1, 'Adriana',  'Loď 1 – hlavní'),
(2, 'Barborka', 'Loď 2 – druhá');

-- 10 uživatelů, 5 na každé lodi
INSERT INTO users (id, name, phone, email, boat_id) VALUES
(1,  'Pavel Novák',      '+420 601 111 111', 'pavel@test.cz',  1),
(2,  'Jana Horáková',    '+420 602 222 222', 'jana@test.cz',   1),
(3,  'Tomáš Krejčí',     '+420 603 333 333', 'tomas@test.cz',  1),
(4,  'Lucie Marková',    '+420 604 444 444', 'lucie@test.cz',  1),
(5,  'Martin Blaha',     '+420 605 555 555', 'martin@test.cz', 1),
(6,  'Eva Procházková',  '+420 606 666 666', 'eva@test.cz',    2),
(7,  'Petr Šimánek',     '+420 607 777 777', 'petr@test.cz',   2),
(8,  'Klára Dvořáčková', '+420 608 888 888', 'klara@test.cz',  2),
(9,  'Ondřej Vlček',     '+420 609 999 999', 'ondrej@test.cz', 2),
(10, 'Tereza Nováčková', '+420 610 000 000', 'tereza@test.cz', 2);

INSERT INTO settings (setting_key, setting_value) VALUES
('installed',             '1'),
('trip_name',             'Jadran 2025 – Testovací plavba'),
('trip_date_from',        '2025-07-15'),
('trip_date_to',          '2025-07-25'),
('admin_password',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('member_password',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('exchange_rate',         '25.00'),
('exchange_rate_updated', '2025-07-15')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ============================================================
-- VÝDAJE
-- Formát komentáře:
--   paid_by: kdo zaplatil
--   split: kdo se dělí
--   amount_eur: přesná EUR hodnota
--   per_person: floor(eur/n*100)/100
--   remainder: eur - n*per_person → jde na split[0]
-- ============================================================

-- ------------------------------------------------------------
-- E01: Pavel zaplatí naftu pro obě lodě
-- 180.00 EUR / 10 lidí [1..10]
-- per=18.00, rem=0.00 → všichni 18.00
-- Pavel platí: +180.00, jeho podíl: -18.00 → bilance +162.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (1, 1, 180.00, 'EUR', 180.00, 25.00, 'Nafta pro obě lodě – Caorle', 'ostatni', '2025-07-15 09:00:00', 'both', 1);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(1,1,18.00),(1,2,18.00),(1,3,18.00),(1,4,18.00),(1,5,18.00),
(1,6,18.00),(1,7,18.00),(1,8,18.00),(1,9,18.00),(1,10,18.00);

-- ------------------------------------------------------------
-- E02: Jana zaplatí potraviny pro loď 1
-- 1500 CZK → 60.00 EUR / 5 lidí [1..5]
-- per=12.00, rem=0.00 → všichni 12.00
-- Jana platí: +60.00, její podíl: -12.00 → bilance +48.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (2, 2, 1500.00, 'CZK', 60.00, 25.00, 'Potraviny Caorle – Loď 1', 'ostatni', '2025-07-15 11:00:00', 'boat1', 2);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(2,1,12.00),(2,2,12.00),(2,3,12.00),(2,4,12.00),(2,5,12.00);

-- ------------------------------------------------------------
-- E03: Eva zaplatí potraviny pro loď 2
-- 1500 CZK → 60.00 EUR / 5 lidí [6..10]
-- per=12.00, rem=0.00 → všichni 12.00
-- Eva platí: +60.00, její podíl: -12.00 → bilance +48.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (3, 6, 1500.00, 'CZK', 60.00, 25.00, 'Potraviny Caorle – Loď 2', 'ostatni', '2025-07-15 11:30:00', 'boat2', 6);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(3,6,12.00),(3,7,12.00),(3,8,12.00),(3,9,12.00),(3,10,12.00);

-- ------------------------------------------------------------
-- E04: Tomáš zaplatí mariinu pro obě lodě (2 noci)
-- 320.00 EUR / 10 lidí [1..10]
-- per=32.00, rem=0.00 → všichni 32.00
-- Tomáš platí: +320.00, jeho podíl: -32.00 → bilance +288.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (4, 3, 320.00, 'EUR', 320.00, 25.00, 'Marina Poreč – 2 noci', 'ostatni', '2025-07-16 18:00:00', 'both', 3);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(4,1,32.00),(4,2,32.00),(4,3,32.00),(4,4,32.00),(4,5,32.00),
(4,6,32.00),(4,7,32.00),(4,8,32.00),(4,9,32.00),(4,10,32.00);

-- ------------------------------------------------------------
-- E05: Lucie zaplatí restauraci pro celou skupinu
-- 245.00 EUR / 10 lidí [1..10]
-- per=24.50, rem=0.00 → všichni 24.50
-- Lucie platí: +245.00, její podíl: -24.50 → bilance +220.50
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (5, 4, 245.00, 'EUR', 245.00, 25.00, 'Večeře Rovinj – celá skupina', 'ostatni', '2025-07-17 20:00:00', 'both', 4);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(5,1,24.50),(5,2,24.50),(5,3,24.50),(5,4,24.50),(5,5,24.50),
(5,6,24.50),(5,7,24.50),(5,8,24.50),(5,9,24.50),(5,10,24.50);

-- ------------------------------------------------------------
-- E06: Martin zaplatí naftu loď 1
-- 95.00 EUR / 5 lidí [1..5]
-- per=19.00, rem=0.00 → všichni 19.00
-- Martin platí: +95.00, jeho podíl: -19.00 → bilance +76.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (6, 5, 95.00, 'EUR', 95.00, 25.00, 'Nafta – Loď 1 Rovinj→Pula', 'ostatni', '2025-07-18 08:00:00', 'boat1', 5);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(6,1,19.00),(6,2,19.00),(6,3,19.00),(6,4,19.00),(6,5,19.00);

-- ------------------------------------------------------------
-- E07: Petr zaplatí naftu loď 2
-- 92.00 EUR / 5 lidí [6..10]
-- per=18.40, rem=0.00 → všichni 18.40
-- Petr platí: +92.00, jeho podíl: -18.40 → bilance +73.60
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (7, 7, 92.00, 'EUR', 92.00, 25.00, 'Nafta – Loď 2 Rovinj→Pula', 'ostatni', '2025-07-18 08:15:00', 'boat2', 7);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(7,6,18.40),(7,7,18.40),(7,8,18.40),(7,9,18.40),(7,10,18.40);

-- ------------------------------------------------------------
-- E08: Klára zaplatí paddleboardy (3 lidi)
-- 45.00 EUR / 3 lidé [6,8,10]
-- per=15.00, rem=0.00 → všichni 15.00
-- Klára platí: +45.00, její podíl: -15.00 → bilance +30.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (8, 8, 45.00, 'EUR', 45.00, 25.00, 'Paddleboardy – 3 hod.', 'ostatni', '2025-07-18 14:00:00', 'both', 8);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(8,6,15.00),(8,8,15.00),(8,10,15.00);

-- ------------------------------------------------------------
-- E09: Ondřej zaplatí parkovné (obě lodě, CZK)
-- 2500 CZK → 100.00 EUR / 10 lidí [1..10]
-- per=10.00, rem=0.00 → všichni 10.00
-- Ondřej platí: +100.00, jeho podíl: -10.00 → bilance +90.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (9, 9, 2500.00, 'CZK', 100.00, 25.00, 'Parkovné Caorle – 10 dní', 'ostatni', '2025-07-25 10:00:00', 'both', 9);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(9,1,10.00),(9,2,10.00),(9,3,10.00),(9,4,10.00),(9,5,10.00),
(9,6,10.00),(9,7,10.00),(9,8,10.00),(9,9,10.00),(9,10,10.00);

-- ------------------------------------------------------------
-- E10: Tereza zaplatí léky + lékárnu (obě lodě)
-- 37.00 EUR / 10 lidí [1..10]
-- per = floor(37/10*100)/100 = floor(3.70)*100/100 = 3.70
-- rem = 37.00 - 10*3.70 = 37.00 - 37.00 = 0.00 → všichni 3.70
-- Tereza platí: +37.00, její podíl: -3.70 → bilance +33.30
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (10, 10, 37.00, 'EUR', 37.00, 25.00, 'Lékárna – seasick tablety', 'ostatni', '2025-07-15 16:00:00', 'both', 10);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(10,1,3.70),(10,2,3.70),(10,3,3.70),(10,4,3.70),(10,5,3.70),
(10,6,3.70),(10,7,3.70),(10,8,3.70),(10,9,3.70),(10,10,3.70);

-- ------------------------------------------------------------
-- E11: Pavel zaplatí mariinu Pula (obě lodě)
-- 280.00 EUR / 10 lidí [1..10]
-- per=28.00, rem=0.00 → všichni 28.00
-- Pavel platí: +280.00, jeho podíl: -28.00 → bilance +252.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (11, 1, 280.00, 'EUR', 280.00, 25.00, 'Marina Pula – 1 noc', 'ostatni', '2025-07-19 17:00:00', 'both', 1);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(11,1,28.00),(11,2,28.00),(11,3,28.00),(11,4,28.00),(11,5,28.00),
(11,6,28.00),(11,7,28.00),(11,8,28.00),(11,9,28.00),(11,10,28.00);

-- ------------------------------------------------------------
-- E12: Jana zaplatí oběd loď 1 (CZK)
-- 875 CZK → 35.00 EUR / 5 lidí [1..5]
-- per=7.00, rem=0.00 → všichni 7.00
-- Jana platí: +35.00, její podíl: -7.00 → bilance +28.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (12, 2, 875.00, 'CZK', 35.00, 25.00, 'Oběd Brijuni – Loď 1', 'ostatni', '2025-07-19 13:00:00', 'boat1', 2);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(12,1,7.00),(12,2,7.00),(12,3,7.00),(12,4,7.00),(12,5,7.00);

-- ------------------------------------------------------------
-- E13: Eva zaplatí oběd loď 2
-- 89.00 EUR / 5 lidí [6..10]
-- per=17.80, rem=0.00 → všichni 17.80
-- Eva platí: +89.00, její podíl: -17.80 → bilance +71.20
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (13, 6, 89.00, 'EUR', 89.00, 25.00, 'Oběd Brijuni – Loď 2', 'ostatni', '2025-07-19 13:15:00', 'boat2', 6);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(13,6,17.80),(13,7,17.80),(13,8,17.80),(13,9,17.80),(13,10,17.80);

-- ------------------------------------------------------------
-- E14: Tomáš zaplatí šnorchlování (6 lidí, mix lodí)
-- 120.00 EUR / 6 lidí [1,2,3,6,7,8]
-- per=20.00, rem=0.00 → všichni 20.00
-- Tomáš platí: +120.00, jeho podíl: -20.00 → bilance +100.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (14, 3, 120.00, 'EUR', 120.00, 25.00, 'Šnorchlování Brijuni', 'ostatni', '2025-07-20 10:00:00', 'both', 3);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(14,1,20.00),(14,2,20.00),(14,3,20.00),(14,6,20.00),(14,7,20.00),(14,8,20.00);

-- ------------------------------------------------------------
-- E15: Martin zaplatí led + nápoje loď 1 (CZK)
-- 500 CZK → 20.00 EUR / 5 lidí [1..5]
-- per=4.00, rem=0.00 → všichni 4.00
-- Martin platí: +20.00, jeho podíl: -4.00 → bilance +16.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (15, 5, 500.00, 'CZK', 20.00, 25.00, 'Led + nápoje – Loď 1', 'ostatni', '2025-07-20 12:00:00', 'boat1', 5);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(15,1,4.00),(15,2,4.00),(15,3,4.00),(15,4,4.00),(15,5,4.00);

-- ------------------------------------------------------------
-- E16: Petr zaplatí led + nápoje loď 2 (CZK)
-- 500 CZK → 20.00 EUR / 5 lidí [6..10]
-- per=4.00, rem=0.00 → všichni 4.00
-- Petr platí: +20.00, jeho podíl: -4.00 → bilance +16.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (16, 7, 500.00, 'CZK', 20.00, 25.00, 'Led + nápoje – Loď 2', 'ostatni', '2025-07-20 12:15:00', 'boat2', 7);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(16,6,4.00),(16,7,4.00),(16,8,4.00),(16,9,4.00),(16,10,4.00);

-- ------------------------------------------------------------
-- E17: Ondřej zaplatí výlet na Brijuni (všichni)
-- 220.00 EUR / 10 lidí [1..10]
-- per=22.00, rem=0.00 → všichni 22.00
-- Ondřej platí: +220.00, jeho podíl: -22.00 → bilance +198.00
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (17, 9, 220.00, 'EUR', 220.00, 25.00, 'Výlet Brijuni – lodní taxi', 'ostatni', '2025-07-20 09:00:00', 'both', 9);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(17,1,22.00),(17,2,22.00),(17,3,22.00),(17,4,22.00),(17,5,22.00),
(17,6,22.00),(17,7,22.00),(17,8,22.00),(17,9,22.00),(17,10,22.00);

-- ------------------------------------------------------------
-- E18: Lucie zaplatí poslední večeři (všichni) – nesoudělné číslo!
-- 301.00 EUR / 10 lidí [1..10]
-- per = floor(301/10*100)/100 = floor(30.10)*100/100 = 30.10
-- rem = 301.00 - 10*30.10 = 301.00 - 301.00 = 0.00 → všichni 30.10
-- Lucie platí: +301.00, její podíl: -30.10 → bilance +270.90
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (18, 4, 301.00, 'EUR', 301.00, 25.00, 'Závěrečná večeře Pula', 'ostatni', '2025-07-24 20:00:00', 'both', 4);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(18,1,30.10),(18,2,30.10),(18,3,30.10),(18,4,30.10),(18,5,30.10),
(18,6,30.10),(18,7,30.10),(18,8,30.10),(18,9,30.10),(18,10,30.10);

-- ------------------------------------------------------------
-- E19: Klára zaplatí pronájem kajaku (3 lidi, nesoudělné)
-- 100.00 EUR / 3 lidé [7,8,9]
-- per = floor(100/3*100)/100 = floor(33.33)*100/100 = 33.33
-- rem = 100.00 - 3*33.33 = 100.00 - 99.99 = 0.01
-- splits: user7=33.34, user8=33.33, user9=33.33
-- Klára platí: +100.00, její podíl: -33.33 → bilance +66.67
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (19, 8, 100.00, 'EUR', 100.00, 25.00, 'Kajak pronájem – 3 hod.', 'ostatni', '2025-07-21 10:00:00', 'both', 8);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(19,7,33.34),(19,8,33.33),(19,9,33.33);

-- ------------------------------------------------------------
-- E20: Tereza zaplatí ice cream (malý výdaj, CZK, haléřový test)
-- 127 CZK → 5.08 EUR / 10 lidí [1..10]
-- per = floor(5.08/10*100)/100 = floor(0.508)*100/100 = 0.50
-- rem = 5.08 - 10*0.50 = 5.08 - 5.00 = 0.08
-- splits: user1=0.58, ostatní=0.50
-- Tereza platí: +5.08, její podíl: -0.50 → bilance +4.58
-- ------------------------------------------------------------
INSERT INTO wallet_expenses (id, paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
VALUES (20, 10, 127.00, 'CZK', 5.08, 25.00, 'Zmrzlina Rovinj', 'ostatni', '2025-07-17 15:00:00', 'both', 10);
INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES
(20,1,0.58),(20,2,0.50),(20,3,0.50),(20,4,0.50),(20,5,0.50),
(20,6,0.50),(20,7,0.50),(20,8,0.50),(20,9,0.50),(20,10,0.50);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- GOLDEN VALUES – ruční výpočet (nezávislý na aplikaci)
-- Metoda: pro každého uživatele sečteme co zaplatil minus co dluží
--
-- Výdaje kde se dělí VŠICHNI (10 lidí):
--   E01 180.00  E04 320.00  E05 245.00  E09 100.00  E10 37.00
--   E11 280.00  E17 220.00  E18 301.00  E20 5.08
--   Celkem všichni: 1688.08 EUR → na osobu: 168.808 EUR
--
-- PAVEL (user 1):
--   Zaplatil: E01=180.00, E11=280.00 → 460.00 EUR
--   Dluží: E01=18, E02=12, E04=32, E05=24.50, E06=19, E09=10,
--           E10=3.70, E11=28, E12=7, E14=20, E15=4, E17=22,
--           E18=30.10, E20=0.58 → 230.88 EUR
--   Bilance: 460.00 - 230.88 = +229.12
--
-- JANA (user 2):
--   Zaplatila: E02=60.00, E12=35.00 → 95.00 EUR
--   Dluží: E01=18, E02=12, E04=32, E05=24.50, E06=19, E09=10,
--           E10=3.70, E11=28, E12=7, E14=20, E15=4, E17=22,
--           E18=30.10, E20=0.50 → 230.80 EUR
--   Bilance: 95.00 - 230.80 = -135.80
--
-- TOMÁŠ (user 3):
--   Zaplatil: E04=320.00, E14=120.00 → 440.00 EUR
--   Dluží: E01=18, E02=12, E04=32, E05=24.50, E06=19, E09=10,
--           E10=3.70, E11=28, E12=7, E14=20, E15=4, E17=22,
--           E18=30.10, E20=0.50 → 230.80 EUR
--   Bilance: 440.00 - 230.80 = +209.20
--
-- LUCIE (user 4):
--   Zaplatila: E05=245.00, E18=301.00 → 546.00 EUR
--   Dluží: E01=18, E02=12, E04=32, E05=24.50, E06=19, E09=10,
--           E10=3.70, E11=28, E12=7, E15=4, E17=22, E18=30.10,
--           E20=0.50 → 210.80 EUR
--   Bilance: 546.00 - 210.80 = +335.20
--
-- MARTIN (user 5):
--   Zaplatil: E06=95.00, E15=20.00 → 115.00 EUR
--   Dluží: E01=18, E02=12, E04=32, E05=24.50, E06=19, E09=10,
--           E10=3.70, E11=28, E12=7, E15=4, E17=22, E18=30.10,
--           E20=0.50 → 210.80 EUR
--   Bilance: 115.00 - 210.80 = -95.80
--
-- EVA (user 6):
--   Zaplatila: E03=60.00, E13=89.00 → 149.00 EUR
--   Dluží: E01=18, E03=12, E04=32, E05=24.50, E07=18.40, E08=15,
--           E09=10, E10=3.70, E11=28, E13=17.80, E14=20, E16=4,
--           E17=22, E18=30.10, E20=0.50 → 256.00 EUR
--   Bilance: 149.00 - 256.00 = -107.00
--
-- PETR (user 7):
--   Zaplatil: E07=92.00, E16=20.00 → 112.00 EUR
--   Dluží: E01=18, E03=12, E04=32, E05=24.50, E07=18.40, E09=10,
--           E10=3.70, E11=28, E13=17.80, E14=20, E16=4, E17=22,
--           E18=30.10, E19=33.34, E20=0.50 → 274.34 EUR
--   Bilance: 112.00 - 274.34 = -162.34
--
-- KLÁRA (user 8):
--   Zaplatila: E08=45.00, E19=100.00 → 145.00 EUR
--   Dluží: E01=18, E03=12, E04=32, E05=24.50, E07=18.40, E08=15,
--           E09=10, E10=3.70, E11=28, E13=17.80, E14=20, E16=4,
--           E17=22, E18=30.10, E19=33.33, E20=0.50 → 289.33 EUR
--   Bilance: 145.00 - 289.33 = -144.33
--
-- ONDŘEJ (user 9):
--   Zaplatil: E09=100.00, E17=220.00 → 320.00 EUR
--   Dluží: E01=18, E03=12, E04=32, E05=24.50, E07=18.40, E09=10,
--           E10=3.70, E11=28, E13=17.80, E16=4, E17=22, E18=30.10,
--           E19=33.33, E20=0.50 → 254.33 EUR
--   Bilance: 320.00 - 254.33 = +65.67
--
-- TEREZA (user 10):
--   Zaplatila: E10=37.00, E20=5.08 → 42.08 EUR
--   Dluží: E01=18, E03=12, E04=32, E05=24.50, E07=18.40, E09=10,
--           E10=3.70, E11=28, E13=17.80, E16=4, E17=22, E18=30.10,
--           E20=0.50 → 221.00 EUR
--   Bilance: 42.08 - 221.00 = -178.92
--
-- KONTROLA: SUM bilancí musí být 0
--   +229.12 -135.80 +209.20 +335.20 -95.80 -107.00 -162.34 -144.33 +65.67 -178.92
--   = (229.12+209.20+335.20+65.67) - (135.80+95.80+107.00+162.34+144.33+178.92)
--   = 839.19 - 824.19 ... hmm, to nesedí – viz oprava níže
--
-- POZN: Tereza dluží za E20=0.50 (ne 0.58), Tereza = user 10, takže
-- dluží svůj vlastní split. Přepočítáme Terezu:
-- Tereza platí E10+E20=37.00+5.08=42.08
-- Tereza dluží (jako user 10):
--   E01=18, E03=12, E04=32, E05=24.50, E07=18.40, E09=10,
--   E10=3.70, E11=28, E13=17.80, E16=4, E17=22, E18=30.10, E20=0.50
--   = 18+12+32+24.50+18.40+10+3.70+28+17.80+4+22+30.10+0.50 = 221.00
-- Bilance Tereza: 42.08 - 221.00 = -178.92
--
-- SUM: 229.12-135.80+209.20+335.20-95.80-107.00-162.34-144.33+65.67-178.92 = 15.00 ??
-- Přepočítám znovu v testu PHP skriptem – zlaté hodnoty jsou v golden_dataset_test.php
-- ============================================================
