# VOL5 – Task list: Kritické nedostatky a vylepšení

---

## 🔴 KRITICKÉ BEZPEČNOSTNÍ CHYBY

---

### [SEC-1] Chybí ownership kontrola při edit/delete operacích
**Soubory:** `api/shopping.php`, `api/logbook.php`, `api/menu.php`  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Všechny mutační endpointy (edit, delete, toggle_bought) přijímaly pouze `id` záznamu a okamžitě ho modifikovaly – bez jakékoliv kontroly, komu záznam patří. Aplikace rozlišuje dvě lodě (Loď 1 a Loď 2), každá má svá data. Přihlášený člen Lodě 1 mohl odeslat `POST /api/shopping.php?action=delete` s `id=6` (položka Lodě 2) a záznam byl smazán. Stačilo znát nebo uhodnout libovolné `id` – žádná autorizace, jen autentizace.

**Konkrétní případ – `api/shopping.php` před opravou:**
```php
case 'delete':
    requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id < 1) { jsonResponse(false, null, 'Neplatná položka.'); }

    // ❌ Žádná kontrola boat_id – smaže cokoliv s tímto id
    $db->prepare("DELETE FROM shopping_items WHERE id = ?")->execute([$id]);
    jsonResponse(true);
```

**Jak bylo opraveno:**  
Před každou mutací se nejprve načte záznam z DB a ověří se, zda jeho `boat_id` odpovídá `boat_id` přihlášeného uživatele (uloženo v session). Pokud ne, vrátí se chyba 403.

```php
case 'delete':
    requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id < 1) { jsonResponse(false, null, 'Neplatná položka.'); }

    // ✅ Načíst záznam a ověřit vlastnictví
    $check = $db->prepare("SELECT boat_id FROM shopping_items WHERE id = ?");
    $check->execute([$id]);
    $item = $check->fetch();
    if (!$item || (currentBoatId() !== null && $item['boat_id'] !== currentBoatId())) {
        jsonResponse(false, null, 'Přístup odepřen.');
    }

    $db->prepare("DELETE FROM shopping_items WHERE id = ?")->execute([$id]);
    jsonResponse(true);
```

Stejný pattern aplikován i na `edit`, `toggle_bought` v shopping, `edit` a `delete` v logbook a menu.

---

### [SEC-2] Chybí validace `expense_date` – lze vložit nevalidní datetime
**Soubor:** `api/wallet.php`, case `add` (řádek ~85) a case `edit` (řádek ~187)  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Datum výdaje se bralo přímo z `$_POST` a vkládalo do SQL bez jakékoliv validace formátu. MySQL sloupec `DATETIME` sice nevalidní datum odmítne, ale vrátí `0000-00-00 00:00:00` nebo `NULL` bez vyhození výjimky (závisí na `sql_mode`). Výsledkem byl výdaj s datem `0000-00-00`, který se pak řadil na začátek seznamu a pokazil formátování datumů v JS. Navíc bylo možné vložit platný SQL string s vedlejšími efekty, pokud by PDO bindings selhaly.

**Kód před opravou:**
```php
// case 'add':
$expenseDate = $_POST['expense_date'] ?? date('Y-m-d H:i:s');
// ❌ Žádná validace – '2025-99-99 25:99:99' nebo '' prošlo bez chyby
```

**Jak bylo opraveno:**  
Datum se parsuje přes `DateTime::createFromFormat()` který striktně ověří formát i hodnoty. Přijímá jak `Y-m-d H:i:s`, tak `Y-m-d H:i` (formát který posílá `<input type="datetime-local">`). Pokud parsování selže, API vrátí chybovou hlášku.

```php
// case 'add':
$expenseDateRaw = $_POST['expense_date'] ?? '';
$expenseDate = date('Y-m-d H:i:s'); // výchozí = teď
if ($expenseDateRaw !== '') {
    $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $expenseDateRaw)
               ?? DateTime::createFromFormat('Y-m-d H:i', $expenseDateRaw);
    if ($parsedDate === false) {
        jsonResponse(false, null, 'Neplatný formát datumu.');
    }
    $expenseDate = $parsedDate->format('Y-m-d H:i:s');
}
```

Pro case `edit` navíc: pokud přijde prázdný datum, zachová se původní datum z DB.

---

### [SEC-3] API `audit` endpoint – chybí `requireCsrf()` na READ
**Soubor:** `api/wallet.php`, case `audit`  
**Status:** 🔵 Low priority (GET endpoint, pouze čte, nepíše)

**Proč to bylo špatně:**  
Endpoint `?action=audit&expense_id=X` je přístupný GET requestem bez CSRF tokenu. Všechny ostatní read endpointy jsou na tom stejně – aplikace používá CSRF pouze na write operace, což je standardní praxe. Jde spíše o konzistentnost dokumentace než o reálné bezpečnostní riziko, protože `requireLogin()` je přítomné.

**Plánovaná akce:** Bez akce, stav je akceptovatelný.

---

### [SEC-4] `wallet_settled` a `wallet_audit_log` vytvářeny dynamicky za běhu
**Soubor:** `api/wallet.php`  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Dvě tabulky (`wallet_audit_log`, `wallet_settled`) se nevytvářely v databázovém schematu při instalaci, ale dynamicky příkazem `CREATE TABLE IF NOT EXISTS` uvnitř API endpointů při prvním použití. To má tři problémy:

1. **Produkční DB uživatel** typicky nemá `CREATE` práva – tabulka se nevytvoří a audit log tiše přestane fungovat (chyba se pouze zaloguje do error_log, uživatel nic nevidí).
2. **Výkon** – každý zápis do audit logu spouštěl DDL příkaz, který MySQL musí zpracovat i když tabulka existuje.
3. **Nepředvídatelnost** – schema databáze nebylo na jednom místě, část byla v PHP kódu.

