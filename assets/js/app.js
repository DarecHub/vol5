/**
 * Sailing App – Hlavní JavaScript
 * Sdílené funkce pro celou aplikaci
 */

// ============================================================
// CSRF TOKEN
// ============================================================

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// ============================================================
// AJAX HELPER
// ============================================================

async function apiCall(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    };

    if (data && method !== 'GET') {
        if (data instanceof FormData) {
            data.append('csrf_token', getCsrfToken());
            options.body = data;
        } else {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            const params = new URLSearchParams(data);
            params.append('csrf_token', getCsrfToken());
            options.body = params;
        }
    }

    try {
        const response = await fetch(url, options);
        const json = await response.json();
        return json;
    } catch (error) {
        console.error('API chyba:', error);
        return { success: false, error: 'Chyba připojení k serveru.' };
    }
}

// ============================================================
// TOAST NOTIFIKACE
// ============================================================

function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = '<span>' + escapeHtml(message) + '</span><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ============================================================
// MODALY
// ============================================================

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Zavření modalu klikem na overlay
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Zavření modalu/menu klávesou Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
        });
        document.body.style.overflow = '';
        const menu = document.getElementById('mobileMenu');
        if (menu && menu.classList.contains('open')) toggleMobileMenu();
        const dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('open');
    }
});

// ============================================================
// USER DROPDOWN (desktop popup)
// ============================================================

function toggleUserDropdown() {
    const dd = document.getElementById('userDropdown');
    if (!dd) return;
    dd.classList.toggle('open');
}

// Zavřít dropdown klikem mimo
document.addEventListener('click', function(e) {
    const dd = document.getElementById('userDropdown');
    const btn = document.getElementById('userDropdownToggle');
    if (dd && dd.classList.contains('open') && btn && !btn.contains(e.target) && !dd.contains(e.target)) {
        dd.classList.remove('open');
    }
});

// ============================================================
// MOBILNÍ MENU
// ============================================================

function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (!menu) return;
    const isOpen = menu.classList.contains('open');
    if (isOpen) {
        menu.classList.remove('open');
    } else {
        menu.classList.add('open');
    }
}

// Swipe dolů zavře mobilní menu
document.addEventListener('DOMContentLoaded', function() {
    const menu = document.getElementById('mobileMenu');
    const content = menu && menu.querySelector('.mobile-menu-content');
    if (!content) return;
    let startY = 0;
    content.addEventListener('touchstart', e => { startY = e.touches[0].clientY; }, { passive: true });
    content.addEventListener('touchend', e => {
        if (e.changedTouches[0].clientY - startY > 60) toggleMobileMenu();
    }, { passive: true });
});

// ============================================================
// TABY
// ============================================================

function switchTab(tabGroup, tabId) {
    // Deaktivovat všechny taby ve skupině
    document.querySelectorAll('[data-tab-group="' + tabGroup + '"]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('[data-tab-panel="' + tabGroup + '"]').forEach(panel => {
        panel.classList.remove('active');
    });

    // Aktivovat vybraný
    const btn = document.querySelector('[data-tab-group="' + tabGroup + '"][data-tab-id="' + tabId + '"]');
    const panel = document.getElementById(tabId);
    if (btn) btn.classList.add('active');
    if (panel) panel.classList.add('active');
}

// ============================================================
// LOADING STAVY
// ============================================================

function showLoading(element) {
    if (!element) return;
    element.style.position = 'relative';
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner spinner-lg"></div>';
    element.appendChild(overlay);
}

function hideLoading(element) {
    if (!element) return;
    const overlay = element.querySelector('.loading-overlay');
    if (overlay) overlay.remove();
}

// ============================================================
// POMOCNÉ FUNKCE
// ============================================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getInitials(name) {
    const parts = (name || '').trim().split(' ');
    let i = parts[0].charAt(0).toUpperCase();
    if (parts.length > 1) i += parts[parts.length - 1].charAt(0).toUpperCase();
    return i;
}

function formatMoney(amount, currency) {
    currency = currency || 'EUR';
    return parseFloat(amount).toFixed(2).replace('.', ',') + ' ' + currency;
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear();
}

/**
 * Potvrzovací dialog – stylizovaný modal místo browser confirm()
 * @param {string} message - Zpráva k zobrazení
 * @param {function} onConfirm - Callback při potvrzení
 */
function confirmAction(message, onConfirm) {
    const modal = document.getElementById('confirmModal');
    if (!modal) {
        // Fallback pokud modal neexistuje
        if (confirm(message)) onConfirm();
        return;
    }
    document.getElementById('confirmModalMessage').textContent = message;
    const confirmBtn = document.getElementById('confirmModalOk');
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
    newBtn.addEventListener('click', function() {
        closeModal('confirmModal');
        onConfirm();
    });
    openModal('confirmModal');
}

// ============================================================
// THEME TOGGLE (dark/light mode)
// ============================================================

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    updateThemeIcon();
}

function updateThemeIcon() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    // Desktop dropdown icon
    const icon = document.getElementById('themeToggleIcon');
    if (icon) icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
    const label = document.getElementById('themeToggleLabel');
    if (label) label.textContent = isDark ? 'Světlý režim' : 'Tmavý režim';
    // Drawer icon
    const iconDrawer = document.getElementById('themeToggleIconDrawer');
    if (iconDrawer) iconDrawer.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
    const labelDrawer = document.getElementById('themeToggleLabelDrawer');
    if (labelDrawer) labelDrawer.textContent = isDark ? 'Světlý režim' : 'Tmavý režim';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Initialize theme icon on page load
document.addEventListener('DOMContentLoaded', function() {
    updateThemeIcon();
});

/**
 * Odpočet (countdown) – pro dashboard
 */
function initCountdown(targetDate, elementId) {
    const el = document.getElementById(elementId);
    if (!el || !targetDate) return;

    function update() {
        const now = new Date().getTime();
        const target = new Date(targetDate).getTime();
        const diff = target - now;

        if (diff <= 0) {
            el.innerHTML = '<div class="countdown-title">Plavba již začala!</div>';
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        el.querySelector('.cd-days').textContent = days;
        el.querySelector('.cd-hours').textContent = hours;
        el.querySelector('.cd-minutes').textContent = minutes;
        el.querySelector('.cd-seconds').textContent = seconds;
    }

    update();
    setInterval(update, 1000);
}

// ============================================================
// SCROLL FADE INDICATORS
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.wallet-stats-row').forEach(function(row) {
        row.addEventListener('scroll', function() {
            const atEnd = row.scrollLeft + row.clientWidth >= row.scrollWidth - 8;
            row.classList.toggle('scrolled-end', atEnd);
        }, { passive: true });
    });
});
