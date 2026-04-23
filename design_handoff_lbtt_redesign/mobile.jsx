// Mobile surfaces for LBTimeTracker redesign.
// Calendar (month grid, 4 slots/day, drag-to-fill), Summary, Projects, Login, Setup, Heatmap.

const { useState: uS, useMemo: uM, useEffect: uE, useRef: uR } = React;

// ========== Mobile Calendar ==========
function MobileCalendar({ accent = "#c45a2e" }) {
  const [monthDate, setMonthDate] = uS(new Date(2026, 3, 1));
  const y = monthDate.getFullYear();
  const m = monthDate.getMonth();
  const [entries, setEntries] = uS(() => lbttGenerateEntries(y, m));
  const [sheet, setSheet] = uS(null);
  const [drag, setDrag] = uS(null);
  const cells = uM(() => lbttMonthGrid(y, m), [y, m]);

  const changeMonth = (d) => {
    const n = new Date(y, m + d, 1);
    setMonthDate(n);
    setEntries(lbttGenerateEntries(n.getFullYear(), n.getMonth()));
  };

  const pointerDown = (ymd, slot, e) => {
    e.preventDefault();
    setDrag({ path: [{ ymd, slot }] });
  };
  uE(() => {
    if (!drag) return;
    const onMove = (e) => {
      const t = e.touches?.[0] || e;
      const el = document.elementFromPoint(t.clientX, t.clientY);
      const s = el?.closest?.("[data-slot]");
      if (!s) return;
      const ymd = s.dataset.ymd, slot = s.dataset.sk;
      const last = drag.path[drag.path.length - 1];
      if (last.ymd === ymd && last.slot === slot) return;
      setDrag(st => ({ path: [...st.path, { ymd, slot }] }));
    };
    const onUp = () => {
      const seen = new Set();
      const uniq = drag.path.filter(p => {
        const k = p.ymd + p.slot;
        if (seen.has(k)) return false;
        seen.add(k); return true;
      });
      setSheet({ targets: uniq, single: uniq.length === 1 });
      setDrag(null);
    };
    window.addEventListener("pointermove", onMove);
    window.addEventListener("pointerup", onUp);
    return () => {
      window.removeEventListener("pointermove", onMove);
      window.removeEventListener("pointerup", onUp);
    };
  }, [drag]);

  const apply = (targets, pid) => {
    setEntries(prev => {
      const next = { ...prev };
      targets.forEach(({ ymd, slot }) => {
        next[ymd] = { ...(next[ymd] || {}) };
        if (pid == null) delete next[ymd][slot];
        else next[ymd][slot] = { projectId: pid, note: next[ymd][slot]?.note || "" };
      });
      return next;
    });
  };

  const active = LBTT_PROJECTS.filter(p => !p.archived);
  const today = new Date();
  const todayKey = lbttKey(today.getFullYear(), today.getMonth(), today.getDate());
  const todayEntries = entries[todayKey] || {};
  const filled = Object.keys(todayEntries).length;

  return (
    <div className="lbtt-root lbtt-no-select" style={{ minHeight: "100%", display: "flex", flexDirection: "column", position: "relative" }}>
      {/* Header */}
      <div style={{ padding: "14px 18px 10px", borderBottom: "1px solid var(--lbtt-rule)" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline" }}>
          <div>
            <div className="lbtt-label">Journal · {y}</div>
            <div className="lbtt-serif" style={{ fontSize: 28, lineHeight: 1, textTransform: "lowercase" }}>
              {LBTT_MONTHS_FR[m]}.
            </div>
          </div>
          <div style={{ display: "flex", gap: 4 }}>
            <button className="lbtt-btn lbtt-btn-ghost" onClick={() => changeMonth(-1)} style={{ padding: "6px 10px" }}>‹</button>
            <button className="lbtt-btn lbtt-btn-ghost" onClick={() => changeMonth(1)} style={{ padding: "6px 10px" }}>›</button>
          </div>
        </div>

        <div style={{
          marginTop: 10, padding: "8px 10px", background: "var(--lbtt-paper-2)",
          border: "1px solid var(--lbtt-rule)", display: "flex", alignItems: "center", gap: 10,
        }}>
          <div style={{
            width: 30, height: 30, background: "var(--lbtt-ink)", color: "var(--lbtt-paper)",
            display: "grid", placeItems: "center",
          }}>
            <span className="lbtt-mono" style={{ fontSize: 12 }}>{today.getDate()}</span>
          </div>
          <div style={{ flex: 1 }}>
            <div className="lbtt-label">Aujourd'hui · {filled}/4</div>
            <div style={{ display: "flex", gap: 2, marginTop: 4 }}>
              {LBTT_SLOTS.map(s => {
                const e = todayEntries[s.key];
                const p = e && lbttProjectById(e.projectId);
                return <div key={s.key} style={{ flex: 1, height: 5, background: p ? p.color : "var(--lbtt-paper-3)" }} />;
              })}
            </div>
          </div>
        </div>
      </div>

      {/* Weekday */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(7, 1fr)", padding: "6px 10px 2px" }}>
        {LBTT_DAYS_FR_SHORT.map((d, i) => (
          <div key={i} className="lbtt-mono" style={{ fontSize: 9, textAlign: "center", color: "var(--lbtt-muted)", letterSpacing: "0.15em" }}>{d}</div>
        ))}
      </div>

      {/* Grid */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(7, 1fr)", flex: 1, padding: "2px 10px 8px" }}>
        {cells.map((d, i) => {
          if (d == null) return <div key={i} />;
          const ymd = lbttKey(y, m, d);
          const de = entries[ymd] || {};
          const isToday = lbttIsToday(y, m, d);
          return (
            <div key={i} style={{
              padding: "3px 2px", display: "flex", flexDirection: "column",
              borderRight: (i + 1) % 7 === 0 ? "none" : "1px solid var(--lbtt-rule)",
              borderBottom: "1px solid var(--lbtt-rule)",
            }}>
              <div className="lbtt-mono" style={{
                fontSize: 9.5, textAlign: "center", marginBottom: 2,
                color: isToday ? "var(--lbtt-accent)" : "var(--lbtt-ink)",
                fontWeight: isToday ? 600 : 400,
              }}>{d}</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 1.5, flex: 1 }}>
                {LBTT_SLOTS.map(s => {
                  const e = de[s.key];
                  const p = e && lbttProjectById(e.projectId);
                  const ind = drag?.path?.some(x => x.ymd === ymd && x.slot === s.key);
                  return (
                    <div key={s.key} data-slot data-ymd={ymd} data-sk={s.key}
                      onPointerDown={(ev) => pointerDown(ymd, s.key, ev)}
                      style={{
                        flex: 1, minHeight: 9,
                        background: p ? p.color : "var(--lbtt-paper-3)",
                        outline: ind ? "1.5px solid var(--lbtt-ink)" : "none",
                        touchAction: "none", cursor: "pointer",
                      }} />
                  );
                })}
              </div>
            </div>
          );
        })}
      </div>

      {/* Legend */}
      <div style={{ padding: "10px 16px 14px", borderTop: "1px solid var(--lbtt-rule)", display: "flex", gap: 5, flexWrap: "wrap" }}>
        {active.slice(0, 6).map(p => (
          <span key={p.id} className="lbtt-tag" style={{ fontSize: 10 }}>
            <span className="lbtt-tag-dot" style={{ background: p.color }} />{p.name}
          </span>
        ))}
      </div>

      {sheet && <MobSheet sheet={sheet} entries={entries}
        onClose={() => setSheet(null)}
        onPick={(pid) => { apply(sheet.targets, pid); setSheet(null); }} />}
    </div>
  );
}

function MobSheet({ sheet, entries, onClose, onPick }) {
  const active = LBTT_PROJECTS.filter(p => !p.archived);
  const first = sheet.targets[0];
  const existing = sheet.single ? entries[first.ymd]?.[first.slot] : null;
  const [note, setNote] = uS(existing?.note || "");
  const slotMeta = LBTT_SLOTS.find(s => s.key === first.slot);
  const label = sheet.single
    ? (() => { const [yy, mm, dd] = first.ymd.split("-").map(Number); return `${dd} ${LBTT_MONTHS_FR[mm - 1]}`; })()
    : `${sheet.targets.length} créneaux`;
  return (
    <div onClick={onClose} style={{
      position: "absolute", inset: 0, background: "rgba(0,0,0,0.4)",
      display: "flex", alignItems: "flex-end", zIndex: 30,
    }}>
      <div onClick={e => e.stopPropagation()} style={{
        width: "100%", background: "var(--lbtt-paper)", borderTop: "1px solid var(--lbtt-ink)",
        padding: "14px 18px 22px", animation: "mslide 220ms cubic-bezier(.2,.8,.3,1)",
      }}>
        <div style={{ width: 36, height: 3, background: "var(--lbtt-faint)", margin: "0 auto 12px" }} />
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: 12 }}>
          <div>
            <div className="lbtt-label">{sheet.single ? `${slotMeta.label} · ${slotMeta.hours}` : "Remplissage groupé"}</div>
            <div className="lbtt-serif" style={{ fontSize: 22, marginTop: 2, textTransform: "lowercase" }}>{label}</div>
          </div>
          <button className="lbtt-btn lbtt-btn-ghost" onClick={onClose} style={{ padding: "4px 10px", fontSize: 10 }}>Fermer</button>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 5, marginBottom: 12 }}>
          {active.map(p => (
            <button key={p.id} onClick={() => onPick(p.id)} style={{
              display: "flex", alignItems: "center", gap: 8, padding: "9px 10px",
              background: "var(--lbtt-paper-2)", border: "1px solid var(--lbtt-rule)",
              cursor: "pointer", textAlign: "left", color: "var(--lbtt-ink)", fontSize: 12.5,
            }}>
              <span style={{ width: 11, height: 11, background: p.color, flexShrink: 0 }} />
              <span style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{p.name}</span>
            </button>
          ))}
          <button onClick={() => onPick(null)} style={{
            gridColumn: "1 / -1", padding: 9, background: "transparent",
            border: "1px dashed var(--lbtt-rule)", color: "var(--lbtt-muted)", cursor: "pointer",
            fontFamily: "var(--mono)", fontSize: 10, letterSpacing: "0.1em", textTransform: "uppercase",
          }}>× Effacer</button>
        </div>
        {sheet.single && (
          <>
            <div className="lbtt-label" style={{ marginBottom: 5 }}>Note</div>
            <input className="lbtt-input" value={note} maxLength={200}
              onChange={e => setNote(e.target.value)} placeholder="Ex: sprint X, bug Y…" style={{ fontSize: 13 }} />
          </>
        )}
      </div>
      <style>{`@keyframes mslide { from { transform: translateY(100%);} to {transform: translateY(0);} }`}</style>
    </div>
  );
}