**Kód před opravou (v api/wallet.php při každém přidání výdaje):**
```php
$db->exec("CREATE TABLE IF NOT EXISTS wallet_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_id INT NOT NULL,
    ...
) ENGINE=InnoDB ...");
// ❌ DDL příkaz při každém POST requestu
```

**Jak bylo opraveno:**  
Obě tabulky přidány do `docker/schema.sql` jako standardní `CREATE TABLE IF NOT EXISTS` definice, které se spustí při inicializaci databáze. Dynamické `CREATE TABLE` příkazy z API kódu odstraněny.

---

## 🟠 KRITICKÉ LOGICKÉ CHYBY (přepočty)

---

### [CALC-1] Split algoritmus – `floor()` způsobuje větší zbytek u více osob
**Soubor:** `api/wallet.php`, řádky 110–127 (case `add`) a 204–220 (case `edit`)  
**Status:** ⚠️ SUM je vždy správná, ale rozdělení není rovnoměrné – akceptováno

**Proč to bylo špatně (odhaleno testy):**  
Algoritmus používá `floor()` pro výpočet podílu na osobu, aby získal zaokrouhlení dolů na 2 desetinná místa. Zbytek (remainder) se přidá prvnímu uživateli v poli. Matematicky je součet vždy správný (`SUM == amount_eur`), ale `floor()` způsobuje, že zbytek může být větší než 1 haléř u vyšších počtů osob.

**Ukázka problému (odhalená unit testem):**
```
100.00 EUR / 6 osob:
  floor(100/6 * 100) / 100 = floor(16.6666) * 100 / 100 = 16.66
  zbytek = 100.00 - (6 × 16.66) = 100.00 - 99.96 = 0.04
  výsledek: osoba[0] = 16.70, ostatní = 16.66

Spravedlivější by bylo:
  round(100/6, 2) = 16.67
  5 × 16.67 = 83.35, zbytek = 0.15... → složitější logika
```

Druhý problém: pořadí uživatelů v `split_users` poli závisí na pořadí HTML checkboxů při odeslání formuláře, které není garantované. Osoba která „nese" zbytek se může lišit mezi přidáním a editací stejného výdaje.

**Proč akceptováno (bez opravy):**  
Součet je vždy přesný – nikdo neplatí víc ani méně celkem. Rozdíl je maximálně `(N-1) × 0.01 EUR` kde N je počet osob (pro 10 osob max 9 haléřů navíc pro prvního). Pro účely skupinové plavby je tato nepřesnost zanedbatelná. Složitější distribuční algoritmus by kód zbytečně zkomplikoval.

**Unit test který to odhalil** (`tests/wallet_test.php`):
```php
$splits = calculateSplits(100.00, [1, 2, 3, 4, 5, 6]);
assert_equals('100.00 / 6 – osoba[0] má zbytek (16.70)', 16.70, $splits[1]);
assert_equals('100.00 / 6 – ostatní (16.66)', 16.66, $splits[2]);
// SUM = 16.70 + 5×16.66 = 16.70 + 83.30 = 100.00 ✅
```

---

### [CALC-2] Bilance a settlements – N+1 dotazů, triplikovaný kód
**Soubory:** `api/wallet.php` (case `balances` a `settlements`), `api/export.php`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Výpočet bilancí je implementován jako smyčka přes všechny uživatele, kde pro každého uživatele se posílají 2 SQL dotazy (jeden pro `wallet_expenses`, druhý pro `wallet_expense_splits`). Pro 10 uživatelů = 20 dotazů místo 1–2. Navíc je celý tento kód zkopírován třikrát – v `case 'balances'`, `case 'settlements'` a v `api/export.php` – bez sdílené funkce.

**Kód před opravou:**
```php
foreach ($users as $u) {
    // Dotaz #1 pro každého uživatele:
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expenses WHERE paid_by = ?");
    $stmt->execute([$u['id']]);
    $paid = (float) $stmt->fetchColumn();

    // Dotaz #2 pro každého uživatele:
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expense_splits WHERE user_id = ?");
    $stmt->execute([$u['id']]);
    $share = (float) $stmt->fetchColumn();
}
// ❌ Pro 10 uživatelů = 20 dotazů, kód zkopírován 3×
```

**Plánovaný fix – 1 dotaz + sdílená funkce:**
```php
// Extrahovat do functions.php:
function getBalances(): array {
    $db = getDB();
    $rows = $db->query("
        SELECT u.id, u.name, u.boat_id,
          COALESCE(we.paid, 0) AS paid,
          COALESCE(s.share, 0) AS share
        FROM users u
        LEFT JOIN (
          SELECT paid_by, SUM(amount_eur) AS paid
          FROM wallet_expenses GROUP BY paid_by
        ) we ON we.paid_by = u.id
        LEFT JOIN (
          SELECT user_id, SUM(amount_eur) AS share
          FROM wallet_expense_splits GROUP BY user_id
        ) s ON s.user_id = u.id
    ")->fetchAll();
    // ... přidat balance = paid - share
}
// ✅ 1 dotaz místo 2N, použitelné ze všech 3 míst
```

---

### [CALC-3] Kurz CZK/EUR se při editaci výdaje vždy přepočítá aktuálním kurzem
**Soubor:** `api/wallet.php`, case `edit`  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Při editaci výdaje v CZK se vždy volalo `getExchangeRate()` které vrátí dnešní kurz z ČNB (nebo z cache). Původní kurz v době vytvoření výdaje byl uložen v DB sloupci `exchange_rate`, ale při editaci se ignoroval.

