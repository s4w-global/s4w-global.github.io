const API_BASE = "https://api-s4w.wjf-tech.nl/api"; // Backend API host (GitHub Pages friendly)
/* S4W MVP (client-side demo)
   - language redirect (/index.html)
   - safety-map heat "demo" using canvas (dummy points)
   - report unsafe adds point + refreshes layer (localStorage)
   - dashboard: level switches + trend colored badges + legend
*/
(function(){
  const STORAGE_KEY = "s4w_reports_v1";
  const LANG_KEY = "s4w_lang_pref";

  function getLangFromBrowser(){
    const nav = (navigator.languages && navigator.languages.length) ? navigator.languages[0] : (navigator.language || "en");
    const lc = (nav || "en").toLowerCase();
    if(lc.startsWith("nl")) return "nl";
    return "en";
  }

  function setActiveNav(){
    const navLinks = document.querySelectorAll("[data-nav]");
    const path = (location.pathname || "").toLowerCase();
    navLinks.forEach(a=>{
      const href = (a.getAttribute("href")||"").toLowerCase();
      if(href && path.endsWith(href.replace("./",""))) a.classList.add("active");
      if(path.endsWith("/dashboard.html") && href.includes("dashboard")) a.classList.add("active");
      if(path.endsWith("/safety-map.html") && href.includes("safety-map")) a.classList.add("active");
      if(path.endsWith("/contact.html") && href.includes("contact")) a.classList.add("active");
      if(path.endsWith("/index.html") && (href.endsWith("/index.html") || href.endsWith("index.html"))) a.classList.add("active");
    });
  }

  // Root redirect: /index.html -> /nl/index.html or /en/index.html
  function maybeRedirectRoot(){
    const p = (location.pathname || "/").toLowerCase();
    const isRoot = (p === "/" || p.endsWith("/index.html"));
    // Only run on repo root, not inside /en or /nl
    if(!isRoot) return;

    const stored = localStorage.getItem(LANG_KEY);
    const lang = stored || getLangFromBrowser();
    localStorage.setItem(LANG_KEY, lang);

    // If already at /en/... or /nl/... do nothing
    if(p.includes("/en/") || p.includes("/nl/")) return;

    const target = `./${lang}/index.html`;
    location.replace(target);
  }

  function readReports(){
    try{
      const raw = localStorage.getItem(STORAGE_KEY);
      const arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    }catch(e){
      return [];
    }
  }
  function writeReports(arr){
    localStorage.setItem(STORAGE_KEY, JSON.stringify(arr.slice(0, 500))); // cap for demo
  }

  // Generate initial dummy points if empty
  function ensureSeedReports(){
    const existing = readReports();
    if(existing.length) return existing;

    const now = Date.now();
    const seed = [];
    // Points in "normalized canvas space" (0..1)
    const dummy = [
      {x:.22,y:.28,i:.9, cat:"Harassment"},
      {x:.75,y:.30,i:.7, cat:"Unsafe lighting"},
      {x:.58,y:.62,i:.8, cat:"Stalking"},
      {x:.40,y:.74,i:.6, cat:"Assault risk"},
      {x:.86,y:.75,i:.5, cat:"Pickpocketing"},
    ];
    dummy.forEach((d, idx)=>{
      seed.push({
        id: "seed_" + idx,
        ts: now - (idx*86400000),
        x: d.x, y:d.y,
        intensity: d.i,
        category: d.cat,
        note: "Demo report (client-side)"
      });
    });
    writeReports(seed);
    return seed;
  }

  // Heat canvas renderer
  function drawHeat(canvas, reports){
    if(!canvas) return;
    const ctx = canvas.getContext("2d");
    const rect = canvas.getBoundingClientRect();

    // Handle hiDPI
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.floor(rect.width * dpr);
    canvas.height = Math.floor(rect.height * dpr);
    ctx.scale(dpr, dpr);

    // background
    ctx.clearRect(0,0,rect.width,rect.height);

    // subtle grid
    ctx.globalAlpha = 0.18;
    ctx.strokeStyle = "rgba(159,176,195,.20)";
    const step = 48;
    for(let x=0; x<rect.width; x+=step){
      ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,rect.height); ctx.stroke();
    }
    for(let y=0; y<rect.height; y+=step){
      ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(rect.width,y); ctx.stroke();
    }
    ctx.globalAlpha = 1;

    // heat points: radial gradients
    reports.forEach(r=>{
      const x = r.x * rect.width;
      const y = r.y * rect.height;
      const radius = 80 * (0.6 + (r.intensity || 0.6));
      const grd = ctx.createRadialGradient(x,y, 0, x,y, radius);
      grd.addColorStop(0, "rgba(255,77,77,.28)");
      grd.addColorStop(0.55, "rgba(124,92,255,.20)");
      grd.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = grd;
      ctx.beginPath();
      ctx.arc(x,y,radius,0,Math.PI*2);
      ctx.fill();
    });

    // markers for reported points
    reports.slice(-60).forEach(r=>{
      const x = r.x * rect.width;
      const y = r.y * rect.height;
      ctx.fillStyle = "rgba(93,214,255,.85)";
      ctx.beginPath();
      ctx.arc(x,y, 3.3, 0, Math.PI*2);
      ctx.fill();
    });
  }

  function fmtDate(ts){
    const d = new Date(ts);
    const pad = n => String(n).padStart(2,"0");
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }


  const S4W_API_ENABLED = true;
const S4W_PUBLIC_CFG = { turnstile: { enabled:false, site_key:'', protect_reports:true, protect_panic:false } };

const S4W_TOKEN_CACHE = { token: null, exp: 0 }; // set false to use localStorage only
const S4W_API_REPORT_ENDPOINT = 'https://api-s4w.wjf-tech.nl/api/report.php';
const S4W_API_REPORTS_ENDPOINT = 'https://api-s4w.wjf-tech.nl/api/reports.php';

const REPORT_MASK_METERS = 50;   // precise reports
const PANIC_MASK_METERS  = 300;  // privacy for panic


function s4wGetApiKey(){
  try { return sessionStorage.getItem('s4w_api_key') || localStorage.getItem('s4w_api_key') || ''; } catch(e){ return ''; }
}
function s4wSetApiKey(k){
  try { sessionStorage.setItem('s4w_api_key', k); } catch(e){}
  try { localStorage.setItem('s4w_api_key', k); } catch(e){}
}
function s4wEnsureApiKey(){
  let k = s4wGetApiKey();
  if(k) return k;
  k = (window.prompt('Enter your S4W API key (e.g. S4W-RTM-...)') || '').trim();
  if(k){ s4wSetApiKey(k); }
  return k;
}


function mountSafetyMap(){
    const mapEl = document.getElementById("map");
    const list = document.getElementById("reportList");
    const stat = document.getElementById("reportStats");
    const btnPanic = document.getElementById("btnPanic");
    const btnReport = document.getElementById("btnReport");
    const modal = document.getElementById("modalBackdrop");
    if(!mapEl) return;

    if(typeof window.L === "undefined"){
      if(stat) stat.textContent = "Map failed to load (Leaflet missing).";
      toast("Leaflet not loaded. Check your internet / CDN access.");
      return;
    }

    function maskWithinMeters(lat, lng, meters){
      const r = meters * Math.sqrt(Math.random());
      const theta = Math.random() * 2 * Math.PI;
      const dLat = (r * Math.cos(theta)) / 111320;
      const dLng = (r * Math.sin(theta)) / (111320 * Math.cos(lat * Math.PI / 180));
      return { lat: lat + dLat, lng: lng + dLng };
    }

    const map = L.map(mapEl, { zoomControl:true, preferCanvas:true });
    const style = await getMapStyle();
  let tileUrl = "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png";
  let attrib = '&copy; OpenStreetMap contributors &copy; CARTO';
  if(style==='normal') tileUrl = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
  if(style==='semi') tileUrl = "https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png";
  L.tileLayer(tileUrl, { maxZoom: 19, attribution: attrib }).addTo(map);

    const markers = L.layerGroup().addTo(map);
    const heat = (typeof L.heatLayer === "function") ? L.heatLayer([], { radius: 28, blur: 22, maxZoom: 18 }) : null;
    if(heat) heat.addTo(map);

    

async function getTokenCached(){
  const now = Date.now();
  if(S4W_TOKEN_CACHE.token && now < (S4W_TOKEN_CACHE.exp - 10000)) return S4W_TOKEN_CACHE.token;
  const res = await fetch('https://api-s4w.wjf-tech.nl/api/token.php');
  if(!res.ok) throw new Error('token');
  const data = await res.json();
  const expMs = Date.parse(data.expires_at);
  S4W_TOKEN_CACHE.token = data.token;
  S4W_TOKEN_CACHE.exp = isFinite(expMs) ? expMs : (now + 14*60*1000);
  return data.token;
}


async function getPublicCfg(){
  try{
    const r = await fetch('https://api-s4w.wjf-tech.nl/api/public_config.php');
    if(r.ok){
      const cfg = await r.json();
      if(cfg && cfg.turnstile) S4W_PUBLIC_CFG.turnstile = cfg.turnstile;
    }
  }catch(e){}
}


function loadTurnstileScript(){
  return new Promise((resolve) => {
    if(window.turnstile) return resolve(true);
    const s = document.createElement('script');
    s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    s.async = true; s.defer = true;
    s.onload = () => resolve(true);
    s.onerror = () => resolve(false);
    document.head.appendChild(s);
  });
}

async function getMapStyle(){
  try{
    const r = await fetch('https://api-s4w.wjf-tech.nl/api/settings.php');
    if(!r.ok) return 'dark';
    const s = await r.json();
    return (s.map_style || 'dark');
  }catch(e){
    return 'dark';
  }
}

  // Turnstile token (if enabled for this action)
  if(S4W_PUBLIC_CFG.turnstile && S4W_PUBLIC_CFG.turnstile.enabled){
    const protect = (payload.type==='panic') ? S4W_PUBLIC_CFG.turnstile.protect_panic : S4W_PUBLIC_CFG.turnstile.protect_reports;
    if(protect){
      const t = window.__s4wTurnstileToken || '';
      if(!t) throw new Error('turnstile_required');
      payload.turnstile_token = t;
    }
  }


  const res = await fetch(S4W_API_REPORT_ENDPOINT, {
    method: 'POST',
    headers: (function(){ const h={'Content-Type':'application/json'}; const k=s4wEnsureApiKey(); if(k) h['X-API-Key']=k; return h; })(),
    body: JSON.stringify(payload)
  });
  if(!res.ok) throw new Error('api');
  return await res.json().catch(()=>({status:'ok'}));
}

async function refreshFromApi(){
  const token = await getTokenCached();
    const res = await fetch(S4W_API_REPORTS_ENDPOINT + '?since=7d', { headers: { 'Authorization': 'Bearer ' + token } });
  if(!res.ok) throw new Error('api');
  const cells = await res.json();
  // Build fake reports array for table + markers
  const reports = [];
  cells.forEach(c=>{
    const n = c.count || 0;
    for(let i=0;i<Math.min(n, 20);i++){
      reports.push({
        ts: Date.now(),
        lat: c.lat,
        lng: c.lng,
        intensity: Math.min(1, 0.45 + (n/10)),
        category: Object.keys(c.categories||{})[0] || 'report',
        note: 'Aggregated cell'
      });
    }
  });
  // Reuse existing renderer by temporary injecting
  markers.clearLayers();
  const pts = [];
  cells.forEach(c=>{
    pts.push([c.lat, c.lng, Math.min(1, 0.35 + (c.count/10))]);
    const mk = L.circleMarker([c.lat, c.lng], { radius: 4, weight: 0, fillOpacity: 0.9 });
    mk.bindTooltip(`${escapeHtml('Area cell')}<br><span class="small muted">${c.count} reports (7d)</span>`, {direction:"top"});
    mk.addTo(markers);
  });
  if(heat) heat.setLatLngs(pts);
  if(stat) stat.textContent = `${cells.reduce((a,b)=>a+(b.count||0),0)} total reports (7d)`;
  if(list){
    list.innerHTML = cells.slice(0,30).map(c=>{
      return `<tr><td class="small muted">—</td><td>${escapeHtml(c.geohash)}</td><td class="small muted">${c.count} reports (7d)</td></tr>`;
    }).join('');
  }
}

async function renderTurnstileInModal(actionType){
  // Reset token each time modal opens
  window.__s4wTurnstileToken = '';
  const ts = S4W_PUBLIC_CFG.turnstile || {enabled:false};
  if(!ts.enabled) return;

  const protect = (actionType==='panic') ? ts.protect_panic : ts.protect_reports;
  if(!protect) return;

  const ok = await loadTurnstileScript();
  if(!ok || !window.turnstile) return;

  const el = document.getElementById('s4w-turnstile');
  if(!el) return;
  el.innerHTML = '';
  try{
    window.turnstile.render(el, {
      sitekey: ts.site_key,
      theme: 'dark',
      callback: (token) => { window.__s4wTurnstileToken = token; },
      'expired-callback': () => { window.__s4wTurnstileToken = ''; }
    });
  }catch(e){}
}
function ensureSeedGeoReports(){
      const existing = readReports();
      if(existing.length) return existing;
      const base = { lat: 52.3702, lng: 4.8952 }; // Amsterdam
      const now = Date.now();
      const seed = [];
      const cats = ["Harassment","Unsafe lighting","Stalking","Assault risk","Pickpocketing"];
      for(let i=0;i<6;i++){
        const p = maskWithinMeters(base.lat, base.lng, 1200);
        seed.push({
          id:"seed_" + i,
          ts: now - (i*86400000),
          lat: p.lat,
          lng: p.lng,
          intensity: 0.65 + (i%3)*0.08,
          category: cats[i%cats.length],
          note: "Demo report (client-side)",
          privacy: { maskedWithinMeters: 1200 }
        });
      }
      writeReports(seed);
      return seed;
    }

    function refresh(){
      const reports = readReports();
      if(stat) stat.textContent = `${reports.length} total reports (demo)`;
      markers.clearLayers();
      const pts = [];
      reports.forEach(r=>{
        // migrate old normalized x/y if present
        if((typeof r.lat !== "number" || typeof r.lng !== "number") && typeof r.x === "number" && typeof r.y === "number"){
          const c = map.getCenter();
          r.lat = c.lat + (r.y - 0.5) * 0.02;
          r.lng = c.lng + (r.x - 0.5) * 0.02;
        }
        if(typeof r.lat === "number" && typeof r.lng === "number"){
          if(heat) pts.push([r.lat, r.lng, (r.intensity || 0.6)]);
          const mk = L.circleMarker([r.lat, r.lng], { radius: 4, weight: 0, fillOpacity: 0.9 });
          mk.bindTooltip(`${escapeHtml(r.category||"Unspecified")}<br><span class="small muted">${fmtDate(r.ts)}</span>`, {direction:"top"});
          mk.addTo(markers);
        }
      });
      if(heat) heat.setLatLngs(pts);

      if(list){
        const items = reports.slice().reverse().slice(0, 30);
        list.innerHTML = items.map(r=>{
          const cat = escapeHtml(r.category || "Unspecified");
          const note = escapeHtml((r.note||"").slice(0, 80));
          return `<tr>
            <td class="small muted">${fmtDate(r.ts)}</td>
            <td>${cat}</td>
            <td class="small muted">${note || "—"}</td>
          </tr>`;
        }).join("");
      }
    }

    function setInputs(lat, lng){
      const xInp = document.getElementById("inpX");
      const yInp = document.getElementById("inpY");
      if(xInp) xInp.value = Number(lat).toFixed(6);
      if(yInp) yInp.value = Number(lng).toFixed(6);
    }

    map.on("click", (e)=>{
      setInputs(e.latlng.lat, e.latlng.lng);
      toast("Location selected. Choose a category and submit.");
    });

    map.setView([52.3702, 4.8952], 13);

    if(navigator.geolocation){
      navigator.geolocation.getCurrentPosition(
        (pos)=>{
          map.setView([pos.coords.latitude, pos.coords.longitude], 16);
          setInputs(pos.coords.latitude, pos.coords.longitude);
        },
        ()=>toast("GPS not available/allowed. Click map to select a point."),
        { enableHighAccuracy:true, timeout:9000, maximumAge:60000 }
      );
    }

    if(btnPanic){
      btnPanic.addEventListener("click", ()=>{
        toast(btnPanic.getAttribute("data-toast") || "Panic button placeholder (MVP).");
      });
    }

    if(btnReport && modal){
      btnReport.addEventListener("click", ()=> openModal(modal));
      modal.addEventListener("click", (e)=>{ if(e.target === modal) closeModal(modal); });
      const close = document.getElementById("modalClose");
      if(close) close.addEventListener("click", ()=>closeModal(modal));

      const submit = document.getElementById("modalSubmit");
      if(submit) submit.addEventListener("click", ()=>{
        const lat = parseFloat((document.getElementById("inpX")||{}).value || "");
        const lng = parseFloat((document.getElementById("inpY")||{}).value || "");
        const cat = (document.getElementById("inpCat")||{}).value || "Unspecified";
        const note = (document.getElementById("inpNote")||{}).value || "";

        if(!(isFinite(lat) && isFinite(lng) && Math.abs(lat)<=90 && Math.abs(lng)<=180)){
          toast("Select a location on the map (or enable GPS).");
          return;
        }

        const masked = maskWithinMeters(lat, lng, REPORT_MASK_METERS);
        const newItem = {
          id: "r_" + Math.random().toString(16).slice(2),
          ts: Date.now(),
          lat: masked.lat,
          lng: masked.lng,
          intensity: 0.78,
          category: cat,
          note: note.slice(0,200),
          privacy: { maskedWithinMeters: REPORT_MASK_METERS }
        };

        closeModal(modal);
      // Send to backend (live MVP)
      if(S4W_API_ENABLED){
        submitReportToApi({lat: lat, lng: lng, category: cat, type: 'report'}).then(()=>{
          refreshFromApi();
          toast('Report added (server).');
        }).catch(()=>{
          toast('API error. Saved locally (fallback).');
          const all = readReports(); all.push(newItem); writeReports(all); refresh();
        });
      } else {
        const all = readReports(); all.push(newItem); writeReports(all); refresh();
      }
        const noteEl = document.getElementById("inpNote");
        if(noteEl) noteEl.value = "";
        refresh();
        toast("Report added (masked ~300m).");
      });
    }

    ensureSeedGeoReports();
    setTimeout(()=>{ map.invalidateSize(); if(S4W_API_ENABLED){ refreshFromApi().catch(()=>refresh()); } else { refresh(); } }, 150);
  }

  function openModal(backdrop){

    backdrop.style.display = "flex";
  }
  function closeModal(backdrop){
    backdrop.style.display = "none";
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (c)=>({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"
    }[c]));
  }

  // Simple toast
  function toast(msg){
    let el = document.getElementById("toast");
    if(!el){
      el = document.createElement("div");
      el.id = "toast";
      el.style.position = "fixed";
      el.style.left = "50%";
      el.style.bottom = "22px";
      el.style.transform = "translateX(-50%)";
      el.style.padding = "10px 12px";
      el.style.border = "1px solid rgba(159,176,195,.35)";
      el.style.borderRadius = "14px";
      el.style.background = "rgba(11,15,20,.85)";
      el.style.backdropFilter = "blur(10px)";
      el.style.color = "white";
      el.style.zIndex = "999";
      el.style.boxShadow = "0 18px 50px rgba(0,0,0,.45)";
      el.style.maxWidth = "min(560px, 92vw)";
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.opacity = "1";
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(()=>{ el.style.opacity="0"; }, 2600);
  }

  // Dashboard demo
  function mountDashboard(){
    const root = document.getElementById("dashRoot");
    if(!root) return;

    const levelButtons = Array.from(document.querySelectorAll("[data-level]"));
    const tbody = document.getElementById("dashBody");
    const title = document.getElementById("dashTitle");
    const subtitle = document.getElementById("dashSubtitle");

    // Demo data
    const data = {
      municipality: [
        {name:"Amsterdam", reports:132, trend:"strongup"},
        {name:"Rotterdam", reports:98, trend:"up"},
        {name:"Utrecht", reports:64, trend:"stable"},
        {name:"Den Haag", reports:73, trend:"up"},
      ],
      region: [
        {name:"Randstad", reports:412, trend:"strongup"},
        {name:"Noord-Holland", reports:188, trend:"up"},
        {name:"Zuid-Holland", reports:215, trend:"up"},
        {name:"Utrecht Province", reports:92, trend:"stable"},
      ],
      country: [
        {name:"Netherlands", reports:1240, trend:"up"},
        {name:"Belgium", reports:760, trend:"stable"},
        {name:"Germany", reports:1510, trend:"strongup"},
        {name:"France", reports:1390, trend:"up"},
      ],
      continent: [
        {name:"Europe", reports:6820, trend:"up"},
        {name:"North America", reports:5410, trend:"stable"},
        {name:"South America", reports:2630, trend:"up"},
        {name:"Africa", reports:1980, trend:"strongup"},
      ]
    };

    const labels = {
      municipality: {title: root.getAttribute("data-t-muni") || "Municipalities", subtitle: root.getAttribute("data-s-muni") || "Local demo overview"},
      region:       {title: root.getAttribute("data-t-reg")  || "Regions", subtitle: root.getAttribute("data-s-reg")  || "Regional aggregation (demo)"},
      country:      {title: root.getAttribute("data-t-cou")  || "Countries", subtitle: root.getAttribute("data-s-cou")  || "National aggregation (demo)"},
      continent:    {title: root.getAttribute("data-t-con")  || "Continents", subtitle: root.getAttribute("data-s-con")  || "Continental overview (demo)"},
    };

    const trendSymbol = (t)=> t==="strongup" ? "▲▲" : (t==="up" ? "▲" : "➝");
    const trendClass  = (t)=> t==="strongup" ? "trend-upstrong" : (t==="up" ? "trend-up" : "trend-flat");

    let active = "municipality";

    function render(){
      levelButtons.forEach(b=>{
        b.classList.toggle("active", b.getAttribute("data-level")===active);
      });
      if(title) title.textContent = labels[active].title;
      if(subtitle) subtitle.textContent = labels[active].subtitle;

      const rows = data[active].slice().sort((a,b)=>b.reports-a.reports);
      tbody.innerHTML = rows.map(r=>`
        <tr>
          <td>${escapeHtml(r.name)}</td>
          <td class="right">${r.reports}</td>
          <td class="right">
            <span class="trend-badge ${trendClass(r.trend)}">${trendSymbol(r.trend)}</span>
          </td>
        </tr>
      `).join("");
    }

    levelButtons.forEach(b=>{
      b.addEventListener("click", ()=>{
        active = b.getAttribute("data-level");
        render();
      });
    });

    render();
  }

  // Language switcher on pages (optional)
  function mountLangSwitcher(){
    const btn = document.getElementById("langToggle");
    if(!btn) return;
    btn.addEventListener("click", ()=>{
      const current = localStorage.getItem(LANG_KEY) || getLangFromBrowser();
      const next = current === "nl" ? "en" : "nl";
      localStorage.setItem(LANG_KEY, next);

      // Replace /en/ with /nl/ or vice versa
      const p = location.pathname;
      const replaced = p.includes("/en/") ? p.replace("/en/","/nl/") : (p.includes("/nl/") ? p.replace("/nl/","/en/") : p);
      location.href = replaced;
    });
  }

  document.addEventListener("DOMContentLoaded", ()=>{
    maybeRedirectRoot();
    setActiveNav();
    mountLangSwitcher();
    mountSafetyMap();
    mountDashboard();
  });
})();