// ========== Mobile Summary ==========
function MobileSummary() {
  const [range, setRange] = uS("month");
  const entries = uM(() => {
    const d = new Date(2026, 3, 1);
    const out = {};
    const months = range === "month" ? 1 : range === "quarter" ? 3 : 12;
    for (let i = 0; i < months; i++) {
      const dt = new Date(d.getFullYear(), d.getMonth() - i, 1);
      Object.assign(out, lbttGenerateEntries(dt.getFullYear(), dt.getMonth()));
    }
    return out;
  }, [range]);
  const agg = lbttAggregate(entries);
  const rows = LBTT_PROJECTS.filter(p => agg.byProject[p.id])
    .map(p => ({ ...p, h: agg.byProject[p.id], d: agg.byProject[p.id] / 2, pct: (agg.byProject[p.id] / Math.max(1, agg.totalHalfDays)) * 100 }))
    .sort((a, b) => b.h - a.h);

  return (
    <div className="lbtt-root" style={{ minHeight: "100%", display: "flex", flexDirection: "column" }}>
      <div style={{ padding: "14px 18px 10px", borderBottom: "1px solid var(--lbtt-rule)" }}>
        <div className="lbtt-label">Résumé · avril 2026</div>
        <div className="lbtt-serif" style={{ fontSize: 28, lineHeight: 1, textTransform: "lowercase" }}>temps.</div>
      </div>
      <div style={{ display: "flex", gap: 4, padding: "10px 18px 6px" }}>
        {[["month", "Mois"], ["quarter", "Trim."], ["year", "Année"]].map(([k, l]) => (
          <button key={k} onClick={() => setRange(k)} style={{
            flex: 1, padding: "7px", fontFamily: "var(--mono)", fontSize: 10, letterSpacing: "0.1em",
            textTransform: "uppercase", border: "1px solid var(--lbtt-ink)",
            background: range === k ? "var(--lbtt-ink)" : "transparent",
            color: range === k ? "var(--lbtt-paper)" : "var(--lbtt-ink)", cursor: "pointer",
          }}>{l}</button>
        ))}
      </div>
      <div style={{ padding: "8px 18px", display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
        <div style={{ border: "1px solid var(--lbtt-rule)", padding: "10px 12px" }}>
          <div className="lbtt-label">Jours éq.</div>
          <div className="lbtt-num" style={{ fontSize: 34, lineHeight: 1 }}>{(agg.totalHalfDays / 2).toFixed(1)}</div>
        </div>
        <div style={{ border: "1px solid var(--lbtt-rule)", padding: "10px 12px" }}>
          <div className="lbtt-label">Demi-j.</div>
          <div className="lbtt-num" style={{ fontSize: 34, lineHeight: 1 }}>{agg.totalHalfDays}</div>
        </div>
      </div>
      <div style={{ padding: "4px 18px 12px" }}>
        <div style={{ display: "flex", height: 24, border: "1px solid var(--lbtt-ink)" }}>
          {rows.map(r => <div key={r.id} style={{ background: r.color, width: `${r.pct}%` }} />)}
        </div>
      </div>
      <div style={{ flex: 1, overflow: "auto", borderTop: "1px solid var(--lbtt-rule)" }}>
        {rows.map((r, i) => (
          <div key={r.id} style={{
            display: "grid", gridTemplateColumns: "14px 1fr auto auto", gap: 10, alignItems: "center",
            padding: "12px 18px", borderBottom: i === rows.length - 1 ? "none" : "1px solid var(--lbtt-rule)",
          }}>
            <div style={{ width: 10, height: 10, background: r.color }} />
            <div>
              <div style={{ fontSize: 13 }}>{r.name}</div>
              <div className="lbtt-label">{r.h} demi-j.</div>
            </div>
            <div className="lbtt-num" style={{ fontSize: 20 }}>{r.d.toFixed(1)}</div>
            <div className="lbtt-mono" style={{ fontSize: 10.5, color: "var(--lbtt-muted)" }}>{r.pct.toFixed(0)}%</div>
          </div>
        ))}
      </div>
      <div style={{ padding: "10px 18px", borderTop: "1px solid var(--lbtt-rule)" }}>
        <button className="lbtt-btn lbtt-btn-primary" style={{ width: "100%" }}>↓ Export CSV</button>
      </div>
    </div>
  );
}

// ========== Mobile Projects ==========
function MobileProjects() {
  const [projs, setProjs] = uS(LBTT_PROJECTS);
  const [adding, setAdding] = uS(false);
  const [newName, setNewName] = uS("");
  const [newColor, setNewColor] = uS("#8a6a4a");
  return (
    <div className="lbtt-root" style={{ minHeight: "100%", display: "flex", flexDirection: "column" }}>
      <div style={{ padding: "14px 18px 10px", borderBottom: "1px solid var(--lbtt-rule)" }}>
        <div className="lbtt-label">Gestion</div>
        <div className="lbtt-serif" style={{ fontSize: 28, lineHeight: 1, textTransform: "lowercase" }}>projets.</div>
      </div>
      <div style={{ flex: 1, overflow: "auto" }}>
        {projs.map(p => (
          <div key={p.id} style={{
            display: "grid", gridTemplateColumns: "auto 1fr auto", gap: 12, alignItems: "center",
            padding: "13px 18px", borderBottom: "1px solid var(--lbtt-rule)",
            opacity: p.archived ? 0.45 : 1,
          }}>
            <div style={{ width: 18, height: 18, background: p.color, border: "1px solid var(--lbtt-ink)" }} />
            <div>
              <div style={{ fontSize: 14 }}>{p.name}</div>
              <div className="lbtt-label">{p.archived ? "Archivé" : p.color}</div>
            </div>
            <button className="lbtt-btn lbtt-btn-ghost" style={{ fontSize: 9, padding: "6px 8px" }}
              onClick={() => setProjs(ps => ps.map(x => x.id === p.id ? { ...x, archived: !x.archived } : x))}>
              {p.archived ? "↑ Restaurer" : "▢ Archiver"}
            </button>
          </div>
        ))}
        {adding ? (
          <div style={{ padding: "14px 18px", background: "var(--lbtt-paper-2)", borderBottom: "1px solid var(--lbtt-rule)" }}>
            <input className="lbtt-input" placeholder="Nom du projet" value={newName} onChange={e => setNewName(e.target.value)} autoFocus />
            <div style={{ display: "flex", gap: 5, margin: "10px 0" }}>
              {["#c45a2e", "#2d4a3e", "#c9a24a", "#5a6f8a", "#8a6a4a", "#a14a3a"].map(c => (
                <button key={c} onClick={() => setNewColor(c)} style={{
                  width: 28, height: 28, background: c, cursor: "pointer",
                  border: newColor === c ? "2px solid var(--lbtt-ink)" : "1px solid var(--lbtt-rule)",
                }} />
              ))}
            </div>
            <div style={{ display: "flex", gap: 6 }}>
              <button className="lbtt-btn lbtt-btn-primary" onClick={() => {
                if (!newName.trim()) return;
                setProjs(ps => [...ps, { id: Date.now(), name: newName, color: newColor, archived: false }]);
                setNewName(""); setAdding(false);
              }}>Enregistrer</button>
              <button className="lbtt-btn lbtt-btn-ghost" onClick={() => setAdding(false)}>Annuler</button>
            </div>
          </div>
        ) : (
          <button onClick={() => setAdding(true)} style={{
            width: "100%", padding: "16px 18px", background: "transparent", border: "none",
            borderBottom: "1px solid var(--lbtt-rule)", textAlign: "left", cursor: "pointer",
            fontFamily: "var(--mono)", fontSize: 10.5, letterSpacing: "0.1em", textTransform: "uppercase",
            color: "var(--lbtt-muted)",
          }}>+ Nouveau projet</button>
        )}
      </div>
    </div>
  );
}

// ========== Mobile Login ==========
function MobileLogin() {
  return (
    <div className="lbtt-root" style={{
      minHeight: "100%", display: "flex", flexDirection: "column",
      justifyContent: "space-between", padding: "50px 24px 32px",
    }}>
      <div>
        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 20 }}>
          <div style={{ width: 14, height: 14, background: "var(--lbtt-accent)" }} />
          <div className="lbtt-mono" style={{ fontSize: 9, letterSpacing: "0.2em", textTransform: "uppercase" }}>LB — TIME TRACKER</div>
        </div>
        <div className="lbtt-serif" style={{ fontSize: 44, lineHeight: 0.95, textTransform: "lowercase", marginBottom: 14 }}>
          carnet<br/><span style={{ color: "var(--lbtt-accent)" }}>d'heures.</span>
        </div>
        <p style={{ color: "var(--lbtt-muted)", fontSize: 13, lineHeight: 1.5, maxWidth: 240 }}>
          Quatre créneaux par jour, une couleur par projet. Tout le reste suivra.
        </p>
      </div>
      <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
        <div>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Utilisateur</div>
          <input className="lbtt-input" defaultValue="adrien" />
        </div>
        <div>
          <div className="lbtt-label" style={{ marginBottom: 4 }}>Mot de passe</div>
          <input className="lbtt-input" type="password" placeholder="••••••••••" />
        </div>
        <button className="lbtt-btn lbtt-btn-primary" style={{ marginTop: 8 }}>Se connecter →</button>
        <div className="lbtt-mono" style={{ fontSize: 9, color: "var(--lbtt-muted)", textAlign: "center", marginTop: 4, letterSpacing: "0.12em" }}>
          V1.0 · HTTPS UNIQUEMENT
        </div>
      </div>
    </div>
  );
}

