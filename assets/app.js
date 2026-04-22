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
    const projectEl = document.getElementById('sd-project');
    const noteEl = document.getElementById('sd-note');
    const saveBtn = document.getElementById('sd-save');
    const cancelBtn = document.getElementById('sd-cancel');

    projects.forEach((p) => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        opt.dataset.color = p.color;
        projectEl.appendChild(opt);
    });

    let currentBtn = null;

    function openDialog(btn) {
        currentBtn = btn;
        dateEl.textContent = btn.dataset.date;
        periodEl.textContent = btn.dataset.period === 'AM' ? 'Matin' : 'Après-midi';
        const pid = btn.dataset.projectId;
        projectEl.value = (pid && pid !== '0') ? pid : '';
        noteEl.value = btn.dataset.note || '';
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        setTimeout(() => projectEl.focus(), 50);
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

    dialog.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            saveBtn.click();
        }
    });

    saveBtn.addEventListener('click', async () => {
        if (!currentBtn) return;
        const payload = {
            date: currentBtn.dataset.date,
            period: currentBtn.dataset.period,
            project_id: projectEl.value ? parseInt(projectEl.value, 10) : null,
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
})();