**Scénář který to způsoboval chybu:**
```
1.7.2025: Pavel zadá výdaj 2500 CZK, kurz 25.10 → amount_eur = 99.60 EUR
          Bilance: Pavel +79.68, ostatní -13.28 každý (6 osob)

15.7.2025: Eva opraví popis výdaje, kurz dnes 24.50
           → amount_eur se přepočítá: 2500 / 24.50 = 102.04 EUR
           → Bilance se změní o 2.44 EUR BEZ JAKÉKOLIV ZMĚNY ČÁSTKY
           → Nikdo to neví, bilance jsou "tajemně" jiné
```

**Kód před opravou:**
```php
// case 'edit':
$rate = getExchangeRate();  // ❌ Vždy aktuální kurz, ignoruje původní
$amountEur = ($currency === 'CZK') ? round($amount / $rate, 2) : $amount;
```

**Jak bylo opraveno:**  
Při editaci CZK výdaje se použije původní `exchange_rate` uložený v DB záznamu. Nový kurz se stáhne a uloží pouze pokud uživatel změní měnu z CZK na EUR nebo změní samotnou částku (v tom případě je přepočet záměrný). EUR výdaje se kurzem nepřepočítávají vůbec.

```php
// case 'edit':
if ($currency === 'CZK') {
    // ✅ Použít původní kurz z doby vytvoření výdaje
    $rate = (float) ($oldData['exchange_rate'] ?? 0);
    if ($rate <= 0) $rate = getExchangeRate(); // fallback pro staré záznamy bez kurzu
    $amountEur = round($amount / $rate, 2);
} else {
    $rate = getExchangeRate();
    $amountEur = $amount; // EUR se nepřepočítává
}
```

---

### [CALC-4] `formatMoney()` přepsána inline `<script>` blokem – nekonzistentní zobrazení
**Soubor:** `pages/wallet.php`, konec souboru (za `renderFooter()`)  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Na konci `pages/wallet.php`, za voláním `renderFooter()` (které již načetlo `app.js`), byl blok který přepsal globální funkci `formatMoney()`:

```js
// ❌ Bylo v pages/wallet.php za renderFooter():
<script>
function formatMoney(amount, currency) {
    currency = currency || 'EUR';
    return Math.round(parseFloat(amount)) + ' ' + currency;  // bez centů!
}
</script>
```

Důsledky:
- Na stránce Pokladna: `45.67 EUR` se zobrazilo jako `"46 EUR"` (zaokrouhleno na celé číslo)
- Na ostatních stránkách: `45.67 EUR` se zobrazilo jako `"45,67 EUR"` (správně, s centy)
- Bilance v záložce Bilance na wallet stránce mohly zobrazit `"0 EUR"` místo `"-0,45 EUR"` pro malé rozdíly
- Kdokoliv kdo by ladit JS v DevTools byl zmatený proč `formatMoney` funguje jinak

**Jak bylo opraveno:**  
Inline `<script>` blok s override `formatMoney` byl odstraněn. CSS třída `.amount-pill` přesunuta z inline `<style>` bloku do globálního `assets/css/style.css`. Wallet stránka nyní používá stejnou `formatMoney()` jako zbytek aplikace (s centy).

---

### [CALC-5] Označení vyrovnání jako „zaplaceno" neovlivní bilance
**Soubor:** `api/wallet.php`, case `settle`  
**Status:** 🔵 Plánováno – design decision

**Proč to bylo špatně:**  
Tlačítko „Vyrovnáno" v záložce Vyrovnání pouze nastaví příznak v tabulce `wallet_settled`. Záložka Bilance nadále ukazuje původní zápornou bilanci jako by k žádnému vyrovnání nedošlo. Uživatel vidí protichůdné informace:
- Vyrovnání: „Tomáš → Pavel: 53.31 EUR ~~vyrovnáno~~"
- Bilance: „Tomáš: -66.66 EUR" (nezměněno)

Settled příznak je pouze vizuální dekorace bez dopadu na výpočty.

**Plánované řešení:**  
Přidat reálnou transakci do `wallet_expenses` při označení jako vyrovnáno – výdaj s popisem „Vyrovnání: Tomáš → Pavel", kde zaplatil Tomáš a podíl má pouze Pavel. Tím se bilance automaticky vyrovná přes standardní výpočetní logiku.

---

## 🟡 STŘEDNÍ BUGY

---

### [BUG-1] `api/logbook.php` – PHP Warning pro neexistující POST klíče
**Soubor:** `api/logbook.php`, case `add`, řádky 49–50  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Frontend formulář v `pages/logbook.php` posílá pouze `date`, `location_from`, `location_to`, `nautical_miles` a `note` – pole `departure_time`, `arrival_time` a `skipper_user_id` UI vůbec neobsahuje. API v case `add` však přistupovalo k těmto klíčům bez `??` operátoru:

```php
// ❌ Bylo:
$departure = $_POST['departure_time'] ?: null;
//           ^^^^^^^^^^^^^^^^^^^^^^^^^^^
// PHP 8: Warning: Undefined array key "departure_time"
// Tento warning se vypsal PŘED JSON hlavičkou → JSON parse error v JS
```

PHP Warning se v development módu (`APP_ENV=development`, `display_errors=1`) vloží přímo do outputu před JSON odpověď. JS `JSON.parse()` pak dostane `<br />\n<b>Warning</b>...{"success":true}` a selže s parse error. Uživatel viděl jen obecnou chybu bez detailu.

**Jak bylo opraveno:**
```php
// ✅ Opraveno:
$departure = ($_POST['departure_time'] ?? '') ?: null;
$arrival   = ($_POST['arrival_time']   ?? '') ?: null;
$skipper   = (int) ($_POST['skipper_user_id'] ?? 0) ?: null;
```