// ========== Mobile Setup ==========
function MobileSetup() {
  const [step, setStep] = uS(0);
  return (
    <div className="lbtt-root" style={{ minHeight: "100%", display: "flex", flexDirection: "column" }}>
      <div style={{ padding: "14px 18px 10px", borderBottom: "1px solid var(--lbtt-rule)" }}>
        <div className="lbtt-label">Installation · {step + 1}/4</div>
        <div className="lbtt-serif" style={{ fontSize: 24, lineHeight: 1, textTransform: "lowercase" }}>configuration.</div>
      </div>
      <div style={{ display: "flex", padding: "10px 18px", gap: 5 }}>
        {[0, 1, 2, 3].map(i => (
          <div key={i} style={{ flex: 1, height: 3, background: i <= step ? "var(--lbtt-accent)" : "var(--lbtt-paper-3)" }} />
        ))}
      </div>
      <div style={{ flex: 1, padding: "6px 18px" }}>
        {step === 0 && (
          <>
            <div className="lbtt-label" style={{ marginBottom: 4 }}>Utilisateur admin</div>
            <input className="lbtt-input" defaultValue="adrien" style={{ marginBottom: 10 }} />
            <div className="lbtt-label" style={{ marginBottom: 4 }}>Mot de passe</div>
            <input className="lbtt-input" type="password" defaultValue="testtest" />
            <p style={{ fontSize: 11.5, color: "var(--lbtt-muted)", marginTop: 10, lineHeight: 1.5 }}>
              Hash bcrypt stocké dans <span className="lbtt-mono">config.php</span> (chmod 0600).
            </p>
          </>
        )}
        {step === 1 && (
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
            <div style={{ gridColumn: "1 / -1" }}><div className="lbtt-label" style={{ marginBottom: 4 }}>Hôte</div><input className="lbtt-input" defaultValue="localhost" /></div>
            <div><div className="lbtt-label" style={{ marginBottom: 4 }}>Port</div><input className="lbtt-input" defaultValue="3306" /></div>
            <div><div className="lbtt-label" style={{ marginBottom: 4 }}>Base</div><input className="lbtt-input" defaultValue="lbtt" /></div>
            <div><div className="lbtt-label" style={{ marginBottom: 4 }}>User</div><input className="lbtt-input" defaultValue="lbtt" /></div>
            <div><div className="lbtt-label" style={{ marginBottom: 4 }}>Mdp</div><input className="lbtt-input" type="password" defaultValue="lbtt" /></div>
          </div>
        )}
        {step === 2 && (
          <>
            <div className="lbtt-label" style={{ marginBottom: 4 }}>Timezone</div>
            <input className="lbtt-input" defaultValue="Europe/Zurich" />
            <p style={{ fontSize: 11.5, color: "var(--lbtt-muted)", marginTop: 10, lineHeight: 1.5 }}>
              Utilisée pour l'agrégation quotidienne et les bornes de période.
            </p>
          </>
        )}
        {step === 3 && (
          <div style={{ textAlign: "center", paddingTop: 32 }}>
            <div style={{ width: 64, height: 64, margin: "0 auto 16px", background: "var(--lbtt-accent)", color: "#fff", display: "grid", placeItems: "center" }}>
              <span className="lbtt-serif" style={{ fontSize: 32 }}>✓</span>
            </div>
            <div className="lbtt-serif" style={{ fontSize: 20, marginBottom: 6 }}>Connexion établie.</div>
            <p style={{ fontSize: 12, color: "var(--lbtt-muted)" }}>Tables créées · config.php généré</p>
          </div>
        )}
      </div>
      <div style={{ padding: "14px 18px", borderTop: "1px solid var(--lbtt-rule)", display: "flex", gap: 6 }}>
        {step > 0 && step < 3 && <button className="lbtt-btn lbtt-btn-ghost" onClick={() => setStep(s => s - 1)}>Précédent</button>}
        <button className="lbtt-btn lbtt-btn-primary" style={{ flex: 1 }} onClick={() => setStep(s => Math.min(3, s + 1))}>
          {step === 2 ? "Tester et installer →" : step === 3 ? "Se connecter →" : "Continuer →"}
        </button>
      </div>
    </div>
  );
}

