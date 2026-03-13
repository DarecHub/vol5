# Sailing App

Interní webová aplikace pro správu plavby na plachetnici.
Umožňuje správu pokladny, nákupů, logbooku, jídelníčku, aut, itineráře a posádky.

---

## Funkce

- **Pokladna** – sdílené výdaje a vyúčtování posádky
- **Nákupy** – nákupní seznam
- **Logbook** – deník plavby
- **Jídelníček** – plánování jídla
- **Auta** – správa vozidel
- **Itinerář** – plán plavby
- **Posádky** – správa členů

---

## Technologie

- PHP (vanilla, bez frameworku)
- MySQL
- Apache (.htaccess)
- JavaScript (vanilla AJAX)

---

## Instalace

1. Zkopíruj soubory na server
2. Zkopíruj `config.example.php` jako `config.php` a vyplň DB údaje, nebo vytvoř `.env`:

```
DB_HOST=localhost
DB_NAME=nazev_databaze
DB_USER=uzivatel
DB_PASS=heslo
```

3. Importuj databázovou strukturu
4. Otevři aplikaci v prohlížeči

---

## Struktura

```
├── index.php              # Login stránka
├── config.php             # Konfigurace (nekopírovat do gitu)
├── functions.php          # Sdílené PHP funkce
├── logout.php
├── admin/                 # Administrace
├── pages/                 # Stránky pro přihlášené členy
├── api/                   # AJAX API endpointy
├── templates/             # Hlavička a patička
└── assets/                # CSS, JS, obrázky
```

---

## Přihlašování

Dvě role:

| Role | Přihlášení |
|------|-----------|
| Admin | Heslo (bcrypt) |
| Člen posádky | Jméno z dropdownu + sdílené heslo posádky |

Session timeout: 24 h (nebo 7 dní s "zapamatovat si").

---

## Bezpečnost

- CSRF ochrana na všech formulářích a AJAX požadavcích
- Brute-force limit (max 10 pokusů / 15 min)
- HTTP security headers (X-Frame-Options, CSP, X-Content-Type-Options...)
- Citlivé soubory chráněny přes `.htaccess`