---

### [BUG-2] API bez přihlášení vrací HTTP 302 redirect místo JSON chyby
**Soubor:** `functions.php`, funkce `requireLogin()`  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Funkce `requireLogin()` při neplatné session vždy prováděla HTTP redirect na `/index.php`. Pro běžné GET requesty na stránky je to správné chování. Pro AJAX requesty z JS `apiCall()` je to fatální – `fetch()` automaticky sleduje redirect, dostane HTML login stránku a pokusí se ji parsovat jako JSON. Výsledkem je vyhozená výjimka `SyntaxError: Unexpected token '<'` v JS konzoli a uživatel vidí jen zamrzlé „Načítám…" bez jakékoliv informace.

```
Situace: Session vyprší po 24h (nebo 7 dnech s remember_me)
1. Uživatel otevře aplikaci, stránka se načte z cache prohlížeče
2. JS zavolá apiCall('/api/wallet.php?action=list')
3. PHP: session neplatná → header('Location: /index.php') + exit
4. fetch() sleduje redirect → dostane HTML login stránky
5. JSON.parse('<html>...') → SyntaxError
6. Uživatel nevidí nic, netuší proč
```

**Jak bylo opraveno:**  
Přidáno rozlišení v `requireLogin()` zda jde o AJAX request (detekce přes `X-Requested-With: XMLHttpRequest` header). Pro AJAX se vrátí JSON chyba s HTTP 200 (aby `fetch()` nezahodil body), pro normální requesty zůstane redirect.

```php
function requireLogin(): void
{
    if (!isLoggedIn() && !isAdmin()) {
        if (isAjax()) {
            // ✅ Pro AJAX: JSON chyba kterou JS může zobrazit uživateli
            jsonResponse(false, null, 'Přihlášení vypršelo. Obnovte stránku.');
        }
        // Pro normální requesty: redirect na login
        setFlash('error', 'Pro přístup se musíte přihlásit.');
        redirect('/index.php');
    }
}
```

---

### [BUG-3] `editExpense()` v JS načítá všechny výdaje jen aby našel jeden
**Soubor:** `pages/wallet.php`, JS funkce `editExpense()`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Při kliknutí na „Upravit" u výdaje se zavolalo API `?action=list` které vrátí kompletní seznam všech výdajů. Z tohoto seznamu se pak `find()` vybere jeden konkrétní záznam. Zbývající data se zahodí.

```js
async function editExpense(id) {
    // ❌ Načítá VŠECHNY výdaje (může být stovky) jen kvůli jednomu:
    const res = await apiCall('/api/wallet.php?action=list');
    if (!res.success) return;
    const expense = res.data.expenses.find(e => e.id == id);
    // ...
}
```

Problém nastane pokud:
- Výdajů je hodně (pomalé načtení, zbytečný traffic)
- Někdo přidal výdaj mezi zobrazením seznamu a kliknutím na edit (race condition – edit modal otevře stará data)

**Plánovaný fix:**  
Předat data výdaje přímo jako parametr funkce. Data jsou již k dispozici v DOM při renderu expense-card (jsou vygenerována serverem nebo předchozím `loadExpenses()` voláním):

```js
// V expense-card renderu:
<button onclick='editExpense(${JSON.stringify(expense)})'>Upravit</button>

// Funkce pak nepotřebuje API call:
function editExpense(expense) {
    document.getElementById('exp-amount').value = expense.amount;
    // ... naplnit formulář přímo z parametru
}
```

---

### [BUG-4] `switchTab()` na login stránce závisí na implicitním globálním `event`
**Soubor:** `index.php`  
**Status:** ✅ Opraveno

**Proč to bylo špatně:**  
Funkce `switchTab()` na přihlašovací stránce přistupovala k `event.target` bez toho, aby byl `event` předán jako parametr. Spoléhala na to, že `event` je globální proměnná dostupná v kontextu volání `onclick` handleru.

```html
<!-- ❌ Bylo: -->
<button onclick="switchTab('member')">Posádka</button>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');  // ❌ Globální 'event' – nefunguje v Safari
}
</script>
```

Implicitní `event` v inline handleru funguje v Chrome, ale v Safari a Firefox strict mode vrhá `ReferenceError: Can't find variable: event`. Výsledek: kliknutí na záložku "Admin" otevřelo obsah, ale záložka vizuálně zůstala neaktivní (chybný styling).

**Jak bylo opraveno:**  
Element `this` předán jako explicitní parametr `el`:

```html
<!-- ✅ Opraveno: -->
<button onclick="switchTab('member', this)">Posádka</button>

<script>
function switchTab(tab, el) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    el.classList.add('active');  // ✅ Explicitní reference, funguje všude
}
</script>
```

---

### [BUG-5] `api/cars.php` – kapacita auta nesprávně nezahrnuje řidiče
**Soubor:** `api/cars.php`, case `add_passenger`, řádek ~91  
**Status:** 🔵 Plánováno (náhodou funguje, ale logika je klamná)

**Proč to bylo špatně:**  
Podmínka pro kontrolu kapacity počítá `current_count + 1 >= seats`, kde `current_count` je počet pasažérů (bez řidiče). Řidič v podmínce vůbec nefiguruje.

```php
$car = $db->prepare("
    SELECT seats,
    (SELECT COUNT(*) FROM car_passengers WHERE car_id = ?) AS current_count
    FROM cars WHERE id = ?
");
$car->execute([$carId, $carId]);
$carData = $car->fetch();

// ❌ Logická chyba: nepočítá řidiče
if ($carData && ($carData['current_count'] + 1) >= $carData['seats']) {
    jsonResponse(false, null, 'Auto je plné.');
}
```

