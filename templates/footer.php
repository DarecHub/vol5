    </main>

    <!-- Toast notifikace kontejner -->
    <div id="toast-container" class="toast-container"></div>

    <script src="/assets/js/lucide.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>lucide.createIcons();</script>
    <script>
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
