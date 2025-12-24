/* ==========================================================
   S4W – MVP Core Script (Dark Theme)
   - Language auto-detect (NL/EN)
   - Redirect from root to /nl/ or /en/
   - Heatmap demo loader + report unsafe flow (client-side)
   ========================================================== */

(function () {
    // --- Detect browser language safely
    function detectLang() {
        const lang = (navigator.language || navigator.userLanguage || "en").toLowerCase();
        if (lang.startsWith("nl")) return "nl";
        return "en"; // fallback
    }

    // --- Redirect helper
    function redirectToLangIfRoot() {
        const path = window.location.pathname;

        // If we are already on /en or /nl, do nothing
        if (path.includes("/en/") || path.includes("/nl/")) return;

        // If we are at root "/" or "/index.html" -> redirect
        if (path === "/" || path.endsWith("/index.html")) {
            const targetLang = detectLang();
            window.location.replace(`/${targetLang}/index.html`);
        }
    }

    redirectToLangIfRoot();
})();


// ==========================================================
// HEATMAP + REPORTING (client-side demo)
// ==========================================================

// Dummy dataset (base seed)
let s4wReports = [
    { lat: 52.3676, lng: 4.9041, label: "Amsterdam", type: "Harassment", level: "Medium" },
    { lat: 51.9244, lng: 4.4777, label: "Rotterdam", type: "Unsafe area", level: "High" },
    { lat: 52.0705, lng: 4.3007, label: "Den Haag", type: "Suspicious behaviour", level: "Low" }
];

// Helper: render heatmap dummy as a styled list + visual blocks
function renderHeatmap() {
    const container = document.getElementById("heatmap");
    if (!container) return;

    // Build a simple “heatmap-like” UI representation
    let html = `
        <div style="padding:18px;">
            <div style="color:#31C8D9; font-weight:bold; margin-bottom:10px;">
                Heatmap loaded (MVP demo)
            </div>
            <div style="font-size:13px; color:#bbb; margin-bottom:14px;">
                Demo only — in production, reports are clustered & blurred (privacy-by-design).
            </div>
            <div style="display:grid; gap:10px;">
    `;

    s4wReports.slice().reverse().forEach((r, idx) => {
        const riskColor =
            r.level === "High" ? "#FF4455" :
            r.level === "Medium" ? "#FFA640" :
            "#2BCB89";

        html += `
            <div style="border:1px solid #333; background:#14161D; padding:12px; border-radius:6px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="color:#F4F2EE; font-weight:bold;">${r.label}</div>
                    <div style="color:${riskColor}; font-weight:bold;">${r.level}</div>
                </div>
                <div style="margin-top:6px; font-size:13px; color:#bbb;">
                    Type: ${r.type} • Lat: ${r.lat.toFixed(4)} • Lng: ${r.lng.toFixed(4)}
                </div>
            </div>
        `;
    });

    html += `
            </div>
        </div>
    `;

    container.innerHTML = html;
}

// Hook for the “Load Heatmap” button
function s4wLoadHeatmap() {
    renderHeatmap();
}


// ==========================================================
// REPORT UNSAFE LOCATION (client-side demo)
// ==========================================================

function s4wReportUnsafe(language = "en") {
    // Very lightweight “modal” via prompts for MVP speed.
    // Later: replace with real modal UI.

    const text = {
        en: {
            title: "Report Unsafe Location (Demo)",
            location: "Location name (e.g. street / area / city):",
            type: "Incident type (e.g. harassment, stalking, unsafe area):",
            level: "Risk level (Low / Medium / High):",
            ok: "Thanks — report added to the heatmap (demo)."
        },
        nl: {
            title: "Meld Onveilige Locatie (Demo)",
            location: "Locatienaam (bijv. straat / gebied / stad):",
            type: "Type melding (bijv. intimidatie, stalking, onveilig gebied):",
            level: "Risiconiveau (Low / Medium / High):",
            ok: "Dank — melding is toegevoegd aan de heatmap (demo)."
        }
    };

    const t = text[language] || text.en;

    const loc = prompt(`${t.title}\n\n${t.location}`);
    if (!loc) return;

    const type = prompt(`${t.title}\n\n${t.type}`) || "Unsafe area";
    const levelInput = (prompt(`${t.title}\n\n${t.level}`) || "Medium").toLowerCase();

    let level = "Medium";
    if (levelInput.includes("high")) level = "High";
    if (levelInput.includes("low")) level = "Low";

    // Create pseudo coordinates around NL for demo (privacy-like randomization)
    // This keeps it “feeling real” without needing an actual map integration yet.
    const baseLat = 52.0 + (Math.random() * 1.0);
    const baseLng = 4.0 + (Math.random() * 2.0);

    s4wReports.push({
        lat: baseLat,
        lng: baseLng,
        label: loc,
        type: type,
        level: level
    });

    alert(t.ok);
    renderHeatmap();
}


// ==========================================================
// DASHBOARD (dummy data switcher - optional)
// ==========================================================

function s4wSwitchDashboard(level) {
    const table = document.getElementById("dashboardTableBody");
    if (!table) return;

    const data = {
        municipality: [
            { area: "Amsterdam", reports: 41, trend: "▲" },
            { area: "Rotterdam", reports: 32, trend: "▲▲" },
            { area: "Utrecht", reports: 18, trend: "➝" }
        ],
        region: [
            { area: "Randstad", reports: 108, trend: "▲" },
            { area: "Noord-Holland", reports: 67, trend: "▲" },
            { area: "Zuid-Holland", reports: 79, trend: "▲▲" }
        ],
        country: [
            { area: "Netherlands", reports: 245, trend: "▲" },
            { area: "Belgium", reports: 133, trend: "➝" },
            { area: "Germany", reports: 390, trend: "▲▲" }
        ],
        continent: [
            { area: "Europe", reports: 870, trend: "▲" },
            { area: "North America", reports: 620, trend: "➝" },
            { area: "South America", reports: 510, trend: "▲" }
        ]
    };

    const rows = data[level] || data.municipality;

    table.innerHTML = rows.map(r => `
        <tr>
            <td>${r.area}</td>
            <td>${r.reports}</td>
            <td>${r.trend}</td>
        </tr>
    `).join("");
}