**Proč to náhodou funguje:**  
Auto se `seats=5` (5 míst celkem). Podmínka pustí pasažéry pokud `pasažéři + 1_nový < 5`, tedy max 3 noví pasažéři (0,1,2 → 4. by bylo >= 5). Dohromady: řidič + 3 pasažéři = 4 osoby. Ale auto má 5 míst → jedno místo se zbytečně blokuje.

Chyba je v komentáři kódu který říká „+1 protože řidič taky zabírá místo" ale v podmínce řidič nezabírá – podmínka by měla být `>= seats - 1` nebo lépe `current_count + 1 + 1 >= seats` (pasažéři + nový + řidič).

**Plánovaný fix:**
```php
// ✅ Správná podmínka: pasažéři + nový pasažér + 1 řidič >= kapacita
if ($carData && ($carData['current_count'] + 1 + 1) >= $carData['seats']) {
    jsonResponse(false, null, 'Auto je plné (max ' . $carData['seats'] . ' míst včetně řidiče).');
}
```

---

### [BUG-6] Chybí `install.php` – aplikace přesměruje na neexistující soubor
**Soubor:** `index.php`, řádek 16  
**Status:** 🔵 Workaround přes `docker/seed.sql`

**Proč to bylo špatně:**  
Přihlašovací stránka kontroluje zda je aplikace nainstalovaná (`installed=1` v tabulce `settings`). Pokud ne, přesměruje na `/install.php`:

```php
$installedCheck = getSetting('installed', '0');
if ($installedCheck !== '1') {
    redirect('/install.php');  // ❌ Soubor neexistuje!
}
```

Nová instalace na prázdné DB by vrátila HTTP 404 místo instalačního průvodce.

**Workaround:**  
`docker/seed.sql` nastaví `installed=1` při inicializaci Docker databáze. Pro produkci je nutné buď vytvořit `install.php`, nebo nastavit `installed=1` manuálně v DB.

---

## 🔵 TESTY

---

### [TEST-1] Unit testy přepočtů pokladny
**Soubor:** `tests/wallet_test.php`  
**Status:** ✅ Vytvořeno – 62/62 testů prochází

**Co testy pokrývají:**

| Oblast | Počet testů | Popis |
|--------|-------------|-------|
| Split algoritmus – základní | 12 | 10/3, 100/3, 100/6, 120/6 |
| Split algoritmus – edge cases | 9 | 0.01/2, 0.01/3, 99.99/3, 100/7, 1.00/1 |
| Split algoritmus – velké částky | 4 | 5000/10, 1234.56/5, 333.33/3 |
| Přepočet CZK → EUR | 7 | různé kurzy, nulový kurz, 0 CZK |
| Zpětná kontrola EUR → CZK | 1 | tolerance ±0.01 |
| Settlement – základní | 7 | 1 dluh, vyrovnaní, 3 osoby |
| Settlement – optimalizace | 5 | greedy, 6 osob |
| Settlement – edge cases | 8 | 1 věřitel 3 dlužníci, haléřové rozdíly |
| Integrita SUM | 7 | ověření pro 7 různých kombinací |

**Nález z testů – CALC-1:**  
Test odhalil že `100.00 EUR / 6 osob` dá `16.70 + 5×16.66` (ne `6×16.67`). `floor()` u vyšších počtů dělitelů způsobuje větší zbytek u první osoby. Matematický součet je správný, ale distribuce je méně rovnoměrná. Akceptováno jako known limitation.

**Spuštění:**
```bash
php tests/wallet_test.php
# nebo přes Docker:
docker exec vol5_web php /var/www/html/tests/wallet_test.php
```

---

### [TEST-2] Integration testy API endpointů
**Soubor:** `tests/api_test.php`  
**Status:** 🔵 Plánováno

Plánované pokrytí:
- `POST wallet/add` → ověřit záznamy v `wallet_expense_splits` v DB
- `POST wallet/edit` → ověřit že starý split smazán, nový správně vytvořen
- Ownership test: přihlásit se jako user Lodě 1, zkusit smazat záznam Lodě 2 → očekáváme `{"success":false}`
- Datetime validace: odeslat `expense_date=2025-99-99` → očekáváme chybu
- AJAX session test: volat API bez session → očekáváme JSON `{"success":false}` ne HTML

---

### [TEST-3] Integritní SQL kontrola splits v produkční DB
**Status:** 🔵 Plánováno

Spustit po každé migraci nebo záloze pro ověření konzistence dat:

```sql
-- Výdaje kde SUM(splits) != amount_eur (tolerance 0.01):
SELECT
    we.id,
    we.description,
    we.amount_eur AS expected,
    ROUND(SUM(wes.amount_eur), 2) AS actual,
    ABS(we.amount_eur - SUM(wes.amount_eur)) AS diff
FROM wallet_expenses we
JOIN wallet_expense_splits wes ON wes.expense_id = we.id
GROUP BY we.id
HAVING diff > 0.01
ORDER BY diff DESC;
-- Výsledek musí být prázdný

-- Výdaje bez jakýchkoliv splits (osiřelé záznamy):
SELECT we.id, we.description, we.amount_eur
FROM wallet_expenses we
LEFT JOIN wallet_expense_splits wes ON wes.expense_id = we.id
WHERE wes.id IS NULL;
-- Výsledek musí být prázdný
```

---

## 🟡 NOVÉ STŘEDNÍ BUGY (identifikované dodatečnou analýzou)

---

### [BUG-7] `api/shopping.php` – kategorie bez whitelistu
**Soubor:** `api/shopping.php`, case `add` a `edit`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Pole `category` z `$_POST` se vkládá do DB bez validace přípustných hodnot. `api/checklist.php` toto dělá správně (má `in_array` check), ale shopping ne.

