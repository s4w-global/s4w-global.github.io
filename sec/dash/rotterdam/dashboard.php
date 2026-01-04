<?php
// Rotterdam dashboard (restricted): requires POC cookie to mint a token.
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Gemeente Dashboard — Rotterdam • S4W</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <header class="topbar">
    <div class="wrap">
      <div class="brand">S4W</div>
      <div class="spacer"></div>
      <span class="badge">Rotterdam</span>
    </div>
  </header>

  <main class="wrap">
    <h1>Gemeente Dashboard — Rotterdam</h1>
    <p class="muted">Dit dashboard is niet gelinkt vanuit de publieke pagina’s. Toegang is beperkt (POC).</p>

    <div class="card">
      <div class="card-h">
        <h2>Gebieden (laatste 7 dagen)</h2>
        <div class="spacer"></div>
        <button id="btnPdf" class="btn">Export naar PDF</button>
      </div>

      <div id="status" class="muted">Laden…</div>

      <div class="table-wrap" style="margin-top:12px">
        <table class="table">
          <thead>
            <tr>
              <th>Gebied</th>
              <th>Meldingen</th>
              <th>Δ t.o.v. vorige 7d</th>
              <th>Trend</th>
            </tr>
          </thead>
          <tbody id="rows"></tbody>
        </table>
      </div>

      <div class="legend muted" style="margin-top:12px">
        <b>Legenda:</b>
        <span class="trend t-strong">▲▲</span> sterke stijging ·
        <span class="trend t-up">▲</span> stijging ·
        <span class="trend t-flat">➝</span> stabiel
      </div>
    </div>
  </main>

  <script>
  (function(){
    const rowsEl = document.getElementById('rows');
    const statusEl = document.getElementById('status');
    const btnPdf = document.getElementById('btnPdf');

    function trendBadge(trend){
      if(trend==='upup') return '<span class="badge trend t-strong">▲▲</span>';
      if(trend==='up') return '<span class="badge trend t-up">▲</span>';
      return '<span class="badge trend t-flat">➝</span>';
    }

    async function getToken(){
      const res = await fetch('/api/token.php');
      if(!res.ok) throw new Error('token');
      const data = await res.json();
      return data.token;
    }

    async function load(){
      try{
        const token = await getToken();
        const res = await fetch('/api/rotterdam_areas.php', {
          headers: { 'Authorization': 'Bearer ' + token }
        });
        if(!res.ok) throw new Error('dash');
        const data = await res.json();
        rowsEl.innerHTML = data.areas.map(a => `
          <tr>
            <td><b>${a.area}</b></td>
            <td>${a.last7}</td>
            <td class="muted">${a.delta >=0 ? '+'+a.delta : a.delta}</td>
            <td>${trendBadge(a.trend)}</td>
          </tr>
        `).join('');
        statusEl.textContent = 'Bijgewerkt.';
      }catch(e){
        statusEl.textContent = 'Geen toegang. Vraag een POC-link aan.';
        rowsEl.innerHTML = '<tr><td colspan="4" class="muted">Toegang vereist.</td></tr>';
      }
    }

    btnPdf.addEventListener('click', ()=> window.print());
    load();
  })();
  </script>
</body>
</html>