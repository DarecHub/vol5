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
            <a href="/pages/dashboard.php" class="top-bar-logo">
                <img src="/assets/img/logo.png" alt="Logo" width="20" height="20" style="width:20px!important;height:20px!important;object-fit:contain;flex-shrink:0;vertical-align:middle;">
                <?= e($tripName) ?>
            </a>
        </div>
        <div class="top-bar-right">
            <span class="top-bar-user">
                <?= e($userName) ?>
                <?php if ($boatName): ?>
                    <span class="top-bar-boat"><?= e($boatName) ?></span>
                <?php endif; ?>
            </span>
            <a href="/logout.php" class="top-bar-logout" title="Odhlásit se">
                <i data-lucide="log-out" style="width:18px;height:18px;"></i>
            </a>
        </div>
    </header>

    <!-- Boční menu (desktop) -->
    <nav class="sidebar" id="sidebar">
        <a href="/pages/dashboard.php" class="sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="home" class="sidebar-icon"></i> Domů
        </a>
        <a href="/pages/itinerary.php" class="sidebar-link <?= $activePage === 'itinerary' ? 'active' : '' ?>">
            <i data-lucide="map" class="sidebar-icon"></i> Itinerář
        </a>
        <a href="/pages/crews.php" class="sidebar-link <?= $activePage === 'crews' ? 'active' : '' ?>">
            <i data-lucide="users" class="sidebar-icon"></i> Posádky
        </a>
        <a href="/pages/shopping.php" class="sidebar-link <?= $activePage === 'shopping' ? 'active' : '' ?>">
            <i data-lucide="shopping-cart" class="sidebar-icon"></i> Nákupy
        </a>
        <a href="/pages/menu.php" class="sidebar-link <?= $activePage === 'menu' ? 'active' : '' ?>">
            <i data-lucide="utensils" class="sidebar-icon"></i> Jídelníček
        </a>
        <a href="/pages/wallet.php" class="sidebar-link <?= $activePage === 'wallet' ? 'active' : '' ?>">
            <i data-lucide="wallet" class="sidebar-icon"></i> Pokladna
        </a>
        <a href="/pages/checklist.php" class="sidebar-link <?= $activePage === 'checklist' ? 'active' : '' ?>">
            <i data-lucide="check-square" class="sidebar-icon"></i> Co s sebou
        </a>
        <a href="/pages/logbook.php" class="sidebar-link <?= $activePage === 'logbook' ? 'active' : '' ?>">
            <i data-lucide="book-open" class="sidebar-icon"></i> Deník
        </a>
        <a href="/pages/cars.php" class="sidebar-link <?= $activePage === 'cars' ? 'active' : '' ?>">
            <i data-lucide="car" class="sidebar-icon"></i> Auta
        </a>
        <?php if ($isAdm): ?>
            <hr class="sidebar-divider">
            <a href="/admin/index.php" class="sidebar-link <?= $activePage === 'admin' ? 'active' : '' ?>">
                <i data-lucide="settings" class="sidebar-icon"></i> Admin
            </a>
        <?php endif; ?>
    </nav>

    <!-- Spodní mobilní navigace – 5 položek, žádné "Více" tlačítko -->
    <nav class="bottom-nav">
        <a href="/pages/dashboard.php" class="bottom-nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="home" class="bottom-nav-icon"></i>
            <span class="bottom-nav-label">Domů</span>
        </a>
        <a href="/pages/wallet.php" class="bottom-nav-item <?= $activePage === 'wallet' ? 'active' : '' ?>">
            <i data-lucide="wallet" class="bottom-nav-icon"></i>
            <span class="bottom-nav-label">Pokladna</span>
        </a>
        <a href="/pages/shopping.php" class="bottom-nav-item <?= $activePage === 'shopping' ? 'active' : '' ?>">
            <i data-lucide="shopping-cart" class="bottom-nav-icon"></i>
            <span class="bottom-nav-label">Nákupy</span>
        </a>
        <a href="/pages/logbook.php" class="bottom-nav-item <?= $activePage === 'logbook' ? 'active' : '' ?>">
            <i data-lucide="book-open" class="bottom-nav-icon"></i>
            <span class="bottom-nav-label">Deník</span>
        </a>
        <button class="bottom-nav-item <?= in_array($activePage, ['itinerary','crews','menu','checklist','cars','admin']) ? 'active' : '' ?>" onclick="toggleMobileMenu()" id="menuToggle" aria-label="Více">
            <i data-lucide="grid" class="bottom-nav-icon"></i>
            <span class="bottom-nav-label">Více</span>
        </button>
    </nav>

    <!-- Mobilní drawer – slide-up bottom sheet -->
    <div class="mobile-menu" id="mobileMenu" role="dialog" aria-modal="true" aria-label="Navigace">
        <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
        <div class="mobile-menu-content">
            <div class="mobile-menu-handle"></div>
            <div class="mobile-menu-grid">
                <a href="/pages/itinerary.php" class="mobile-menu-tile <?= $activePage === 'itinerary' ? 'active' : '' ?>">
                    <i data-lucide="map" class="mobile-menu-tile-icon"></i>
                    <span>Itinerář</span>
                </a>
                <a href="/pages/crews.php" class="mobile-menu-tile <?= $activePage === 'crews' ? 'active' : '' ?>">
                    <i data-lucide="users" class="mobile-menu-tile-icon"></i>
                    <span>Posádky</span>
                </a>
                <a href="/pages/menu.php" class="mobile-menu-tile <?= $activePage === 'menu' ? 'active' : '' ?>">
                    <i data-lucide="utensils" class="mobile-menu-tile-icon"></i>
                    <span>Jídelníček</span>
                </a>
                <a href="/pages/checklist.php" class="mobile-menu-tile <?= $activePage === 'checklist' ? 'active' : '' ?>">
                    <i data-lucide="check-square" class="mobile-menu-tile-icon"></i>
                    <span>Co s sebou</span>
                </a>
                <a href="/pages/cars.php" class="mobile-menu-tile <?= $activePage === 'cars' ? 'active' : '' ?>">
                    <i data-lucide="car" class="mobile-menu-tile-icon"></i>
                    <span>Auta</span>
                </a>
                <?php if ($isAdm): ?>
                <a href="/admin/index.php" class="mobile-menu-tile <?= $activePage === 'admin' ? 'active' : '' ?>">
                    <i data-lucide="settings" class="mobile-menu-tile-icon"></i>
                    <span>Admin</span>
                </a>
                <?php endif; ?>
            </div>
            <div class="mobile-menu-footer">
                <span class="mobile-menu-user">
                    <i data-lucide="user" style="width:14px;height:14px;"></i>
                    <?= e($userName) ?>
                    <?php if ($boatName): ?>
                        <span class="top-bar-boat" style="margin-left:4px;"><?= e($boatName) ?></span>
                    <?php endif; ?>
                </span>
                <a href="/logout.php" class="mobile-menu-logout">
                    <i data-lucide="log-out" style="width:14px;height:14px;"></i> Odhlásit
                </a>
            </div>
        </div>
    </div>

    <!-- Hlavní obsah -->
    <main class="main-content">
        <?= renderFlashMessages() ?>
        <meta name="csrf-token" content="<?= e($csrf) ?>">
