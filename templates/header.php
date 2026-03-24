<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($title) ?> – <?= e($tripName) ?></title>
    <link rel="icon" type="image/png" href="/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
    (function(){
        var t = localStorage.getItem('theme');
        if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/lucide.min.js"></script>
    <script src="/assets/js/app.js"></script>
</head>
<body>
    <!-- Horní lišta -->
    <header class="top-bar">
        <a href="/pages/dashboard.php" class="top-bar-logo">
            <img src="/assets/img/logo.png" alt="Logo" width="20" height="20">
            <span><?= e($tripName) ?></span>
        </a>
        <div class="top-bar-actions">
            <!-- Desktop: avatar s popup dropdownem -->
            <?php $topBarUser = ['name' => $userName, 'avatar' => $userAvatar ? ltrim($userAvatar, '/') : null]; ?>
            <div class="user-dropdown-wrap">
                <button class="top-bar-avatar-btn" onclick="toggleUserDropdown()" id="userDropdownToggle">
                    <?= avatarHtml($topBarUser, 'sm', 'primary') ?>
                    <span class="top-bar-user-name"><?= e($userName) ?></span>
                    <i data-lucide="chevron-down" style="width:14px;height:14px;opacity:0.6;"></i>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <div class="user-dropdown-avatar" onclick="document.getElementById('avatarFileDropdown').click()" title="Změnit profilový obrázek">
                            <?= avatarHtml($topBarUser, 'lg', 'primary') ?>
                            <span class="user-dropdown-avatar-edit">
                                <i data-lucide="camera" style="width:12px;height:12px;"></i>
                            </span>
                        </div>
                        <input type="file" id="avatarFileDropdown" accept="image/jpeg,image/png,image/webp,image/gif"
                               style="display:none;" onchange="uploadAvatar(this)">
                        <div class="user-dropdown-name"><?= e($userName) ?></div>
                        <?php if ($boatName): ?>
                        <div class="user-dropdown-boat">
                            <i data-lucide="sailboat" style="width:12px;height:12px;"></i> <?= e($boatName) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="user-dropdown-links">
                        <button class="user-dropdown-link" onclick="toggleTheme(); return false;">
                            <i data-lucide="moon" id="themeToggleIcon" style="width:16px;height:16px;"></i>
                            <span id="themeToggleLabel">Tmavý režim</span>
                        </button>
                        <a href="/logout.php" class="user-dropdown-link">
                            <i data-lucide="log-out" style="width:16px;height:16px;"></i> Odhlásit se
                        </a>
                    </div>
                </div>
            </div>
            <!-- Hamburger – jen mobil -->
            <button class="top-bar-btn top-bar-hamburger" onclick="toggleMobileMenu()" id="menuToggle" aria-label="Menu">
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
        <a href="#" class="bottom-nav-item" onclick="toggleMobileMenu(); return false;">
            <i data-lucide="grid-2x2" class="bottom-nav-icon"></i>
            <span class="bottom-nav-label">Více</span>
        </a>
    </nav>

    <!-- Drawer menu -->
    <div class="mobile-menu" id="mobileMenu" role="dialog" aria-modal="true" aria-label="Navigace">
        <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
        <div class="mobile-menu-content">
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
                        <div style="font-weight:700;font-size:0.95rem;color:var(--color-text);"><?= e($userName) ?></div>
                        <?php if ($boatName): ?>
                        <div style="font-size:0.75rem;color:var(--color-text-secondary);display:flex;align-items:center;gap:4px;margin-top:2px;">
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
                        <i data-lucide="chevron-right" style="width:15px;height:15px;color:var(--color-text-tertiary);margin-left:auto;"></i>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Theme toggle + Logout -->
            <div class="drawer-footer">
                <button class="drawer-logout" onclick="toggleTheme(); return false;" style="margin-bottom:12px;border:none;background:none;cursor:pointer;font-family:inherit;">
                    <i data-lucide="moon" id="themeToggleIconDrawer" style="width:17px;height:17px;"></i>
                    <span id="themeToggleLabelDrawer">Tmavý režim</span>
                </button>
                <br>
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