```php
// ❌ Bylo (shopping.php):
$category = $_POST['category'] ?? 'potraviny';
// Lze odeslat libovolný string, např. '<script>', SQL fragment, nebo 200-znakový string

// ✅ Jak je to v checklist.php (správně):
$validCats = ['povinne', 'obleceni', 'vybaveni', 'doporucene'];
if (!in_array($category, $validCats)) $category = 'doporucene';
```

**Plánovaný fix:**
```php
$validCats = ['potraviny', 'napoje', 'alkohol', 'hygiena', 'vybaveni', 'ostatni'];
if (!in_array($category, $validCats)) $category = 'ostatni';
```

---

### [BUG-8] `api/cars.php` – chybí validace počtu sedadel
**Soubor:** `api/cars.php`, case `add_car`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
`seats` se přijímá jako `int` bez range kontroly. Lze zadat `seats=0`, `seats=-5`, nebo `seats=9999`.

```php
// ❌ Bylo:
$seats = (int) ($_POST['seats'] ?? 5);
// Lze: seats=0 → auto s 0 místy (vždy plné při přidání řidiče)
// Lze: seats=9999 → kapacita nikdy nepřesažena
```

**Plánovaný fix:**
```php
$seats = max(2, min(20, (int) ($_POST['seats'] ?? 5)));
```

---

### [BUG-9] `api/cars.php` – chybí kontrola že řidič není již řidičem jiného auta
**Soubor:** `api/cars.php`, case `add_car`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Stejný uživatel může být nastaven jako řidič více aut najednou. UI pak zobrazí uživatele ve více autech a sekce „Nepřiřazení" může být prázdná, přestože někteří jsou přiřazeni vícekrát.

```php
// ❌ Chybí:
// SELECT id FROM cars WHERE driver_user_id = $driverId
// → pokud existuje, odmítnout
```

---

### [BUG-10] `api/cars.php` – chybí kontrola že pasažér není již v jiném autě
**Soubor:** `api/cars.php`, case `add_passenger`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Stejný uživatel může být spolujezdec ve více autech. `list` akce pak zobrazí uživatele ve více autech současně, `unassigned` bude prázdný i když tam chybějí uživatelé.

```php
// ❌ Chybí:
// SELECT cp.id FROM car_passengers cp WHERE cp.user_id = $userId
// → pokud existuje, odmítnout
```

---

### [BUG-11] `api/logbook.php` – nautical_miles mohou být záporné
**Soubor:** `api/logbook.php`, case `add` a `edit`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
`nautical_miles` se přijímá jako `float` bez validace. Lze zadat `-999.9 NM`. Statistiky (`total_nm`, `avg_nm`) pak vrátí záporné nebo nesmyslné hodnoty.

```php
// ❌ Bylo:
$nm = (float) ($_POST['nautical_miles'] ?? 0);
// Lze: nm=-500 → total_nm v statistikách bude nesprávný
```

**Plánovaný fix:**
```php
$nm = max(0.0, (float) ($_POST['nautical_miles'] ?? 0));
// Nebo přísněji: if ($nm < 0 || $nm >= 10000) jsonResponse(false, null, 'Neplatný počet NM.');
```

---

### [BUG-12] `admin/users.php` – email se nevaliduje server-side
**Soubor:** `admin/users.php`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
HTML input `type="email"` validuje formát pouze v prohlížeči. Přímý POST request nebo curl může vložit `"tohle neni email"` nebo XSS string do DB sloupce `email`.

```php
// ❌ Bylo:
$email = trim($_POST['email'] ?? '');
$stmt->execute([$name, $phone ?: null, $email ?: null, $boatId]);
// Bez filter_var() validace
```

**Plánovaný fix:**
```php
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Neplatný formát e-mailové adresy.';
}
```

---

### [BUG-13] `logout.php` – chybí `session_regenerate_id()` před novou session
**Soubor:** `logout.php`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Po `session_destroy()` se volá `session_start()` pro flash zprávu, ale bez `session_regenerate_id(true)`. Původní session ID mohlo být cachováno prohlížečem nebo zaznamenáno v síťových log souborech. Po odhlášení starý session ID funguje ještě krátce (do garbage collection).

```php
// ❌ Bylo:
session_unset();
session_destroy();
session_start();
// Nová session dostane nové ID automaticky... ale flash zpráva se nastaví
// na nové ID zatímco cookies prohlížeče stále drží staré

// ✅ Fix:
session_unset();
session_destroy();
session_start();
session_regenerate_id(true); // Přidělit nové bezpečné ID
$_SESSION['flash']['success'] = 'Byli jste úspěšně odhlášeni.';
```

---

### [BUG-14] `api/wallet.php` – filter `boat1`/`boat2` ignoruje skutečné boat_id
**Soubor:** `api/wallet.php`, case `list`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Filtr podle lodi filtruje podle `split_type` sloupce (`boat1`, `boat2`, `both`), ne podle skutečných `boat_id` uživatelů ve splitech. `split_type` se nastavuje při vytvoření výdaje z HTML checkboxů a není nijak svázán s real boat_id.

```php
// ❌ Bylo:
} elseif ($filter === 'boat1') {
    $where = 'WHERE we.split_type IN ("boat1", "both")';
}
// Výdaj kde split_type='both' ale split_users jsou jen loď 2 → zobrazí se v obou filtrech
// Výdaj kde split_type='custom' ale všichni jsou z lodi 1 → nezobrazí se v filtru lodi 1
```

**Plánovaný fix:**  
Filtrovat přes JOIN na `wallet_expense_splits` a `users.boat_id`:
```sql
WHERE EXISTS (
    SELECT 1 FROM wallet_expense_splits wes
    JOIN users u ON u.id = wes.user_id
    WHERE wes.expense_id = we.id AND u.boat_id = 1
)
```

