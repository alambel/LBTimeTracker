// LBTimeTracker — shared data model, projects, sample entries, helpers.
// French to match the original app.

const LBTT_PROJECTS = [
  { id: 1, name: "Client Meridian",   color: "#c45a2e", archived: false },
  { id: 2, name: "R&D interne",        color: "#2d4a3e", archived: false },
  { id: 3, name: "Refonte site",       color: "#c9a24a", archived: false },
  { id: 4, name: "Formation",          color: "#5a6f8a", archived: false },
  { id: 5, name: "Admin & factu.",     color: "#8a6a4a", archived: false },
  { id: 6, name: "Congés",             color: "#a8a094", archived: false },
  { id: 7, name: "Ancien — Archives",  color: "#b3b0a8", archived: true  },
];

const LBTT_SLOTS = [
  { key: "AM", label: "Matin",      hours: "08–12" },
  { key: "PM", label: "Après-midi", hours: "13–17" },
  { key: "EV", label: "Soir",       hours: "17–21" },
  { key: "NT", label: "Nuit",       hours: "22–02" },
];

// Deterministic pseudo-random sample entries for a month.
// Returns map: "YYYY-MM-DD" -> { AM?: {projectId, note}, PM?: ..., ... }
function lbttGenerateEntries(year, month /* 0-indexed */) {
  const out = {};
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const activeProjects = LBTT_PROJECTS.filter(p => !p.archived);
  // simple seeded rng
  let seed = year * 100 + month;
  const rand = () => {
    seed = (seed * 9301 + 49297) % 233280;
    return seed / 233280;
  };
  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(year, month, d);
    const dow = date.getDay(); // 0=Sun, 6=Sat
    const key = `${year}-${String(month + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
    out[key] = {};
    // Weekends mostly empty
    const slotFillProb = dow === 0 || dow === 6 ? 0.12 : 0.88;
    const eveFillProb  = dow === 0 || dow === 6 ? 0.05 : 0.22;
    const ntFillProb   = 0.04;
    LBTT_SLOTS.forEach(slot => {
      let prob = slotFillProb;
      if (slot.key === "EV") prob = eveFillProb;
      if (slot.key === "NT") prob = ntFillProb;
      if (rand() < prob) {
        const pr = activeProjects[Math.floor(rand() * activeProjects.length)];
        out[key][slot.key] = { projectId: pr.id, note: "" };
      }
    });
  }
  // Seed a few notes
  const noteCandidates = [
    "Sync avec l'équipe",
    "Livrable v2",
    "Appel client — brief",
    "Debug prod",
    "Atelier roadmap",
    "Revue PR",
    "Rédaction doc",
  ];
  let notesLeft = 6;
  for (const k of Object.keys(out)) {
    if (notesLeft <= 0) break;
    const entry = out[k];
    const slotKeys = Object.keys(entry);
    if (slotKeys.length) {
      const pick = slotKeys[Math.floor(rand() * slotKeys.length)];
      entry[pick].note = noteCandidates[Math.floor(rand() * noteCandidates.length)];
      notesLeft--;
    }
  }
  return out;
}

function lbttProjectById(id) {
  return LBTT_PROJECTS.find(p => p.id === id);
}

// Localized French month + day names
const LBTT_MONTHS_FR = [
  "janvier", "février", "mars", "avril", "mai", "juin",
  "juillet", "août", "septembre", "octobre", "novembre", "décembre",
];
const LBTT_DAYS_FR_SHORT = ["L", "M", "M", "J", "V", "S", "D"]; // Mon-first

// Build month grid starting on Monday
function lbttMonthGrid(year, month) {
  const first = new Date(year, month, 1);
  // Monday = 0 in our grid
  const jsDow = first.getDay(); // 0=Sun
  const offset = (jsDow + 6) % 7; // Mon=0..Sun=6
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < offset; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);
  while (cells.length % 7 !== 0) cells.push(null);
  return cells;
}

function lbttIsToday(y, m, d) {
  const now = new Date();
  return now.getFullYear() === y && now.getMonth() === m && now.getDate() === d;
}

function lbttKey(y, m, d) {
  return `${y}-${String(m + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
}

// Aggregate: hours per project across entries map.
// Each filled slot = 0.5 day (demi-journée). 4 slots/day total.
function lbttAggregate(entries) {
  const byProject = {}; // id -> halfDays
  let totalHalfDays = 0;
  for (const k of Object.keys(entries)) {
    const e = entries[k];
    for (const slotKey of Object.keys(e)) {
      const pid = e[slotKey].projectId;
      byProject[pid] = (byProject[pid] || 0) + 1;
      totalHalfDays++;
    }
  }
  return { byProject, totalHalfDays };
}

Object.assign(window, {
  LBTT_PROJECTS, LBTT_SLOTS,
  lbttGenerateEntries, lbttProjectById,
  LBTT_MONTHS_FR, LBTT_DAYS_FR_SHORT,
  lbttMonthGrid, lbttIsToday, lbttKey, lbttAggregate,
});
