    </main>

    <!-- Toast notifikace kontejner -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Confirm dialog – globální -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h3 class="modal-title">Potvrzení</h3>
                <button class="modal-close" onclick="closeModal('confirmModal')">&times;</button>
            </div>
            <div class="modal-body" style="text-align:center;padding:24px 20px;">
                <i data-lucide="alert-triangle" style="width:40px;height:40px;color:var(--color-warning);margin-bottom:12px;"></i>
                <p id="confirmModalMessage" style="font-size:0.95rem;color:var(--color-text);"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('confirmModal')">Zrušit</button>
                <button class="btn btn-danger" id="confirmModalOk">Potvrdit</button>
            </div>
        </div>
    </div>

    <!-- Member detail modal – globální, použitelný z celé appky -->
    <div class="modal-overlay" id="memberModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h3 class="modal-title" id="memberModalTitle">Detail člena</h3>
                <button class="modal-close" onclick="closeModal('memberModal')">&times;</button>
            </div>
            <div class="modal-body" id="memberModalBody" style="padding:0;">
                <div style="text-align:center;padding:32px 20px 24px;">
                    <div class="spinner spinner-lg" style="margin:0 auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>lucide.createIcons();</script>
    <script>
    // ============================================================
    // MEMBER DETAIL MODAL
    // ============================================================
    async function openMemberModal(userId) {
        openModal('memberModal');
        const body = document.getElementById('memberModalBody');
        body.innerHTML = '<div style="text-align:center;padding:32px 20px;"><div class="spinner spinner-lg" style="margin:0 auto;"></div></div>';

        const res = await apiCall('/api/user_detail.php?id=' + userId);
        if (!res.success) {
            body.innerHTML = '<p class="text-center text-muted" style="padding:24px;">Chyba načítání.</p>';
            return;
        }

        const u = res.data;
        document.getElementById('memberModalTitle').textContent = u.name;

        // Bezpečný fallback – nepotřebuje globální getInitials
        const _ini = (n) => { const p = (n||'').trim().split(' '); return (p[0][0]||'').toUpperCase() + (p.length>1?(p[p.length-1][0]||'').toUpperCase():''); };
        const avatarHtml = u.avatar_url
            ? `<img src="${escapeHtml(u.avatar_url)}" alt="${escapeHtml(u.name)}"
                   onclick="openPhotoFullscreen('${escapeHtml(u.avatar_url)}', '${escapeHtml(u.name)}')">`
            : `<span class="avatar-initials">${escapeHtml(_ini(u.name))}</span>`;

        const phoneHtml = u.phone
            ? `<a href="tel:${escapeHtml(u.phone)}" class="member-modal-row">
                   <span class="member-modal-icon phone">
                       <i data-lucide="phone" style="width:17px;height:17px;"></i>
                   </span>
                   <div>
                       <div class="member-modal-label">Telefon</div>
                       <div class="member-modal-value">${escapeHtml(u.phone)}</div>
                   </div>
                   <i data-lucide="chevron-right" style="width:16px;height:16px;color:var(--color-text-tertiary);margin-left:auto;"></i>
               </a>` : '';

        const emailHtml = u.email
            ? `<a href="mailto:${escapeHtml(u.email)}" class="member-modal-row">
                   <span class="member-modal-icon email">
                       <i data-lucide="mail" style="width:17px;height:17px;"></i>
                   </span>
                   <div>
                       <div class="member-modal-label">Email</div>
                       <div class="member-modal-value">${escapeHtml(u.email)}</div>
                   </div>
                   <i data-lucide="chevron-right" style="width:16px;height:16px;color:var(--color-text-tertiary);margin-left:auto;"></i>
               </a>` : '';

        const boatHtml = u.boat_name
            ? `<div class="member-modal-row">
                   <span class="member-modal-icon boat">
                       <i data-lucide="sailboat" style="width:17px;height:17px;"></i>
                   </span>
                   <div>
                       <div class="member-modal-label">Loď</div>
                       <div class="member-modal-value">${escapeHtml(u.boat_name)}</div>
                   </div>
               </div>` : '';

        body.innerHTML = `
            <div class="member-modal-hero">
                ${avatarHtml}
                <div class="member-name">${escapeHtml(u.name)}</div>
            </div>
            <div>
                ${phoneHtml}
                ${emailHtml}
                ${boatHtml}
            </div>`;

        lucide.createIcons();
    }

    function openPhotoFullscreen(src, name) {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:zoom-out;';
        overlay.onclick = () => overlay.remove();
        overlay.innerHTML = `
            <img src="${escapeHtml(src)}" alt="${escapeHtml(name)}"
                 style="max-width:90vw;max-height:80vh;object-fit:contain;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,0.5);">
            <div style="color:white;margin-top:14px;font-size:1rem;font-weight:600;opacity:.85;">${escapeHtml(name)}</div>`;
        document.body.appendChild(overlay);
    }

    async function uploadAvatar(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('action', 'upload');
        formData.append('csrf_token', getCsrfToken());
        try {
            const res = await fetch('/api/avatar.php?action=upload', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            const json = await res.json();
            if (json.success) {
                const wrap = document.getElementById('drawerAvatarImg');
                if (wrap) {
                    const img = document.createElement('img');
                    img.src = json.data.avatar;
                    img.alt = 'Avatar';
                    img.className = 'avatar avatar-lg';
                    img.style.cssText = 'object-fit:cover;border:2px solid var(--color-border);';
                    wrap.innerHTML = '';
                    wrap.appendChild(img);
                }
                showToast('Profilový obrázek byl uložen.', 'success');
            } else {
                showToast(json.error || 'Chyba nahrávání.', 'error');
            }
        } catch (e) {
            showToast('Chyba připojení.', 'error');
        }
        input.value = '';
    }
    </script>
</body>
</html>
