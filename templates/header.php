<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($title) ?> – <?= e($tripName) ?></title>
    <link rel="icon" type="image/png" href="/assets/img/logo.png">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Horní lišta -->
    <header class="top-bar">
        <a href="/pages/dashboard.php" class="top-bar-logo">
            <img src="/assets/img/logo.png" alt="Logo" width="20" height="20">
            <span><?= e($tripName) ?></span>
        </a>
        <div class="top-bar-actions">
            <!-- Desktop: jméno + loď -->
            <span class="top-bar-user">
                <?= e($userName) ?>
                <?php if ($boatName): ?>
                    <span class="top-bar-boat"><?= e($boatName) ?></span>
                <?php endif; ?>
            </span>
            <!-- Desktop logout -->
            <a href="/logout.php" class="top-bar-btn top-bar-btn-desktop" title="Odhlásit se">
                <i data-lucide="log-out" style="width:20px;height:20px;"></i>
            </a>
            <!-- Hamburger – mobil i desktop -->
            <button class="top-bar-btn" onclick="toggleMobileMenu()" id="menuToggle" aria-label="Menu">
                <i data-lucide="menu" style="width:22px;height:22px;"></i>
            </button>
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

    <!-- Spodní mobilní navigace – 4 hlavní položky -->
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
    </nav>

    <!-- Drawer menu -->
    <div class="mobile-menu" id="mobileMenu" role="dialog" aria-modal="true" aria-label="Navigace">
        <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
        <div class="mobile-menu-content" style="box-shadow:-4px 0 24px rgba(0,0,0,0.15);">
            <!-- Hlavička draweru: avatar + jméno + upload -->
            <div class="drawer-header">
                <div class="drawer-header-user">
                    <!-- Avatar – kliknutím nahrát foto -->
                    <div class="drawer-avatar-wrap" onclick="document.getElementById('avatarFileInput').click()" title="Změnit profilový obrázek">
                        <?php
                        $meUser = ['name' => $userName, 'avatar' => $userAvatar ? ltrim($userAvatar, '/') : null];
                        ?>
                        <span id="drawerAvatarImg"><?= avatarHtml($meUser, 'lg', 'primary') ?></span>
                        <span class="drawer-avatar-edit">
                            <i data-lucide="camera" style="width:12px;height:12px;"></i>
                        </span>
                    </div>
                    <input type="file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp,image/gif"
                           style="display:none;" onchange="uploadAvatar(this)">
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;color:var(--gray-800);"><?= e($userName) ?></div>
                        <?php if ($boatName): ?>
                        <div style="font-size:0.75rem;color:var(--gray-500);display:flex;align-items:center;gap:4px;margin-top:2px;">
                            <i data-lucide="sailboat" style="width:11px;height:11px;"></i><?= e($boatName) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="drawer-close" onclick="toggleMobileMenu()" aria-label="Zavřít">
                    <i data-lucide="x" style="width:20px;height:20px;"></i>
                </button>
            </div>

            <!-- Všechny položky menu jako seznam -->
            <nav class="drawer-nav">
                <?php
                $allPages = [
                    ['href'=>'/pages/dashboard.php',  'icon'=>'home',         'label'=>'Domů',        'page'=>'dashboard'],
                    ['href'=>'/pages/wallet.php',      'icon'=>'wallet',       'label'=>'Pokladna',    'page'=>'wallet'],
                    ['href'=>'/pages/shopping.php',    'icon'=>'shopping-cart','label'=>'Nákupy',      'page'=>'shopping'],
                    ['href'=>'/pages/logbook.php',     'icon'=>'book-open',    'label'=>'Deník',       'page'=>'logbook'],
                    ['href'=>'/pages/itinerary.php',   'icon'=>'map',          'label'=>'Itinerář',    'page'=>'itinerary'],
                    ['href'=>'/pages/crews.php',       'icon'=>'users',        'label'=>'Posádky',     'page'=>'crews'],
                    ['href'=>'/pages/menu.php',        'icon'=>'utensils',     'label'=>'Jídelníček',  'page'=>'menu'],
                    ['href'=>'/pages/checklist.php',   'icon'=>'check-square', 'label'=>'Co s sebou',  'page'=>'checklist'],
                    ['href'=>'/pages/cars.php',        'icon'=>'car',          'label'=>'Auta',        'page'=>'cars'],
                ];
                if ($isAdm) {
                    $allPages[] = ['href'=>'/admin/index.php','icon'=>'settings','label'=>'Admin','page'=>'admin'];
                }
                foreach ($allPages as $p):
                    $isActive = $activePage === $p['page'];
                ?>
                <a href="<?= $p['href'] ?>" class="drawer-link <?= $isActive ? 'active' : '' ?>" onclick="toggleMobileMenu()">
                    <span class="drawer-link-icon">
                        <i data-lucide="<?= $p['icon'] ?>" style="width:18px;height:18px;"></i>
                    </span>
                    <span class="drawer-link-label"><?= $p['label'] ?></span>
                    <?php if ($isActive): ?>
                        <i data-lucide="chevron-right" style="width:15px;height:15px;color:var(--gray-300);margin-left:auto;"></i>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Logout dole -->
            <div class="drawer-footer">
                <a href="/logout.php" class="drawer-logout">
                    <i data-lucide="log-out" style="width:17px;height:17px;"></i>
                    Odhlásit se
                </a>
            </div>
        </div>
    </div>

    <!-- Hlavní obsah -->
    <main class="main-content">
        <?= renderFlashMessages() ?>
        <meta name="csrf-token" content="<?= e($csrf) ?>">
