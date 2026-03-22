    </main>

    <!-- Toast notifikace kontejner -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Member detail modal – globální, použitelný z celé appky -->
    <div class="modal-overlay" id="memberModal">
        <div class="modal" style="max-width:400px;">
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
                   style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--gray-200);cursor:zoom-in;"
                   onclick="openPhotoFullscreen('${escapeHtml(u.avatar_url)}', '${escapeHtml(u.name)}')">`
            : `<span style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;font-size:2rem;font-weight:700;display:inline-flex;align-items:center;justify-content:center;">
                   ${escapeHtml(_ini(u.name))}
               </span>`;

        const phoneHtml = u.phone
            ? `<a href="tel:${escapeHtml(u.phone)}" style="display:flex;align-items:center;gap:10px;padding:14px 20px;border-bottom:1px solid var(--gray-100);text-decoration:none;color:var(--gray-800);">
                   <span style="width:36px;height:36px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                       <i data-lucide="phone" style="width:17px;height:17px;color:#16a34a;"></i>
                   </span>
                   <div>
                       <div style="font-size:0.72rem;color:var(--gray-500);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Telefon</div>
                       <div style="font-weight:600;font-size:0.95rem;">${escapeHtml(u.phone)}</div>
                   </div>
                   <i data-lucide="chevron-right" style="width:16px;height:16px;color:var(--gray-300);margin-left:auto;"></i>
               </a>` : '';

        const emailHtml = u.email
            ? `<a href="mailto:${escapeHtml(u.email)}" style="display:flex;align-items:center;gap:10px;padding:14px 20px;border-bottom:1px solid var(--gray-100);text-decoration:none;color:var(--gray-800);">
                   <span style="width:36px;height:36px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                       <i data-lucide="mail" style="width:17px;height:17px;color:#2563eb;"></i>
                   </span>
                   <div>
                       <div style="font-size:0.72rem;color:var(--gray-500);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Email</div>
                       <div style="font-weight:600;font-size:0.95rem;">${escapeHtml(u.email)}</div>
                   </div>
                   <i data-lucide="chevron-right" style="width:16px;height:16px;color:var(--gray-300);margin-left:auto;"></i>
               </a>` : '';

        const boatHtml = u.boat_name
            ? `<div style="display:flex;align-items:center;gap:10px;padding:14px 20px;">
                   <span style="width:36px;height:36px;border-radius:10px;background:#fef9c3;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                       <i data-lucide="sailboat" style="width:17px;height:17px;color:#ca8a04;"></i>
                   </span>
                   <div>
                       <div style="font-size:0.72rem;color:var(--gray-500);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Loď</div>
                       <div style="font-weight:600;font-size:0.95rem;">${escapeHtml(u.boat_name)}</div>
                   </div>
               </div>` : '';

        body.innerHTML = `
            <div style="text-align:center;padding:28px 20px 20px;border-bottom:1px solid var(--gray-100);">
                ${avatarHtml}
                <div style="font-size:1.1rem;font-weight:700;color:var(--gray-800);margin-top:12px;">${escapeHtml(u.name)}</div>
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
                    img.style.cssText = 'object-fit:cover;border:2px solid var(--gray-200);';
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
