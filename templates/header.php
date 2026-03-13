<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> – <?= e($tripName) ?></title>
    <link rel="icon" type="image/png" href="/assets/img/logo.png">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Horní lišta -->
    <header class="top-bar">
        <div class="top-bar-left">
            <a href="/pages/dashboard.php" class="top-bar-logo"><img src="/assets/img/logo.png" alt="Logo" width="20" height="20" style="width:20px!important;height:20px!important;object-fit:contain;flex-shrink:0;vertical-align:middle;"> <?= e($tripName) ?></a>
        </div>
        <div class="top-bar-right">
            <span class="top-bar-user">
                <?= e($userName) ?>
                <?php if ($boatName): ?>
                    <span class="top-bar-boat"><?= e($boatName) ?></span>
                <?php endif; ?>
            </span>
            <a href="/logout.php" class="top-bar-logout" title="Odhlásit se">&#10005;</a>
        </div>
    </header>

    <!-- Boční menu (desktop) -->
    <nav class="sidebar" id="sidebar">
        <a href="/pages/dashboard.php" class="sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#127968;</span> Domů
        </a>
        <a href="/pages/itinerary.php" class="sidebar-link <?= $activePage === 'itinerary' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#128506;</span> Itinerář
        </a>
        <a href="/pages/crews.php" class="sidebar-link <?= $activePage === 'crews' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#9973;</span> Posádky
        </a>
        <a href="/pages/shopping.php" class="sidebar-link <?= $activePage === 'shopping' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#128722;</span> Nákupy
        </a>
        <a href="/pages/menu.php" class="sidebar-link <?= $activePage === 'menu' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#127858;</span> Jídelníček
        </a>
        <a href="/pages/wallet.php" class="sidebar-link <?= $activePage === 'wallet' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#128176;</span> Pokladna
        </a>
        <a href="/pages/checklist.php" class="sidebar-link <?= $activePage === 'checklist' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#9989;</span> Co s sebou
        </a>
        <a href="/pages/logbook.php" class="sidebar-link <?= $activePage === 'logbook' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#128214;</span> Deník
        </a>
        <a href="/pages/cars.php" class="sidebar-link <?= $activePage === 'cars' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#128663;</span> Auta
        </a>
        <?php if ($isAdm): ?>
            <hr class="sidebar-divider">
            <a href="/admin/index.php" class="sidebar-link <?= $activePage === 'admin' ? 'active' : '' ?>">
                <span class="sidebar-icon">&#9881;</span> Admin
            </a>
        <?php endif; ?>
    </nav>

    <!-- Spodní mobilní navigace -->
    <nav class="bottom-nav">
        <a href="/pages/dashboard.php" class="bottom-nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">&#127968;</span>
            <span class="bottom-nav-label">Domů</span>
        </a>
        <a href="/pages/wallet.php" class="bottom-nav-item <?= $activePage === 'wallet' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">&#128176;</span>
            <span class="bottom-nav-label">Pokladna</span>
        </a>
        <button class="bottom-nav-item" onclick="toggleMobileMenu()" id="menuToggle">
            <span class="bottom-nav-icon">&#9776;</span>
            <span class="bottom-nav-label">Více</span>
        </button>
    </nav>

    <!-- Mobilní rozbalovací menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
        <div class="mobile-menu-content">
            <a href="/pages/itinerary.php" class="mobile-menu-link">&#128506; Itinerář</a>
            <a href="/pages/shopping.php" class="mobile-menu-link">&#128722; Nákupy</a>
            <a href="/pages/crews.php" class="mobile-menu-link">&#9973; Posádky</a>
            <a href="/pages/menu.php" class="mobile-menu-link">&#127858; Jídelníček</a>
            <a href="/pages/checklist.php" class="mobile-menu-link">&#9989; Co s sebou</a>
            <a href="/pages/logbook.php" class="mobile-menu-link">&#128214; Deník plavby</a>
            <a href="/pages/cars.php" class="mobile-menu-link">&#128663; Auta</a>
        </div>
    </div>

    <!-- Hlavní obsah -->
    <main class="main-content">
        <?= renderFlashMessages() ?>
        <meta name="csrf-token" content="<?= e($csrf) ?>">
