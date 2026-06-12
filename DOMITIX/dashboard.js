// dashboard.js — Live polling v2

const POLL_INTERVAL = 5000;

function updateClock() {
  const now = new Date();
  const pad = n => String(n).padStart(2,'0');
  const el  = document.getElementById('clock');
  if (el) el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
  const fe = document.getElementById('footer-time');
  if (fe) fe.textContent = now.toLocaleDateString('fr-FR',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
}

function renderBadge(status) {
  const map = {
    success: '<span class="badge badge-success">ACCESS OK</span>',
    fail:    '<span class="badge badge-fail">DENIED</span>',
    lock:    '<span class="badge badge-lock">LOCKED</span>',
  };
  return map[status] || `<span class="badge">${status}</span>`;
}

function fetchDashboard() {
  fetch('fetch_data.php?action=dashboard')
    .then(r => r.json())
    .then(data => {
      const temp = parseFloat(data.temperature);
      const hum  = parseFloat(data.humidity);
      const volt = parseFloat(data.voltage);

      const set = (id, html) => { const el = document.getElementById(id); if(el) el.innerHTML = html; };
      set('temperature', isNaN(temp) ? '--<span class="unit">°C</span>' : `${temp.toFixed(1)}<span class="unit">°C</span>`);
      set('humidity',    isNaN(hum)  ? '--<span class="unit">%</span>'  : `${hum.toFixed(1)}<span class="unit">%</span>`);
      set('voltage',     isNaN(volt) ? '--<span class="unit">V</span>'  : `${volt.toFixed(2)}<span class="unit">V</span>`);

      const tb = document.getElementById('temp-bar');
      const hb = document.getElementById('hum-bar');
      if (tb && !isNaN(temp)) tb.style.width = Math.min(100, ((temp-10)/40)*100) + '%';
      if (hb && !isNaN(hum))  hb.style.width = Math.min(100, hum) + '%';

      const attEl = document.getElementById('attempts');
      if (attEl) attEl.textContent = parseInt(data.attempts) || 0;

      const lockEl = document.getElementById('lockout-status');
      const doorEl = document.getElementById('door-status');
      if (data.is_locked == 1) {
        if (lockEl) { lockEl.textContent = `${data.lock_remain}s`; lockEl.className = 'card-value danger'; }
        if (doorEl) { doorEl.textContent = 'LOCKED'; doorEl.className = 'card-value status-locked'; }
      } else {
        if (lockEl) { lockEl.textContent = 'NONE'; lockEl.className = 'card-value'; }
        if (doorEl) {
          doorEl.textContent = data.door_open == 1 ? 'OPEN' : 'LOCKED';
          doorEl.className   = data.door_open == 1 ? 'card-value status-open' : 'card-value status-locked';
        }
      }

      const tbody = document.getElementById('logs-body');
      if (tbody) {
        tbody.innerHTML = data.recent_logs?.length
          ? data.recent_logs.map(l => `<tr>
              <td>${l.created_at}</td>
              <td>${l.event_type}</td>
              <td>${l.code_used||'—'}</td>
              <td>${renderBadge(l.status)}</td>
            </tr>`).join('')
          : '<tr><td colspan="4" class="loading">No activity yet</td></tr>';
      }

      if (data.temp_history?.length) renderChart(data.temp_history);
    })
    .catch(() => fillMockData());
}

function renderChart(history) {
  const wrap = document.getElementById('temp-chart');
  if (!wrap) return;
  const vals = history.map(h => parseFloat(h.temperature)||0);
  const max  = Math.max(...vals), min = Math.min(...vals), range = (max-min)||1;
  wrap.innerHTML = history.map(h => {
    const val = parseFloat(h.temperature)||0;
    const pct = ((val-min)/range)*80+10;
    const t   = h.created_at?.split(' ')[1]?.substring(0,5)||'';
    return `<div class="chart-bar-wrap">
      <div class="chart-bar" style="height:${pct}%" title="${val}°C"></div>
      <div class="chart-label">${t}</div>
    </div>`;
  }).join('');
}

function fillMockData() {
  const set = (id,html) => { const el=document.getElementById(id); if(el) el.innerHTML=html; };
  set('temperature','24.5<span class="unit">°C</span>');
  set('humidity',   '62.0<span class="unit">%</span>');
  set('voltage',    '3.18<span class="unit">V</span>');
  const attEl = document.getElementById('attempts');
  if (attEl) attEl.textContent = '0';
  const tb = document.getElementById('temp-bar');
  const hb = document.getElementById('hum-bar');
  if (tb) tb.style.width='55%';
  if (hb) hb.style.width='62%';
  const tbody = document.getElementById('logs-body');
  if (tbody) tbody.innerHTML = `
    <tr><td>--</td><td>Waiting for ESP32...</td><td>—</td><td><span class="badge">—</span></td></tr>`;
  const mockH = Array.from({length:10},(_,i)=>({temperature:(22+Math.sin(i)*3).toFixed(1),created_at:`2025-01-10 ${String(10+i).padStart(2,'0')}:00`}));
  renderChart(mockH);
}

setInterval(updateClock, 1000);
updateClock();
fetchDashboard();
setInterval(fetchDashboard, POLL_INTERVAL);
