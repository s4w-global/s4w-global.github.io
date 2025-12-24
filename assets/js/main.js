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

  function mountSafetyMap(){
    const canvas = document.getElementById("heatCanvas");
    const list = document.getElementById("reportList");
    const stat = document.getElementById("reportStats");
    const btnPanic = document.getElementById("btnPanic");
    const btnReport = document.getElementById("btnReport");
    const modal = document.getElementById("modalBackdrop");

    if(!canvas) return;

    let reports = ensureSeedReports();
    const refresh = ()=>{
      reports = readReports();
      drawHeat(canvas, reports);
      if(stat) stat.textContent = `${reports.length} total reports (demo)`;
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
    };

    // Click to set location (normalized) for report
    canvas.addEventListener("click", (e)=>{
      const rect = canvas.getBoundingClientRect();
      const nx = (e.clientX - rect.left) / rect.width;
      const ny = (e.clientY - rect.top) / rect.height;
      const xInp = document.getElementById("inpX");
      const yInp = document.getElementById("inpY");
      if(xInp && yInp){
        xInp.value = nx.toFixed(4);
        yInp.value = ny.toFixed(4);
      }
    });

    window.addEventListener("resize", ()=>refresh());

    if(btnPanic){
      btnPanic.addEventListener("click", ()=>{
        toast(btnPanic.getAttribute("data-toast") || "Panic button placeholder (MVP).");
      });
    }

    if(btnReport && modal){
      btnReport.addEventListener("click", ()=> openModal(modal));
      modal.addEventListener("click", (e)=>{
        if(e.target === modal) closeModal(modal);
      });
      const close = document.getElementById("modalClose");
      if(close) close.addEventListener("click", ()=>closeModal(modal));
      const submit = document.getElementById("modalSubmit");
      if(submit) submit.addEventListener("click", ()=>{
        const x = parseFloat((document.getElementById("inpX")||{}).value || "");
        const y = parseFloat((document.getElementById("inpY")||{}).value || "");
        const cat = (document.getElementById("inpCat")||{}).value || "Unspecified";
        const note = (document.getElementById("inpNote")||{}).value || "";
        if(!(x>=0 && x<=1 && y>=0 && y<=1)){
          toast("Pick a location on the map (or set X/Y between 0 and 1).");
          return;
        }
        const newItem = {
          id: "r_" + Math.random().toString(16).slice(2),
          ts: Date.now(),
          x, y,
          intensity: 0.65,
          category: cat,
          note: note.slice(0, 200)
        };
        const all = readReports();
        all.push(newItem);
        writeReports(all);
        closeModal(modal);
        (document.getElementById("inpNote")||{}).value = "";
        refresh();
        toast("Report added (client-side demo).");
      });
    }

    refresh();
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
