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

        const overlay = document.getElementById('lbtt-sheet-overlay');
        if (!overlay) return;

        const eyebrow = document.getElementById('lbtt-sheet-eyebrow');
        const title = document.getElementById('lbtt-sheet-title');
        const grid = document.getElementById('lbtt-sheet-projects');
        const noteWrap = document.getElementById('lbtt-sheet-note-wrap');
        const noteInput = document.getElementById('lbtt-sheet-note');
        const closeBtn = document.getElementById('lbtt-sheet-close');

        const MONTHS = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        const PERIOD_LABELS = { AM: 'Matin', PM: 'Après-midi', EV: 'Soir', NT: 'Nuit' };
        const PERIOD_HOURS = { AM: '08–12', PM: '13–17', EV: '17–21', NT: '22–02' };

        let current = null; // { targets: [{ymd, slot}], single: bool }

        function fmtDate(ymd) {
            const [y, m, d] = ymd.split('-').map(Number);
            return `${d} ${MONTHS[m - 1]} ${y}`;
        }

        function renderSheet(state) {
            current = state;
            const first = state.targets[0];
            if (state.single) {
                eyebrow.textContent = `${PERIOD_LABELS[first.slot]} · ${PERIOD_HOURS[first.slot]}`;
                title.textContent = fmtDate(first.ymd);
                noteWrap.hidden = false;
                // Read existing note from the DOM element
                const slotEl = document.querySelector(`[data-slot][data-ymd="${first.ymd}"][data-sk="${first.slot}"]`);
                noteInput.value = slotEl ? (slotEl.dataset.note || '') : '';
            } else {
                eyebrow.textContent = 'Remplissage groupé';
                title.textContent = `${state.targets.length} créneaux`;
                noteWrap.hidden = true;
                noteInput.value = '';
            }
            // Build project grid
            grid.innerHTML = '';
            projects.forEach((p) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'lbtt-sheet-project-btn';
                btn.dataset.id = String(p.id);
                btn.innerHTML = '<span class="sw"></span><span class="nm"></span>';
                btn.querySelector('.sw').style.background = p.color;
                btn.querySelector('.nm').textContent = p.name;
                btn.addEventListener('click', () => pick(p.id));
                grid.appendChild(btn);
            });
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'lbtt-sheet-clear';
            clearBtn.textContent = state.single ? '× Effacer' : `× Effacer les ${state.targets.length} créneaux`;
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
            const targets = current.targets.map((t) => ({ date: t.ymd, period: t.slot }));
            const note = (current.single && noteInput.value.trim()) ? noteInput.value.trim() : null;
            try {
                let r, data;
                if (targets.length === 1) {
                    r = await fetch('index.php?action=api_save_entry', {
                        method: 'POST',
                        headers: authHeaders(),
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            date: targets[0].date,
                            period: targets[0].period,
                            project_id: projectId,
                            note: note,
                        }),
                    });
                } else {
                    r = await fetch('index.php?action=api_batch_save', {
                        method: 'POST',
                        headers: authHeaders(),
                        credentials: 'same-origin',
                        body: JSON.stringify({ targets: targets, project_id: projectId }),
                    });
                }
                data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.error || ('Erreur HTTP ' + r.status));
                // Apply to UI
                const p = projectId !== null ? projects.find((pp) => pp.id === projectId) : null;
                current.targets.forEach(({ ymd, slot }) => {
                    document.querySelectorAll(`[data-slot][data-ymd="${ymd}"][data-sk="${slot}"]`).forEach((el) => applyEntry(el, p, current.single ? note : (el.dataset.note || null)));
                });
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

        // Drag-to-fill pointer handling
        let drag = null;

        function findSlotAt(x, y) {
            const el = document.elementFromPoint(x, y);
            return el && el.closest ? el.closest('[data-slot]') : null;
        }

        function addToPath(ymd, slot) {
            if (!drag) return;
            const last = drag.path[drag.path.length - 1];
            if (last && last.ymd === ymd && last.slot === slot) return;
            drag.path.push({ ymd, slot });
            const el = document.querySelector(`[data-slot][data-ymd="${ymd}"][data-sk="${slot}"]`);
            if (el) el.classList.add('dragging');
        }

        function clearDragHighlight() {
            document.querySelectorAll('[data-slot].dragging').forEach((el) => el.classList.remove('dragging'));
        }

        root.addEventListener('pointerdown', (e) => {
            const slotEl = e.target.closest('[data-slot]');
            if (!slotEl) return;
            e.preventDefault();
            drag = { path: [], startTime: Date.now() };
            addToPath(slotEl.dataset.ymd, slotEl.dataset.sk);
            document.body.classList.add('lbtt-no-select');
        });

        window.addEventListener('pointermove', (e) => {
            if (!drag) return;
            const s = findSlotAt(e.clientX, e.clientY);
            if (!s) return;
            addToPath(s.dataset.ymd, s.dataset.sk);
        });

        window.addEventListener('pointerup', () => {
            if (!drag) return;
            const seen = new Set();
            const uniq = drag.path.filter((p) => {
                const k = p.ymd + '_' + p.slot;
                if (seen.has(k)) return false;
                seen.add(k);
                return true;
            });
            clearDragHighlight();
            document.body.classList.remove('lbtt-no-select');
            if (uniq.length > 0) {
                renderSheet({ targets: uniq, single: uniq.length === 1 });
            }
            drag = null;
        });

        window.addEventListener('pointercancel', () => {
            if (drag) {
                clearDragHighlight();
                document.body.classList.remove('lbtt-no-select');
                drag = null;
            }
        });
    })();
})();
