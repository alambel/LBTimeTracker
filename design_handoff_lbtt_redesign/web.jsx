// Web (desktop) surfaces for LBTimeTracker redesign.

const { useState: wS, useMemo: wM, useEffect: wE } = React;

function WebApp({ initialPage = "calendar" }) {
  const [page, setPage] = wS(initialPage);
  return (
    <div className="lbtt-root" style={{
      width: "100%", height: "100%",
      display: "grid", gridTemplateColumns: "200px 1fr", background: "var(--lbtt-paper)",
    }}>
      <WebNav page={page} onNav={setPage} />
      <div style={{ overflow: "auto" }}>
        {page === "calendar" && <WebCalendar />}
        {page === "summary"  && <WebSummary />}
        {page === "projects" && <WebProjects />}
      </div>
    </div>
  );
}

function WebNav({ page, onNav }) {
  const items = [
    { k: "calendar", label: "Calendrier", n: "01" },
    { k: "summary",  label: "Résumé",     n: "02" },
    { k: "projects", label: "Projets",    n: "03" },
  ];
  return (
    <aside style={{
      borderRight: "1px solid var(--lbtt-rule)", background: "var(--lbtt-paper-2)",
      padding: "22px 16px", display: "flex", flexDirection: "column", gap: 24,
    }}>
      <div>
        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <div style={{ width: 14, height: 14, background: "var(--lbtt-accent)" }} />
          <div className="lbtt-mono" style={{ fontSize: 9, letterSpacing: "0.15em", textTransform: "uppercase" }}>LB — TT</div>
        </div>
        <div className="lbtt-serif" style={{ fontSize: 22, marginTop: 8, lineHeight: 1, textTransform: "lowercase" }}>
          carnet<br/>d'heures.
        </div>
      </div>
      <nav style={{ display: "flex", flexDirection: "column" }}>
        {items.map(it => (
          <button key={it.k} onClick={() => onNav(it.k)} style={{
            textAlign: "left", background: "transparent", border: "none", padding: "11px 0",
            cursor: "pointer", borderBottom: "1px solid var(--lbtt-rule)",
            display: "flex", alignItems: "baseline", gap: 10,
            color: page === it.k ? "var(--lbtt-ink)" : "var(--lbtt-muted)",
          }}>
            <span className="lbtt-mono" style={{ fontSize: 9, letterSpacing: "0.1em" }}>{it.n}</span>
            <span style={{
              fontFamily: "var(--serif)", fontSize: 16, fontWeight: page === it.k ? 500 : 300,
              textDecoration: page === it.k ? "underline" : "none", textUnderlineOffset: 4,
            }}>{it.label}</span>
          </button>
        ))}
      </nav>
      <div style={{ marginTop: "auto" }}>
        <div className="lbtt-label" style={{ marginBottom: 6 }}>Session</div>
        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <div style={{ width: 28, height: 28, background: "var(--lbtt-ink)", color: "var(--lbtt-paper)", display: "grid", placeItems: "center", fontFamily: "var(--serif)", fontSize: 13 }}>A</div>
          <div>
            <div style={{ fontSize: 12 }}>adrien</div>
            <div className="lbtt-mono" style={{ fontSize: 8.5, color: "var(--lbtt-muted)", letterSpacing: "0.1em" }}>DÉCONNEXION →</div>
          </div>
        </div>
      </div>
    </aside>
  );
}

