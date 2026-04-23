/* LBTimeTracker — vanilla JS
   - Setup wizard stepper (setup.php)
   - Calendar drag-to-fill + edit sheet (calendar.php)
   - Project color picker swatches + auto-save name on blur (projects.php)
*/

(function () {
    'use strict';

    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
    function authHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN };
    }

    // ===== Logout confirmation =====
    document.querySelectorAll('form[data-confirm-logout]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm('Se déconnecter maintenant ?')) {
                e.preventDefault();
            }
        });
    });

    // ===== Setup wizard =====
    (function initSetup() {
        const form = document.querySelector('[data-setup-wizard]');
        if (!form) return;
        const steps = form.querySelectorAll('[data-step]');
        const progress = form.querySelectorAll('[data-progress]');
        const prevBtn = form.querySelector('[data-setup-prev]');
        const nextBtn = form.querySelector('[data-setup-next]');
        const submitBtn = form.querySelector('[data-setup-submit]');
        const stepNum = form.querySelector('[data-step-num]');
        let cur = 0;
        const total = steps.length;

        function validateStep(i) {
            const step = steps[i];
            if (!step) return true;
            const fields = step.querySelectorAll('input[required]');
            for (const f of fields) {
                if (!f.reportValidity()) return false;
            }
            if (i === 0) {
                const p = form.querySelector('[name="password"]').value;
                const p2 = form.querySelector('[name="password2"]').value;
                if (p !== p2) {
                    alert('Les mots de passe ne correspondent pas.');
                    return false;
                }
            }
            return true;
        }

        function show(i) {
            cur = Math.max(0, Math.min(total - 1, i));
            steps.forEach((s, idx) => s.classList.toggle('active', idx === cur));
            progress.forEach((p, idx) => p.classList.toggle('done', idx <= cur));
            if (prevBtn) prevBtn.hidden = cur === 0;
            if (nextBtn) nextBtn.hidden = cur === total - 1;
            if (submitBtn) submitBtn.hidden = cur !== total - 1;
            if (stepNum) stepNum.textContent = String(cur + 1);
        }

        if (prevBtn) prevBtn.addEventListener('click', () => show(cur - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => {
            if (validateStep(cur)) show(cur + 1);
        });
        show(0);
    })();

    // ===== Projects page: swatches + name auto-save on blur =====
    (function initProjects() {
        const newForm = document.querySelector('[data-new-project]');
        if (newForm) {
            const colorInput = newForm.querySelector('[data-new-project-color]');
            const swatches = newForm.querySelectorAll('.lbtt-color-swatch');
            swatches.forEach((sw) => {
                sw.addEventListener('click', () => {
                    swatches.forEach((s) => s.classList.remove('selected'));
                    sw.classList.add('selected');
                    colorInput.value = sw.dataset.color || '';
                });
            });
        }
        // Auto-save name on blur if changed
        document.querySelectorAll('[data-proj-name]').forEach((input) => {
            input.addEventListener('blur', () => {
                const initial = input.dataset.initial || '';
                const val = input.value.trim();
                if (val === '' || val === initial) {
                    input.value = initial;
                    return;
                }
                const form = input.closest('form');
                if (!form) return;
                // Submit with op=update
                const nameField = form.querySelector('input[name="name"]');
                if (nameField) nameField.value = val;
                const op = document.createElement('input');
                op.type = 'hidden'; op.name = 'op'; op.value = 'update';
                form.appendChild(op);
                form.submit();
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
                if (e.key === 'Escape') { input.value = input.dataset.initial || ''; input.blur(); }
            });
        });
    })();

    // ===== Calendar =====
    (function initCalendar() {
        const root = document.querySelector('[data-calendar]');
        if (!root) return;

        let projects = [];
        try { projects = JSON.parse(root.dataset.projects || '[]'); } catch (e) {}

        let slotMode = { mode: 'hd4', codes: ['AM','PM','EV','NT'], labels: {}, hours: {} };
        try {
            const sm = JSON.parse(root.dataset.slotMode || 'null');
            if (sm && sm.codes) slotMode = sm;
        } catch (e) {}
        const PERIOD_LABELS = slotMode.labels || {};
        const PERIOD_HOURS = slotMode.hours || {};

        const overlay = document.getElementById('lbtt-sheet-overlay');
        if (!overlay) return;

        const eyebrow = document.getElementById('lbtt-sheet-eyebrow');
        const title = document.getElementById('lbtt-sheet-title');
        const grid = document.getElementById('lbtt-sheet-projects');
        const noteWrap = document.getElementById('lbtt-sheet-note-wrap');
        const noteInput = document.getElementById('lbtt-sheet-note');
        const closeBtn = document.getElementById('lbtt-sheet-close');

        const MONTHS = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

        let current = null; // { ymd, slot }

        function fmtDate(ymd) {
            const [y, m, d] = ymd.split('-').map(Number);
            return `${d} ${MONTHS[m - 1]} ${y}`;
        }

        function renderSheet(target) {
            current = target;
            const label = PERIOD_LABELS[target.slot] || target.slot;
            const hours = PERIOD_HOURS[target.slot] || '';
            eyebrow.textContent = hours ? `${label} · ${hours}` : label;
            title.textContent = fmtDate(target.ymd);
            noteWrap.hidden = false;
            const slotEl = document.querySelector(`[data-slot][data-ymd="${target.ymd}"][data-sk="${target.slot}"]`);
            noteInput.value = slotEl ? (slotEl.dataset.note || '') : '';
            const currentProjectId = slotEl ? (slotEl.dataset.projectId || '0') : '0';
            // Build project grid
            grid.innerHTML = '';
            projects.forEach((p) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'lbtt-sheet-project-btn';
                btn.dataset.id = String(p.id);
                if (String(p.id) === currentProjectId) btn.classList.add('active');
                btn.innerHTML = '<span class="sw"></span><span class="nm"></span>';
                btn.querySelector('.sw').style.background = p.color;
                btn.querySelector('.nm').textContent = p.name;
                btn.addEventListener('click', () => pick(p.id));
                grid.appendChild(btn);
            });
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'lbtt-sheet-clear';
            clearBtn.textContent = '× Effacer';
            clearBtn.addEventListener('click', () => pick(null));
            grid.appendChild(clearBtn);

            overlay.hidden = false;
        }

        function closeSheet() {
            overlay.hidden = true;
            current = null;
        }
        closeBtn.addEventListener('click', closeSheet);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeSheet(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !overlay.hidden) closeSheet(); });

        async function pick(projectId) {
            if (!current) return;
            const { ymd, slot } = current;
            const note = noteInput.value.trim() ? noteInput.value.trim() : null;
            try {
                const r = await fetch('index.php?action=api_save_entry', {
                    method: 'POST',
                    headers: authHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        date: ymd,
                        period: slot,
                        project_id: projectId,
                        note: note,
                    }),
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.error || ('Erreur HTTP ' + r.status));
                const p = projectId !== null ? projects.find((pp) => pp.id === projectId) : null;
                document.querySelectorAll(`[data-slot][data-ymd="${ymd}"][data-sk="${slot}"]`).forEach((el) => applyEntry(el, p, note));
                closeSheet();
            } catch (err) {
                alert('Erreur : ' + err.message);
            }
        }

        function applyEntry(slotEl, project, note) {
            if (!project) {
                // Clear
                slotEl.classList.remove('filled');
                slotEl.style.background = '';
                slotEl.dataset.projectId = '0';
                slotEl.dataset.note = '';
                const nm = slotEl.querySelector('.nm');
                if (nm) nm.textContent = '—';
                slotEl.setAttribute('title', '');
                return;
            }
            slotEl.classList.add('filled');
            slotEl.style.background = project.color;
            slotEl.dataset.projectId = String(project.id);
            slotEl.dataset.note = note || '';
            const nm = slotEl.querySelector('.nm');
            if (nm) nm.textContent = project.name;
            slotEl.setAttribute('title', project.name + (note ? ' — ' + note : ''));
        }

        root.addEventListener('click', (e) => {
            const slotEl = e.target.closest('[data-slot]');
            if (!slotEl) return;
            e.preventDefault();
            renderSheet({ ymd: slotEl.dataset.ymd, slot: slotEl.dataset.sk });
        });
    })();

    // ===== Projects — toggle "Gérer" panel per card =====
    document.querySelectorAll('[data-toggle-project-manage]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const card = btn.closest('[data-project-card]');
            if (!card) return;
            const panel = card.querySelector('[data-project-manage]');
            if (!panel) return;
            const isOpen = !panel.hasAttribute('hidden');
            if (isOpen) panel.setAttribute('hidden', '');
            else panel.removeAttribute('hidden');
        });
    });
})();