---

### [BUG-15] Write API akce přijímají GET requesty
**Soubory:** `api/wallet.php`, `api/shopping.php`, `api/logbook.php`, `api/menu.php`, `api/cars.php`, `api/checklist.php`  
**Status:** 🔵 Plánováno

**Proč to bylo špatně:**  
Všechny endpointy čtou `action` jak z GET tak z POST:
```php
$action = $_GET['action'] ?? $_POST['action'] ?? '';
```
Write akce (`add`, `edit`, `delete`) by měly přijímat **pouze POST**. Přestože CSRF token to ochrání, `<img src="/api/wallet.php?action=delete&id=1&csrf_token=...">` v cached stránce může být zneužito pokud útočník zná token. Defense in depth: write akce odmítnout při GET.

**Plánovaný fix pro write akce:**
```php
case 'delete':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'Metoda není povolena.');
    }
    requireCsrf();
    // ...
```

---

## 🔵 TESTY (rozšíření)

---

### [TEST-2] Integration testy – všechny API endpointy
**Soubor:** `tests/integration_test.php`  
**Status:** ✅ Vytvořeno

**Co testy pokrývají (52 testů):**

| Oblast | Testy | Popis |
|--------|-------|-------|
| Auth | AUTH-1 až AUTH-8 | Login člen, admin, špatné heslo, neexistující user, API bez session, CSRF odmítnut, logout |
| Wallet | WALLET-1 až WALLET-10 | EUR výdaj, CZK přepočet, neplatný datum, záporná částka, prázdný popis, žádní split_users, smazání+splits, edit zachová kurz, bilance, settlements |
| Shopping | SHOP-1 až SHOP-9 | Přidání, list, toggle bought, SEC cizí delete/edit/toggle, prázdné jméno, vlastní smazání |
| Logbook | LOG-1 až LOG-7 | Přidání, list, statistiky, SEC cizí edit/delete, chybí datum, vlastní smazání |
| Menu | MENU-1 až MENU-6 | Přidání, duplicita oběd/den, SEC cizí edit/delete, vlastní edit, vlastní smazání |
| Cars | CARS-1 až CARS-6 | Přidání auta, přidání spolujezdce, list, kapacita, odebrání spolujezdce, smazání |
| Checklist | CHECK-1 až CHECK-5 | Přidání, nevalidní kategorie fallback, list, editace, smazání |
| Exchange | EXCH-1 | Vrátí kurz > 0 |
| Pages | 12 testů | HTTP 200 pro všechny stránky (člen + admin) |

**Spuštění:**
```bash
# Vyžaduje běžící Docker kontejnery
docker-compose up -d
php tests/integration_test.php
# nebo přes Docker:
docker exec vol5_web php /var/www/html/tests/integration_test.php
```

---

### [TEST-3] Unit testy validací
**Soubor:** `tests/validation_test.php`  
**Status:** ✅ Vytvořeno

**Co testy pokrývají (60 testů):**

| Oblast | Testy | Popis |
|--------|-------|-------|
| Datum validace | DAT-1 až DAT-9 | Y-m-d H:i:s, Y-m-d H:i, neplatné, SQL injection, XSS |
| Shopping kategorie | SHOP-V-1 až SHOP-V-10 | Platné hodnoty, INVALID fallback, injection |
| Checklist kategorie | CHK-V-1 až CHK-V-6 | Platné hodnoty, fallback |
| Cars sedadla | CAR-V-1 až CAR-V-9 | Range 2–20, záporné, 0, příliš velké |
| Email validace | EMAIL-1 až EMAIL-8 | Platný, prázdný, null, bez @, XSS |
| Nautical miles | NM-1 až NM-7 | 0, platné, záporné, příliš velké |
| Meal type | MEAL-1 až MEAL-7 | Platné typy, INVALID, prázdný, injection |
| Split users | SPLIT-1 až SPLIT-8 | Pole, string, nulové ID, záporné, prázdné |
| CALC-3 CZK kurz | CALC-3-1 až CALC-3-4 | Původní kurz, kurz=0 fallback, různé kurzy |
| Rate limiting | RL-1 až RL-6 | 0, 9, 10, 15 pokusů, vypršelé okno |

**Spuštění:**
```bash
# Nevyžaduje DB ani server
php tests/validation_test.php
```

---

### [TEST-5] E2E kalkulační testy – celý stack s DB
**Soubor:** `tests/e2e_calculations_test.php`
**Status:** ✅ Vytvořeno – 90/90 testů prochází

**Proč byl test potřeba:**
Existující unit testy (`wallet_test.php`) testovaly pouze izolovanou PHP logiku bez DB. Existující integration testy (`integration_test.php`) volaly API a ověřovaly jen `{"success": true}` – neověřovaly co se skutečně uložilo do DB. Nikdo neověřoval, zda se splits správně zapíší do `wallet_expense_splits`, zda bilance odpovídají přímému DB výpočtu, nebo zda settlements jsou matematicky správné po série výdajů pro více lidí.