function WebCalendar() {
  const [monthDate, setMonthDate] = wS(new Date(2026, 3, 1));
  const y = monthDate.getFullYear();
  const m = monthDate.getMonth();
  const [entries, setEntries] = wS(() => lbttGenerateEntries(y, m));
  const [sheet, setSheet] = wS(null);
  const [drag, setDrag] = wS(null);
  const cells = wM(() => lbttMonthGrid(y, m), [y, m]);

  const changeMonth = (d) => {
    const n = new Date(y, m + d, 1);
    setMonthDate(n);
    setEntries(lbttGenerateEntries(n.getFullYear(), n.getMonth()));
  };

  const pointerDown = (ymd, slot, e) => { e.preventDefault(); setDrag({ path: [{ ymd, slot }] }); };
  wE(() => {
    if (!drag) return;
    const onMove = (e) => {
      const el = document.elementFromPoint(e.clientX, e.clientY);
      const s = el?.closest?.("[data-slot]");
      if (!s) return;
      const ymd = s.dataset.ymd, slot = s.dataset.sk;
      const last = drag.path[drag.path.length - 1];
      if (last.ymd === ymd && last.slot === slot) return;
      setDrag(st => ({ path: [...st.path, { ymd, slot }] }));
    };
    const onUp = () => {
      const seen = new Set();
      const uniq = drag.path.filter(p => { const k = p.ymd + p.slot; if (seen.has(k)) return false; seen.add(k); return true; });
      setSheet({ targets: uniq, single: uniq.length === 1 });
      setDrag(null);
    };
    window.addEventListener("pointermove", onMove);
    window.addEventListener("pointerup", onUp);
    return () => { window.removeEventListener("pointermove", onMove); window.removeEventListener("pointerup", onUp); };
  }, [drag]);

  const apply = (list, pid) => {
    setEntries(prev => {
      const next = { ...prev };
      list.forEach(({ ymd, slot }) => {
        next[ymd] = { ...(next[ymd] || {}) };
        if (pid == null) delete next[ymd][slot];
        else next[ymd][slot] = { projectId: pid, note: next[ymd][slot]?.note || "" };
      });
      return next;
    });
  };

  const active = LBTT_PROJECTS.filter(p => !p.archived);
  const agg = wM(() => lbttAggregate(entries), [entries]);
  const topId = Object.entries(agg.byProject).sort((a, b) => b[1] - a[1])[0]?.[0];
  const top = topId ? lbttProjectById(Number(topId)) : null;

  return (
    <div className="lbtt-no-select" style={{ padding: "24px 28px", minHeight: "100%" }}>
      <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", marginBottom: 20, gap: 20 }}>
        <div>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Mois en cours · {y}</div>
          <div className="lbtt-serif" style={{ fontSize: 56, lineHeight: 0.95, textTransform: "lowercase" }}>{LBTT_MONTHS_FR[m]}.</div>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
          <div style={{ textAlign: "right" }}>
            <div className="lbtt-label">Saisi</div>
            <div className="lbtt-num" style={{ fontSize: 28 }}>{(agg.totalHalfDays / 2).toFixed(1)}<span style={{ fontSize: 13, color: "var(--lbtt-muted)" }}> j</span></div>
          </div>
          <div className="lbtt-rule-v" style={{ height: 40 }} />
          <div>
            <div className="lbtt-label">Dominant</div>
            <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 5 }}>
              <div style={{ width: 10, height: 10, background: top?.color || "var(--lbtt-muted)" }} />
              <div style={{ fontSize: 13 }}>{top?.name || "—"}</div>
            </div>
          </div>
          <div className="lbtt-rule-v" style={{ height: 40 }} />
          <div style={{ display: "flex", gap: 4 }}>
            <button className="lbtt-btn lbtt-btn-ghost" onClick={() => changeMonth(-1)}>‹</button>
            <button className="lbtt-btn lbtt-btn-ghost" onClick={() => { const n = new Date(); setMonthDate(new Date(n.getFullYear(), n.getMonth(), 1)); setEntries(lbttGenerateEntries(n.getFullYear(), n.getMonth())); }}>AUJ.</button>
            <button className="lbtt-btn lbtt-btn-ghost" onClick={() => changeMonth(1)}>›</button>
          </div>
        </div>
      </div>

      <div style={{
        display: "flex", gap: 14, alignItems: "center", padding: "9px 12px",
        background: "var(--lbtt-paper-2)", border: "1px solid var(--lbtt-rule)", marginBottom: 14,
      }}>
        <div className="lbtt-chip">Astuce</div>
        <div style={{ fontSize: 12.5, color: "var(--lbtt-ink-2)" }}>
          Cliquez un créneau pour l'éditer. Maintenez et glissez pour remplir plusieurs créneaux à la fois.
        </div>
      </div>

      <div style={{ border: "1px solid var(--lbtt-ink)" }}>
        <div style={{
          display: "grid", gridTemplateColumns: "repeat(7, 1fr)",
          background: "var(--lbtt-ink)", color: "var(--lbtt-paper)",
        }}>
          {["Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi","Dimanche"].map((d, i) => (
            <div key={i} className="lbtt-mono" style={{
              padding: "7px 10px", fontSize: 9.5, letterSpacing: "0.15em", textTransform: "uppercase",
              borderRight: i < 6 ? "1px solid rgba(255,255,255,0.15)" : "none",
            }}>{d}</div>
          ))}
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(7, 1fr)" }}>
          {cells.map((d, i) => {
            const isRight = (i + 1) % 7 === 0;
            const isLast = i >= cells.length - 7;
            if (d == null) return <div key={i} style={{
              background: "var(--lbtt-paper-2)",
              borderRight: isRight ? "none" : "1px solid var(--lbtt-rule)",
              borderBottom: isLast ? "none" : "1px solid var(--lbtt-rule)",
              minHeight: 110,
            }} />;
            const ymd = lbttKey(y, m, d);
            const de = entries[ymd] || {};
            const isToday = lbttIsToday(y, m, d);
            const dow = new Date(y, m, d).getDay();
            const weekend = dow === 0 || dow === 6;
            return (
              <div key={i} style={{
                padding: 9, minHeight: 110,
                borderRight: isRight ? "none" : "1px solid var(--lbtt-rule)",
                borderBottom: isLast ? "none" : "1px solid var(--lbtt-rule)",
                background: weekend ? "var(--lbtt-paper-2)" : "var(--lbtt-paper)",
                display: "flex", flexDirection: "column", gap: 5,
              }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline" }}>
                  <div className="lbtt-num" style={{ fontSize: 20, color: isToday ? "var(--lbtt-accent)" : "var(--lbtt-ink)" }}>{d}</div>
                  {isToday && <span className="lbtt-chip lbtt-chip-accent" style={{ fontSize: 7, padding: "2px 5px" }}>Auj.</span>}
                </div>
                <div style={{ display: "flex", flexDirection: "column", gap: 2, flex: 1 }}>
                  {LBTT_SLOTS.map(s => {
                    const e = de[s.key];
                    const p = e && lbttProjectById(e.projectId);
                    const ind = drag?.path?.some(x => x.ymd === ymd && x.slot === s.key);
                    return (
                      <div key={s.key} data-slot data-ymd={ymd} data-sk={s.key}
                        onPointerDown={(ev) => pointerDown(ymd, s.key, ev)}
                        style={{
                          flex: 1, minHeight: 13,
                          background: p ? p.color : "transparent",
                          border: p ? "none" : "1px dashed var(--lbtt-rule)",
                          outline: ind ? "2px solid var(--lbtt-ink)" : "none", outlineOffset: -1,
                          display: "flex", alignItems: "center", gap: 5, padding: "0 5px",
                          color: p ? "#fff" : "var(--lbtt-muted)", fontSize: 10, cursor: "pointer", touchAction: "none",
                        }}>
                        <span className="lbtt-mono" style={{ fontSize: 7.5, letterSpacing: "0.1em", opacity: 0.85 }}>{s.key}</span>
                        <span style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", fontSize: 9.5 }}>{p?.name || "—"}</span>
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div style={{ display: "flex", gap: 6, flexWrap: "wrap", marginTop: 14 }}>
        {active.map(p => (
          <span key={p.id} className="lbtt-tag">
            <span className="lbtt-tag-dot" style={{ background: p.color }} />{p.name}
          </span>
        ))}
      </div>

      {sheet && <WebSheet sheet={sheet} entries={entries}
        onClose={() => setSheet(null)}
        onPick={(pid) => { apply(sheet.targets, pid); setSheet(null); }} />}
    </div>
  );
}

function WebSheet({ sheet, entries, onClose, onPick }) {
  const active = LBTT_PROJECTS.filter(p => !p.archived);
  const first = sheet.targets[0];
  const existing = sheet.single ? entries[first.ymd]?.[first.slot] : null;
  const [note, setNote] = wS(existing?.note || "");
  const slotMeta = LBTT_SLOTS.find(s => s.key === first.slot);
  const label = sheet.single
    ? (() => { const [yy, mm, dd] = first.ymd.split("-").map(Number); return `${dd} ${LBTT_MONTHS_FR[mm - 1]} ${yy}`; })()
    : `${sheet.targets.length} créneaux sélectionnés`;
  return (
    <div onClick={onClose} style={{
      position: "fixed", inset: 0, background: "rgba(26,26,26,0.55)",
      display: "grid", placeItems: "center", zIndex: 40,
    }}>
      <div onClick={e => e.stopPropagation()} style={{
        width: 500, maxWidth: "92vw", background: "var(--lbtt-paper)",
        border: "1px solid var(--lbtt-ink)", padding: "22px 24px 24px",
      }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 14 }}>
          <div>
            <div className="lbtt-label">{sheet.single ? `${slotMeta.label} · ${slotMeta.hours}` : "Remplissage groupé"}</div>
            <div className="lbtt-serif" style={{ fontSize: 24, lineHeight: 1.1, marginTop: 2 }}>{label}</div>
          </div>
          <button className="lbtt-btn lbtt-btn-ghost" onClick={onClose}>×</button>
        </div>
        <div className="lbtt-label" style={{ marginBottom: 6 }}>Projet</div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 5, marginBottom: 14 }}>
          {active.map(p => (
            <button key={p.id} onClick={() => onPick(p.id)} style={{
              display: "flex", alignItems: "center", gap: 8, padding: "9px 10px",
              background: "var(--lbtt-paper-2)", border: "1px solid var(--lbtt-rule)",
              cursor: "pointer", textAlign: "left", color: "var(--lbtt-ink)", fontSize: 12.5,
            }}>
              <span style={{ width: 12, height: 12, background: p.color, flexShrink: 0 }} />
              <span style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{p.name}</span>
            </button>
          ))}
          <button onClick={() => onPick(null)} style={{
            gridColumn: "1 / -1", padding: 9, background: "transparent",
            border: "1px dashed var(--lbtt-rule)", color: "var(--lbtt-muted)",
            cursor: "pointer", fontFamily: "var(--mono)", fontSize: 10.5, letterSpacing: "0.1em", textTransform: "uppercase",
          }}>× Effacer {!sheet.single && `les ${sheet.targets.length} créneaux`}</button>
        </div>
        {sheet.single && (
          <>
            <div className="lbtt-label" style={{ marginBottom: 5 }}>Note (facultatif)</div>
            <input className="lbtt-input" value={note} maxLength={200} onChange={e => setNote(e.target.value)} placeholder="Ex: sprint X, bug Y…" />
          </>
        )}
      </div>
    </div>
  );
}

function WebSummary() {
  const [from, setFrom] = wS("2026-04-01");
  const [to, setTo] = wS("2026-04-30");
  const entries = wM(() => lbttGenerateEntries(2026, 3), []);
  const agg = lbttAggregate(entries);
  const rows = LBTT_PROJECTS.filter(p => agg.byProject[p.id])
    .map(p => ({ ...p, h: agg.byProject[p.id], d: agg.byProject[p.id] / 2, pct: (agg.byProject[p.id] / Math.max(1, agg.totalHalfDays)) * 100 }))
    .sort((a, b) => b.h - a.h);
  const days = Object.keys(entries).sort();
  return (
    <div style={{ padding: "24px 28px" }}>
      <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", marginBottom: 16 }}>
        <div>
          <div className="lbtt-label">Rapport · période filtrée</div>
          <div className="lbtt-serif" style={{ fontSize: 56, lineHeight: 0.95, textTransform: "lowercase" }}>résumé.</div>
        </div>
        <button className="lbtt-btn lbtt-btn-primary">↓ EXPORT CSV</button>
      </div>
      <div style={{ display: "flex", alignItems: "end", gap: 12, padding: "12px 16px", border: "1px solid var(--lbtt-rule)", marginBottom: 16 }}>
        <div>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Du</div>
          <input className="lbtt-input" type="date" value={from} onChange={e => setFrom(e.target.value)} style={{ width: 150 }} />
        </div>
        <div>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Au</div>
          <input className="lbtt-input" type="date" value={to} onChange={e => setTo(e.target.value)} style={{ width: 150 }} />
        </div>
        <button className="lbtt-btn lbtt-btn-primary">Filtrer →</button>
        <div style={{ flex: 1 }} />
        <div className="lbtt-mono" style={{ fontSize: 10, color: "var(--lbtt-muted)", letterSpacing: "0.1em" }}>
          30 JOURS · {agg.totalHalfDays} DEMI-JOURNÉES
        </div>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 10, marginBottom: 16 }}>
        {[
          { l: "Demi-journées", v: agg.totalHalfDays, sub: "SUR 120 POSSIBLES" },
          { l: "Jours équiv.",  v: (agg.totalHalfDays / 2).toFixed(1), sub: "120 J. MAX" },
          { l: "Projets actifs", v: rows.length, sub: "1 ARCHIVÉ" },
          { l: "Complétude",    v: Math.round((agg.totalHalfDays / 120) * 100) + "%", sub: "DEMI-J. REMPLIES" },
        ].map((k, i) => (
          <div key={i} style={{ border: "1px solid var(--lbtt-rule)", padding: "13px 16px" }}>
            <div className="lbtt-label">{k.l}</div>
            <div className="lbtt-num" style={{ fontSize: 40, lineHeight: 1 }}>{k.v}</div>
            <div className="lbtt-mono" style={{ fontSize: 9.5, color: "var(--lbtt-muted)", marginTop: 4, letterSpacing: "0.1em" }}>{k.sub}</div>
          </div>
        ))}
      </div>
      <div style={{ border: "1px solid var(--lbtt-rule)", padding: "14px 16px", marginBottom: 16 }}>
        <div className="lbtt-label" style={{ marginBottom: 8 }}>Distribution quotidienne — chaque colonne = un jour, chaque bande = un créneau</div>
        <div style={{ display: "flex", gap: 2, height: 64 }}>
          {days.map(k => {
            const e = entries[k];
            return (
              <div key={k} style={{ flex: 1, display: "flex", flexDirection: "column", gap: 1 }}>
                {LBTT_SLOTS.map(s => {
                  const ee = e[s.key];
                  const p = ee && lbttProjectById(ee.projectId);
                  return <div key={s.key} style={{ flex: 1, background: p ? p.color : "var(--lbtt-paper-3)" }} />;
                })}
              </div>
            );
          })}
        </div>
        <div style={{ display: "flex", justifyContent: "space-between", marginTop: 6 }}>
          <div className="lbtt-mono" style={{ fontSize: 9, color: "var(--lbtt-muted)" }}>01 AVR.</div>
          <div className="lbtt-mono" style={{ fontSize: 9, color: "var(--lbtt-muted)" }}>30 AVR.</div>
        </div>
      </div>
      <div style={{ border: "1px solid var(--lbtt-rule)" }}>
        <div style={{
          display: "grid", gridTemplateColumns: "1fr 130px 130px 100px 2fr",
          padding: "10px 16px", background: "var(--lbtt-paper-2)",
          borderBottom: "1px solid var(--lbtt-rule)",
        }}>
          {["Projet","Demi-journées","Jours éq.","%","Répartition"].map(h => <div key={h} className="lbtt-label">{h}</div>)}
        </div>
        {rows.map((r, i) => (
          <div key={r.id} style={{
            display: "grid", gridTemplateColumns: "1fr 130px 130px 100px 2fr",
            alignItems: "center", padding: "12px 16px",
            borderBottom: i === rows.length - 1 ? "none" : "1px solid var(--lbtt-rule)",
          }}>
            <div style={{ display: "flex", alignItems: "center", gap: 9 }}>
              <span style={{ width: 10, height: 10, background: r.color }} />
              <span style={{ fontSize: 13.5 }}>{r.name}</span>
            </div>
            <div className="lbtt-num" style={{ fontSize: 17 }}>{r.h}</div>
            <div className="lbtt-num" style={{ fontSize: 17 }}>{r.d.toFixed(1)}</div>
            <div className="lbtt-mono" style={{ fontSize: 11.5 }}>{r.pct.toFixed(1)}%</div>
            <div style={{ height: 8, background: "var(--lbtt-paper-3)" }}>
              <div style={{ height: "100%", width: `${r.pct}%`, background: r.color }} />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function WebProjects() {
  const [projs, setProjs] = wS(LBTT_PROJECTS);
  const [newName, setNewName] = wS("");
  const [newColor, setNewColor] = wS("#8a6a4a");
  return (
    <div style={{ padding: "24px 28px" }}>
      <div style={{ marginBottom: 18 }}>
        <div className="lbtt-label">Taxonomie</div>
        <div className="lbtt-serif" style={{ fontSize: 56, lineHeight: 0.95, textTransform: "lowercase" }}>projets.</div>
      </div>
      <div style={{
        display: "flex", gap: 10, alignItems: "end", padding: "14px 16px",
        border: "1px solid var(--lbtt-ink)", background: "var(--lbtt-paper-2)", marginBottom: 16,
      }}>
        <div style={{ flex: 1 }}>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Nouveau projet</div>
          <input className="lbtt-input" placeholder="Nom — ex. Refonte Carnet…" value={newName} onChange={e => setNewName(e.target.value)} />
        </div>
        <div>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Couleur</div>
          <div style={{ display: "flex", gap: 4 }}>
            {["#c45a2e","#2d4a3e","#c9a24a","#5a6f8a","#8a6a4a","#a14a3a","#6a8a5a"].map(c => (
              <button key={c} onClick={() => setNewColor(c)} style={{
                width: 28, height: 28, background: c, cursor: "pointer",
                border: newColor === c ? "2px solid var(--lbtt-ink)" : "1px solid var(--lbtt-rule)",
              }} />
            ))}
          </div>
        </div>
        <button className="lbtt-btn lbtt-btn-primary" onClick={() => {
          if (!newName.trim()) return;
          setProjs(p => [...p, { id: Date.now(), name: newName, color: newColor, archived: false }]);
          setNewName("");
        }}>Ajouter →</button>
      </div>
      <div style={{ border: "1px solid var(--lbtt-rule)" }}>
        {projs.map((p, i) => (
          <div key={p.id} style={{
            display: "grid", gridTemplateColumns: "28px 1fr 130px 110px 100px 90px",
            gap: 10, alignItems: "center", padding: "10px 14px",
            borderBottom: i === projs.length - 1 ? "none" : "1px solid var(--lbtt-rule)",
            background: p.archived ? "var(--lbtt-paper-2)" : "transparent",
            opacity: p.archived ? 0.5 : 1,
          }}>
            <div style={{ width: 18, height: 18, background: p.color, border: "1px solid var(--lbtt-ink)" }} />
            <input value={p.name}
              onChange={e => setProjs(ps => ps.map(x => x.id === p.id ? { ...x, name: e.target.value } : x))}
              style={{ background: "transparent", border: "none", fontSize: 14, padding: "4px 0", color: "var(--lbtt-ink)", outline: "none", fontFamily: "var(--sans)" }} />
            <div className="lbtt-mono" style={{ fontSize: 10.5, color: "var(--lbtt-muted)" }}>{p.color.toUpperCase()}</div>
            <div className="lbtt-mono" style={{ fontSize: 10.5, color: "var(--lbtt-muted)" }}>{[14, 22, 8, 6, 11, 3, 2][i % 7]} saisies</div>
            <button className="lbtt-btn lbtt-btn-ghost" style={{ fontSize: 9.5 }}
              onClick={() => setProjs(ps => ps.map(x => x.id === p.id ? { ...x, archived: !x.archived } : x))}>
              {p.archived ? "Restaurer" : "Archiver"}
            </button>
            <button className="lbtt-btn lbtt-btn-ghost" style={{ fontSize: 9.5, color: "var(--lbtt-accent-ink)" }}
              onClick={() => setProjs(ps => ps.filter(x => x.id !== p.id))}>Supprimer</button>
          </div>
        ))}
      </div>
      <div className="lbtt-mono" style={{ fontSize: 9.5, color: "var(--lbtt-muted)", marginTop: 12, letterSpacing: "0.1em" }}>
        ⚠ SUPPRIMER UN PROJET EFFACE AUSSI TOUTES SES SAISIES (CASCADE).
      </div>
    </div>
  );
}

function WebLogin() {
  return (
    <div className="lbtt-root" style={{
      width: "100%", height: "100%",
      display: "grid", gridTemplateColumns: "1.2fr 1fr", background: "var(--lbtt-paper)",
    }}>
      <div style={{ background: "var(--lbtt-ink)", color: "var(--lbtt-paper)", padding: 40, display: "flex", flexDirection: "column", justifyContent: "space-between", position: "relative", overflow: "hidden" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
          <div style={{ width: 16, height: 16, background: "var(--lbtt-accent)" }} />
          <div className="lbtt-mono" style={{ fontSize: 9.5, letterSpacing: "0.2em", textTransform: "uppercase" }}>LB — TIME TRACKER · V1.0</div>
        </div>
        <div>
          <div className="lbtt-serif" style={{ fontSize: 88, lineHeight: 0.9, textTransform: "lowercase", letterSpacing: "-0.04em" }}>
            carnet<br/><span style={{ color: "var(--lbtt-accent)" }}>d'heures.</span>
          </div>
          <p style={{ color: "rgba(255,255,255,0.6)", fontSize: 14, maxWidth: 380, marginTop: 20, lineHeight: 1.5 }}>
            Suivi du temps par créneau — matin, après-midi, soir, nuit. Une couleur par projet, un CSV par mois.
          </p>
        </div>
        <div className="lbtt-mono" style={{ fontSize: 9.5, letterSpacing: "0.15em", color: "rgba(255,255,255,0.4)" }}>PHP · MARIADB · AUCUNE DÉPENDANCE</div>
        <div style={{ position: "absolute", right: 0, top: 0, bottom: 0, width: 48, display: "flex", flexDirection: "column" }}>
          <div style={{ flex: 1, background: "#c45a2e" }} />
          <div style={{ flex: 1, background: "#2d4a3e" }} />
          <div style={{ flex: 1, background: "#5a6f8a" }} />
          <div style={{ flex: 1, background: "#0a0a0a" }} />
        </div>
      </div>
      <div style={{ padding: 40, display: "flex", flexDirection: "column", justifyContent: "center" }}>
        <div style={{ maxWidth: 320 }}>
          <div className="lbtt-label" style={{ marginBottom: 6 }}>Connexion</div>
          <div className="lbtt-serif" style={{ fontSize: 32, lineHeight: 1, marginBottom: 20, textTransform: "lowercase" }}>se connecter.</div>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Utilisateur</div>
          <input className="lbtt-input" defaultValue="adrien" style={{ marginBottom: 12 }} />
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Mot de passe</div>
          <input className="lbtt-input" type="password" placeholder="••••••••••" />
          <button className="lbtt-btn lbtt-btn-primary" style={{ marginTop: 16, width: "100%" }}>SE CONNECTER →</button>
          <div className="lbtt-mono" style={{ fontSize: 9.5, color: "var(--lbtt-muted)", marginTop: 14, letterSpacing: "0.12em" }}>
            BCRYPT · HTTPONLY · SAMESITE=LAX
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { WebApp, WebLogin });
