(function () {
    const cal = document.querySelector('.calendar');
    if (!cal) return;

    let projects = [];
    try {
        projects = JSON.parse(cal.dataset.projects || '[]');
    } catch (e) {
        console.error('Projects parse error', e);
    }

    const dialog = document.getElementById('slot-dialog');
    if (!dialog) return;

    const dateEl = document.getElementById('sd-date');
    const periodEl = document.getElementById('sd-period');
    const grid = document.getElementById('sd-project-grid');
    const noteEl = document.getElementById('sd-note');
    const saveBtn = document.getElementById('sd-save');
    const cancelBtn = document.getElementById('sd-cancel');

    const PERIOD_LABELS = {
        AM: 'Matin', PM: 'Après-midi', EV: 'Soir', NT: 'Nuit',
    };

    let selectedProjectId = null;
    let currentBtn = null;

    // Construire les pastilles projets une fois
    function buildProjectPills() {
        grid.innerHTML = '';
        projects.forEach((p) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'project-pill';
            pill.setAttribute('role', 'radio');
            pill.setAttribute('aria-checked', 'false');
            pill.dataset.id = String(p.id);
            pill.style.setProperty('--p-color', p.color);
            pill.innerHTML = '<span class="pill-dot"></span><span class="pill-name"></span>';
            pill.querySelector('.pill-name').textContent = p.name;
            grid.appendChild(pill);
        });
        const clearPill = document.createElement('button');
        clearPill.type = 'button';
        clearPill.className = 'project-pill clear-pill';
        clearPill.setAttribute('role', 'radio');
        clearPill.setAttribute('aria-checked', 'false');
        clearPill.dataset.id = '';
        clearPill.innerHTML = '<span class="pill-name">Effacer</span>';
        grid.appendChild(clearPill);
    }
    buildProjectPills();

    function selectPill(id) {
        selectedProjectId = (id === '' || id === null) ? null : parseInt(id, 10);
        grid.querySelectorAll('.project-pill').forEach((pill) => {
            const match = pill.dataset.id === String(id ?? '');
            pill.setAttribute('aria-checked', match ? 'true' : 'false');
        });
    }

    grid.addEventListener('click', (e) => {
        const pill = e.target.closest('.project-pill');
        if (!pill) return;
        selectPill(pill.dataset.id);
    });

    function openDialog(btn) {
        currentBtn = btn;
        const dateStr = btn.dataset.date;
        try {
            const d = new Date(dateStr + 'T00:00:00');
            dateEl.textContent = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
        } catch (err) {
            dateEl.textContent = dateStr;
        }
        periodEl.textContent = PERIOD_LABELS[btn.dataset.period] || btn.dataset.period;
        const pid = btn.dataset.projectId;
        selectPill((pid && pid !== '0') ? pid : '');
        noteEl.value = btn.dataset.note || '';
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        // Scroll selected pill into view
        const selected = grid.querySelector('.project-pill[aria-checked="true"]');
        if (selected) selected.scrollIntoView({ block: 'nearest' });
    }

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    cal.addEventListener('click', (e) => {
        const btn = e.target.closest('.cal-slot');
        if (!btn) return;
        openDialog(btn);
    });

    cancelBtn.addEventListener('click', closeDialog);

    // Swipe down to dismiss (bottom sheet pattern)
    let touchStartY = 0;
    dialog.addEventListener('touchstart', (e) => {
        if (window.matchMedia('(max-width: 640px)').matches) {
            touchStartY = e.touches[0].clientY;
        }
    }, { passive: true });
    dialog.addEventListener('touchend', (e) => {
        if (!window.matchMedia('(max-width: 640px)').matches) return;
        const dy = e.changedTouches[0].clientY - touchStartY;
        if (dy > 80 && dialog.scrollTop === 0) closeDialog();
    }, { passive: true });

    saveBtn.addEventListener('click', async () => {
        if (!currentBtn) return;
        const payload = {
            date: currentBtn.dataset.date,
            period: currentBtn.dataset.period,
            project_id: selectedProjectId,
            note: noteEl.value.trim(),
        };
        saveBtn.disabled = true;
        try {
            const r = await fetch('index.php?action=api_save_entry', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.error || ('Erreur HTTP ' + r.status));

            const slotText = currentBtn.querySelector('.slot-project');
            if (payload.project_id === null) {
                currentBtn.classList.remove('filled');
                currentBtn.style.background = '';
                currentBtn.dataset.projectId = '0';
                currentBtn.dataset.note = '';
                currentBtn.title = '';
                slotText.textContent = '—';
            } else {
                const p = projects.find((pp) => pp.id === payload.project_id);
                if (p) {
                    currentBtn.classList.add('filled');
                    currentBtn.style.background = p.color;
                    currentBtn.dataset.projectId = String(p.id);
                    currentBtn.dataset.note = payload.note;
                    currentBtn.title = p.name + (payload.note ? ' — ' + payload.note : '');
                    slotText.textContent = p.name;
                }
            }
            closeDialog();
        } catch (err) {
            alert('Erreur : ' + err.message);
        } finally {
            saveBtn.disabled = false;
        }
    });

    // Auto-scroll vers "aujourd'hui" sur mobile
    if (window.matchMedia('(max-width: 640px)').matches) {
        const todayCell = document.querySelector('.cal-cell.today');
        if (todayCell) {
            setTimeout(() => todayCell.scrollIntoView({ behavior: 'auto', block: 'start' }), 0);
        }
    }
})();