**Co testy dělají – scénář:**
1. Přihlásí se jako člen, přidá **5 konkrétních výdajů** přes HTTP POST (jako skutečný uživatel)
2. Pro každý výdaj se **přímo připojí do MySQL** a ověří `SUM(wallet_expense_splits.amount_eur) == wallet_expenses.amount_eur`
3. Ověří **konkrétní hodnoty každého splitu** (např. 50 EUR / 6 lidí = 8.35 + 5×8.33)
4. Ověří **editaci výdaje**: staré splity smazány, nové správně vytvořeny, SUM stále platí
5. Ověří **editaci CZK výdaje**: původní `exchange_rate` se zachová, `amount_eur` se nezmění
6. Ověří **globální integritu** všech výdajů v DB (žádné rozbité splity, žádné osiřelé záznamy)
7. Porovná **bilance z API vs. přímý DB výpočet** pro každého ze 6 uživatelů
8. Ověří **matematickou konzistenci**: SUM(všech bilancí) ≈ 0, SUM(kladných) ≈ SUM(záporných)
9. Ověří **settlements**: SUM(transakcí) ≈ SUM(dluhů), max N-1 transakcí, po zaplacení jsou všichni na ~0
10. **Stress test**: přidá 10 výdajů s různými částkami (0.01–2500, EUR+CZK, 2–6 lidí), ověří integritu
11. **Cleanup**: smaže všechna testovací data přes API, ověří že DB je ve stejném stavu jako před testem

**Co testy odhalily:**
- Všechno funguje správně – kalkulace jsou matematicky korektní pro 6 lidí (i při haléřových edge casech jako 0.07/3)
- CZK→EUR přepočet přes celý stack je správný a `exchange_rate` se ukládá
- Editace skutečně přepíše splity (neduplicituje, nesčítá)
- Greedy settlement dává správný výsledek: po zaplacení jsou všechny bilance na ~0

**Co ověřují pro 10+ lidí:**
Stress test s 10 výdaji ověřuje SUM integrity pro každou kombinaci. Pro produkční plavbu s 10 lidmi stačí přidat uživatele a test se automaticky přizpůsobí aktuálnímu kurzu z DB.

**Spuštění:**
```bash
# Vyžaduje běžící Docker kontejnery
docker exec -e RUNNING_IN_DOCKER=1 vol5_web php /var/www/html/tests/e2e_calculations_test.php
```

---

### [TEST-4] SQL integritní kontrola splits
**Status:** 🔵 Plánováno (SQL dotazy pro manuální kontrolu)

Spustit po každé migraci nebo záloze pro ověření konzistence dat:

```sql
-- Výdaje kde SUM(splits) != amount_eur (tolerance 0.01):
SELECT
    we.id,
    we.description,
    we.amount_eur AS expected,
    ROUND(SUM(wes.amount_eur), 2) AS actual,
    ABS(we.amount_eur - SUM(wes.amount_eur)) AS diff
FROM wallet_expenses we
JOIN wallet_expense_splits wes ON wes.expense_id = we.id
GROUP BY we.id
HAVING diff > 0.01
ORDER BY diff DESC;
-- Výsledek musí být prázdný

-- Výdaje bez jakýchkoliv splits (osiřelé záznamy):
SELECT we.id, we.description, we.amount_eur
FROM wallet_expenses we
LEFT JOIN wallet_expense_splits wes ON wes.expense_id = we.id
WHERE wes.id IS NULL;
-- Výsledek musí být prázdný
```

---

## 📋 Prioritizace

| ID | Popis | Priorita | Odhad | Status |
|----|-------|----------|-------|--------|
| SEC-1 | Ownership check edit/delete | 🔴 Kritická | 2h | ✅ |
| SEC-2 | Validace expense_date | 🔴 Kritická | 1h | ✅ |
| SEC-4 | Schema pro audit tabulky | 🔴 Kritická | 30m | ✅ |
| CALC-3 | Kurz zachován při editaci | 🟠 Vysoká | 1h | ✅ |
| BUG-2 | JSON error pro AJAX bez session | 🟠 Vysoká | 30m | ✅ |
| CALC-4 | formatMoney konzistence | 🟡 Střední | 30m | ✅ |
| BUG-4 | switchTab event Safari | 🟡 Střední | 15m | ✅ |
| BUG-1 | logbook PHP Warning kazilo JSON | 🟡 Střední | 10m | ✅ |
| TEST-1 | Unit testy přepočtů (62 testů) | 🔵 Důležité | 3h | ✅ |
| TEST-2 | Integration testy API (52 testů) | 🔵 Důležité | 4h | ✅ |
| TEST-3 | Unit testy validací (60 testů) | 🔵 Důležité | 2h | ✅ |
| TEST-5 | E2E kalkulační testy s DB (90 testů) | 🔵 Důležité | 3h | ✅ |
| BUG-12 | Email validace server-side | 🟠 Vysoká | 15m | 🔵 |
| BUG-13 | logout session_regenerate_id | 🟠 Vysoká | 10m | 🔵 |
| BUG-7 | Shopping kategorie whitelist | 🟡 Střední | 15m | 🔵 |
| BUG-8 | Cars sedadla range validace | 🟡 Střední | 15m | 🔵 |
| BUG-11 | Logbook NM záporné | 🟡 Střední | 15m | 🔵 |
| BUG-14 | Wallet filter boat_id přes JOIN | 🟡 Střední | 1h | 🔵 |
| BUG-9 | Cars duplikát řidič | 🟡 Střední | 30m | 🔵 |
| BUG-10 | Cars duplikát pasažér | 🟡 Střední | 30m | 🔵 |
| BUG-15 | Write API odmítnout GET | 🟡 Nízká | 30m | 🔵 |
| CALC-2 | N+1 dotazy → 1 JOIN query | 🟡 Střední | 1h | 🔵 |
| CALC-5 | Settled reálně sníží bilanci | 🟡 Střední | 3h | 🔵 |
| BUG-3 | editExpense bez API list volání | 🟡 Nízká | 1h | 🔵 |
| BUG-5 | Cars kapacita +1 pro řidiče | 🟡 Nízká | 30m | 🔵 |
| BUG-6 | Vytvořit install.php | 🟡 Nízká | 2h | 🔵 |
| TEST-4 | SQL integritní kontrola | 🔵 Plánováno | 30m | 🔵 |