// ========== Mobile Year Heatmap ==========
function MobileHeatmap() {
  const year = 2026;
  const data = uM(() => {
    const out = {};
    for (let mm = 0; mm < 12; mm++) Object.assign(out, lbttGenerateEntries(year, mm));
    return out;
  }, []);
  const weeks = uM(() => {
    const out = [];
    const start = new Date(year, 0, 1);
    const offset = (start.getDay() + 6) % 7;
    let c = new Date(start);
    c.setDate(c.getDate() - offset);
    for (let w = 0; w < 53; w++) {
      const wk = [];
      for (let d = 0; d < 7; d++) { wk.push(new Date(c)); c.setDate(c.getDate() + 1); }
      out.push(wk);
    }
    return out;
  }, []);
  return (
    <div className="lbtt-root" style={{ minHeight: "100%", display: "flex", flexDirection: "column" }}>
      <div style={{ padding: "14px 18px 10px", borderBottom: "1px solid var(--lbtt-rule)" }}>
        <div className="lbtt-label">Année · {year}</div>
        <div className="lbtt-serif" style={{ fontSize: 28, lineHeight: 1, textTransform: "lowercase" }}>constellation.</div>
      </div>
      <div style={{ flex: 1, padding: "14px 10px", overflow: "auto" }}>
        <div style={{ display: "flex", gap: 2 }}>
          {weeks.map((w, wi) => (
            <div key={wi} style={{ display: "flex", flexDirection: "column", gap: 2 }}>
              {w.map((d, di) => {
                if (d.getFullYear() !== year) return <div key={di} style={{ width: 5.5, height: 15 }} />;
                const k = lbttKey(d.getFullYear(), d.getMonth(), d.getDate());
                const e = data[k] || {};
                return (
                  <div key={di} style={{ width: 5.5, height: 15, display: "flex", flexDirection: "column", gap: 0.5 }}>
                    {LBTT_SLOTS.map(s => {
                      const ee = e[s.key];
                      const p = ee && lbttProjectById(ee.projectId);
                      return <div key={s.key} style={{ flex: 1, background: p ? p.color : "var(--lbtt-paper-3)" }} />;
                    })}
                  </div>
                );
              })}
            </div>
          ))}
        </div>
        <div style={{ display: "flex", justifyContent: "space-between", marginTop: 8 }}>
          {["j","f","m","a","m","j","j","a","s","o","n","d"].map((mm, i) => (
            <div key={i} className="lbtt-mono" style={{ fontSize: 8, color: "var(--lbtt-muted)", textTransform: "uppercase" }}>{mm}</div>
          ))}
        </div>
        <div style={{ marginTop: 24 }}>
          <div className="lbtt-label" style={{ marginBottom: 8 }}>Chaque jour · 4 bandes (matin/après-midi/soir/nuit)</div>
          <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
            {LBTT_PROJECTS.filter(p => !p.archived).map(p => (
              <span key={p.id} className="lbtt-tag" style={{ fontSize: 10 }}>
                <span className="lbtt-tag-dot" style={{ background: p.color }} />{p.name}
              </span>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { MobileCalendar, MobileSummary, MobileProjects, MobileLogin, MobileSetup, MobileHeatmap });
