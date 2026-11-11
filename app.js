/* Forex Sorgulama - Uygulama Mantığı */
const KRAFTON = 'krafton.php';
const API   = 'forexsystem.php';
const AUTH  = 'auth.php';
const CHAT  = 'chat.php';
const AIAPI = 'ai.php';
const BRUTE = 'brute.php';
const HAVA  = 'havadurumu.php';
const DEFAULT_AVATAR = 'https://i.hizliresim.com/midnihxu.jpg';

let CURRENT_USER = null;
let CHAT_TIMER = null;
let HEARTBEAT_TIMER = null;
let CURRENT_QUERY = 'tc';

const QUERIES = {
  tc:        { icon:'🆔', baslik:'TC Sorgulama',       alt:'Kimlik numarası ile kişi bilgisi', inputs:[{id:'tc', ph:'TC Kimlik No (11 hane)', max:11, label:'TC Kimlik Numarası'}], type:'tc', vip:false },
  tcpro:     { icon:'🆔', baslik:'TC Pro Sorgulama',   alt:'Detaylı kişi bilgisi',             inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'tcpro', vip:false },
  adsoyad:   { icon:'👥', baslik:'Ad Soyad Sorgulama', alt:'Ad ve soyad ile TC bulma',         inputs:[{id:'ad', ph:'Ad', label:'Ad'},{id:'soyad', ph:'Soyad', label:'Soyad'}], type:'adsoyad', vip:false },
  aile:      { icon:'👪', baslik:'Aile Sorgulama',     alt:'TC ile aile bireyleri',            inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'aile', vip:false },
  ailepro:   { icon:'👪', baslik:'Aile Pro Sorgulama', alt:'Detaylı aile bilgisi',             inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'ailepro', vip:false },
  tcgsm:     { icon:'📱', baslik:'TC → GSM Sorgulama', alt:'TC ile telefon numarası',          inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'tcgsm', vip:false },
  gsmtc:     { icon:'📞', baslik:'GSM → TC Sorgulama', alt:'Telefon ile TC',                   inputs:[{id:'gsm', ph:'GSM No (5XXXXXXXXX)', max:10, label:'GSM Numarası'}], type:'gsmtc', vip:false },
  eokul:     { icon:'🎓', baslik:'E-Okul Sorgulama',   alt:'Öğrenci bilgileri',                inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'eokul', vip:false },
  sulale:    { icon:'🌳', baslik:'Sülale Sorgulama',   alt:'Sülale kayıtları',                 inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'sulale', vip:true },
  adres:     { icon:'🏠', baslik:'Adres Sorgulama',    alt:'İkametgah adresi',                 inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'adres', vip:true },
  tapu:      { icon:'🏡', baslik:'Tapu Sorgulama',     alt:'Tapu kayıtları',                   inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'tapu', vip:true },
  adaparsel: { icon:'📐', baslik:'Ada Parsel',         alt:'İl/ilçe ile parsel',               inputs:[{id:'il', ph:'İl', label:'İl'},{id:'ilce', ph:'İlçe', label:'İlçe'}], type:'adaparsel', vip:true },
  new_adsoyad:   { icon:'🔎', baslik:'Detaylı Ad Soyad',   alt:'Ad + soyad + il',   inputs:[{id:'ad', ph:'Ad', label:'Ad'},{id:'soyad', ph:'Soyad', label:'Soyad'},{id:'il', ph:'İl (opsiyonel)', label:'İl'}], type:'new_adsoyad', vip:false },
  new_tc:        { icon:'🆔', baslik:'Detaylı TC',         alt:'TC + GSM + aile',   inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_tc', vip:false },
  new_aile:      { icon:'👪', baslik:'Detaylı Aile',       alt:'Tam aile ağacı',    inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_aile', vip:false },
  new_hane:      { icon:'🏘️', baslik:'Hane Sorgu',         alt:'Aynı hane',         inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_hane', vip:false },
  new_sulale:    { icon:'🌳', baslik:'Detaylı Sülale',     alt:'Büyükbaba+anne',    inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_sulale', vip:true },
  new_sokak:     { icon:'🛣️', baslik:'Sokak Sorgu',        alt:'Aynı sokak',        inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_sokak', vip:true },
  new_adres2009: { icon:'📍', baslik:'Eski Adres Geçmişi', alt:'2009-2024',        inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_adres2009', vip:true },
  hava:          { icon:'🌦️', baslik:'Hava Durumu',        alt:'Anlık + günlük + haftalık + saatlik', inputs:[{id:'il', ph:'İl adı (örn: Ankara)', label:'İl'},{id:'gun', ph:'7', label:'Kaç günlük? (1-16)', max:2}], type:'hava', vip:false },
};

const INFO_TEXT = {
  tc:'TC kimlik numarası ile kişinin bilgilerini sorgular.',
  tcpro:'Detaylı kişi bilgisi.',
  adsoyad:'Ad ve soyad ile TC bulur.',
  aile:'Aile bireylerini listeler.',
  ailepro:'Detaylı aile bilgisi.',
  tcgsm:'TC ile GSM bulur.',
  gsmtc:'GSM ile TC bulur.',
  eokul:'E-okul bilgileri.',
  sulale:'💎 VIP — Sülale kayıtları.',
  adres:'💎 VIP — İkametgah adresi.',
  tapu:'💎 VIP — Tapu kayıtları.',
  adaparsel:'💎 VIP — Ada parsel sorgu.',
  new_adsoyad:'Ad+soyad+il ile toplu sorgu.',
  new_tc:'Detaylı TC bilgisi.',
  new_aile:'Tam aile ağacı.',
  new_hane:'Aynı hanedeki kişiler.',
  new_sulale:'💎 VIP — Detaylı sülale.',
  new_sokak:'💎 VIP — Sokaktaki kişiler.',
  new_adres2009:'💎 VIP — Eski adresler.',
  hava:'İl adı + kaç günlük (1-16). Anlık + günlük + haftalık + saatlik tahmin.',
};

const toastEl = document.getElementById('toast');
function toast(msg, type='') {
  if (!toastEl) return;
  toastEl.textContent = msg;
  toastEl.className = 'toast show ' + type;
  clearTimeout(toastEl._t);
  toastEl._t = setTimeout(() => toastEl.className = 'toast', 2200);
}
function esc(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function avatarOf(u) { const a = u && u.avatar; return (a && !a.includes('ui-avatars.com')) ? a : DEFAULT_AVATAR; }
function isVIP(u) { if (!u) return false; return u.is_vip || u.rank === 'VIP' || u.is_admin; }

function setTheme(theme) {
  const ic = document.getElementById('themeIcon');
  const lb = document.getElementById('themeLabel');
  document.body.classList.add('custom-bg');
  if (theme === 'dark') {
    document.body.classList.add('dark'); document.body.classList.remove('light');
    localStorage.setItem('theme', 'dark');
    if (ic) ic.textContent = '🌙';
    if (lb) lb.textContent = 'Gece Modu';
  } else {
    document.body.classList.add('light'); document.body.classList.remove('dark');
    localStorage.setItem('theme', 'light');
    if (ic) ic.textContent = '☀️';
    if (lb) lb.textContent = 'Gündüz Modu';
  }
}
function toggleTheme() { setTheme(document.body.classList.contains('dark') ? 'light' : 'dark'); }
setTheme(localStorage.getItem('theme') === 'light' ? 'light' : 'dark');

function switchTab(tab) {
  document.getElementById('authErr').classList.remove('active');
  if (tab === 'login') {
    document.getElementById('tabLogin').classList.add('active');
    document.getElementById('tabRegister').classList.remove('active');
    document.getElementById('formLogin').classList.remove('hidden');
    document.getElementById('formRegister').classList.add('hidden');
  } else {
    document.getElementById('tabRegister').classList.add('active');
    document.getElementById('tabLogin').classList.remove('active');
    document.getElementById('formRegister').classList.remove('hidden');
    document.getElementById('formLogin').classList.add('hidden');
  }
}
function showErr(m) { const e = document.getElementById('authErr'); e.textContent = m; e.classList.add('active'); }

async function doLogin() {
  const u = document.getElementById('loginUser').value.trim();
  const p = document.getElementById('loginPass').value;
  const btn = document.getElementById('loginBtn');
  document.getElementById('authErr').classList.remove('active');
  if (!u || !p) return showErr('❌ Tüm alanları doldur');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Giriş...';
  try {
    const fd = new FormData();
    fd.append('action','login'); fd.append('username',u); fd.append('password',p);
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Hoş geldin, ' + d.user.username, 'success'); enterPanel(d.user); }
    else showErr('❌ ' + (d.error || 'Giriş başarısız'));
  } catch (e) { showErr('❌ Bağlantı hatası'); }
  finally { btn.disabled = false; btn.textContent = 'Giriş Yap'; }
}

async function doRegister() {
  const u = document.getElementById('regUser').value.trim();
  const e = document.getElementById('regEmail').value.trim();
  const p = document.getElementById('regPass').value;
  const btn = document.getElementById('regBtn');
  document.getElementById('authErr').classList.remove('active');
  if (!u || !p) return showErr('❌ Kullanıcı adı ve şifre gerekli');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Kayıt...';
  try {
    const fd = new FormData();
    fd.append('action','register'); fd.append('username',u); fd.append('email',e); fd.append('password',p);
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Kayıt başarılı!', 'success'); enterPanel(d.user); }
    else showErr('❌ ' + (d.error || 'Kayıt başarısız'));
  } catch (e) { showErr('❌ Bağlantı hatası'); }
  finally { btn.disabled = false; btn.textContent = 'Kayıt Ol'; }
}

async function doLogout() {
  await fetch(AUTH + '?action=logout').catch(() => {});
  if (CHAT_TIMER) clearInterval(CHAT_TIMER);
  if (HEARTBEAT_TIMER) clearInterval(HEARTBEAT_TIMER);
  CURRENT_USER = null;
  document.getElementById('panelView').classList.add('hidden');
  document.getElementById('authView').classList.remove('hidden');
}

async function checkSession() {
  try {
    const r = await fetch(AUTH + '?action=check');
    const d = await r.json();
    if (d.success && d.logged) enterPanel(d.user);
  } catch (e) {}
}

function enterPanel(user) {
  CURRENT_USER = user;
  document.getElementById('authView').classList.add('hidden');
  document.getElementById('panelView').classList.remove('hidden');
  document.getElementById('sideAvatar').src = avatarOf(user);
  document.getElementById('sideUsername').textContent = user.username;
  document.getElementById('sideTick').classList.toggle('hidden', !user.verified);

  const st = document.getElementById('sideStatus');
  if (user.is_vip || user.rank === 'VIP') {
    st.className = 'st vip';
    st.innerHTML = '<span class="dot"></span> 💎 VIP';
  } else {
    st.className = 'st on';
    st.innerHTML = '<span class="dot"></span> Çevrimiçi';
  }

  if (user.is_admin) {
    document.getElementById('adminLink').style.display = 'flex';
    document.getElementById('adminDivider').style.display = 'block';
  }

  showView('chat');
  loadChat();
  if (CHAT_TIMER) clearInterval(CHAT_TIMER);
  CHAT_TIMER = setInterval(loadChat, 4000);
  if (HEARTBEAT_TIMER) clearInterval(HEARTBEAT_TIMER);
  HEARTBEAT_TIMER = setInterval(() => fetch(AUTH + '?action=heartbeat').catch(() => {}), 120000);
}

function toggleSidebar() {
  document.getElementById('sidebar')?.classList.toggle('open');
  document.getElementById('overlay')?.classList.toggle('active');
}
function toggleProfileMenu(e) {
  e.stopPropagation();
  const d = document.getElementById('profileDropdown');
  const b = document.getElementById('profileBtn');
  const opening = !d.classList.contains('open');
  d.classList.toggle('open', opening);
  b.classList.toggle('open', opening);
}
document.addEventListener('click', () => {
  document.getElementById('profileDropdown')?.classList.remove('open');
  document.getElementById('profileBtn')?.classList.remove('open');
});

function showView(view) {
  const viewMap = {
    query:'viewQuery', chat:'viewChat', users:'viewUsers',
    profile:'viewProfile', settings:'viewSettings', ai:'viewAI',
    brute:'viewBrute', krafton:'viewKrafton', hava:'viewHava'
  };
  Object.values(viewMap).forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
  });
  const targetId = viewMap[view];
  if (targetId) {
    const el = document.getElementById(targetId);
    if (el) el.classList.remove('hidden');
  }

  document.querySelectorAll('.sb-nav .sb-item').forEach(el => el.classList.remove('active'));
  const navMap = { chat:'navChat', users:'navUsers', ai:'navAI', brute:'navBrute', krafton:'navKrafton' };
  if (navMap[view]) {
    const navEl = document.getElementById(navMap[view]);
    if (navEl) navEl.classList.add('active');
  }

  if (view === 'profile') loadProfile();
  if (view === 'users') loadUsers();
  if (view === 'settings') loadAccountInfo();
  if (view === 'chat') { loadChat(); scrollChatBottom(); }
  if (view === 'ai') { loadAIHistory(); scrollAIBottom(); }
  if (view === 'brute') { loadBruteInfo(); }
  if (view === 'krafton') { loadKraftonLogs(); }

  if (window.innerWidth < 900) {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('overlay')?.classList.remove('active');
  }
}

function openQuery(type) {
  const q = QUERIES[type];
  if (!q) return;
  if (q.vip && !isVIP(CURRENT_USER)) { toast('💎 Bu sorgu VIP üyeler içindir', 'error'); return; }

  CURRENT_QUERY = type;
  document.getElementById('queryIcon').textContent = q.icon;
  document.getElementById('queryTitle').textContent = q.baslik;
  document.getElementById('querySubtitle').textContent = q.alt || '';
  document.getElementById('queryInfoText').textContent = INFO_TEXT[type] || '';

  const wrap = document.getElementById('queryInputs');
  wrap.innerHTML = '';
  for (const inp of q.inputs) {
    const g = document.createElement('div');
    g.className = 'q-input-group';
    if (inp.label) {
      const lb = document.createElement('label');
      lb.className = 'q-input-label';
      lb.innerHTML = inp.label + (inp.max ? ` <span class="q-input-hint">(${inp.max} hane)</span>` : '');
      g.appendChild(lb);
    }
    const i = document.createElement('input');
    i.type = 'text'; i.id = 'input_' + inp.id;
    i.className = 'q-input'; i.placeholder = inp.ph;
    if (inp.max) i.maxLength = inp.max;
    if (inp.id === 'gun') i.value = '7';
    i.addEventListener('keypress', e => { if (e.key === 'Enter') runQuery(); });
    g.appendChild(i);
    wrap.appendChild(g);
  }
  document.getElementById('resultWrap').classList.remove('active');
  document.getElementById('resultTable').innerHTML = '';
  showView('query');
}

function resetQuery() {
  const q = QUERIES[CURRENT_QUERY];
  if (!q) return;
  for (const inp of q.inputs) {
    const el = document.getElementById('input_' + inp.id);
    if (el) el.value = (inp.id === 'gun') ? '7' : '';
  }
  document.getElementById('resultWrap').classList.remove('active');
  document.getElementById('resultTable').innerHTML = '';
}

async function runQuery() {
  const q = QUERIES[CURRENT_QUERY];
  if (!q) return;
  if (q.vip && !isVIP(CURRENT_USER)) { toast('💎 VIP gerekli', 'error'); return; }

  if (CURRENT_QUERY === 'hava') { return runHava(); }

  const btn = document.getElementById('queryBtn');
  const wrap = document.getElementById('resultWrap');
  const table = document.getElementById('resultTable');
  const params = new URLSearchParams();
  params.set('action', 'query');
  params.set('type', q.type);
  for (const inp of q.inputs) {
    const v = document.getElementById('input_' + inp.id)?.value.trim() || '';
    if (v) params.set(inp.id, v);
  }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Sorgulanıyor...';
  wrap.classList.add('active');
  table.innerHTML = '<div class="q-empty"><span class="spinner"></span> Sorgulanıyor...</div>';
  document.getElementById('resultCount').textContent = '';
  try {
    const r = await fetch(API + '?' + params.toString());
    const d = await r.json();
    if (r.status === 401) { doLogout(); return; }
    renderResult(d);
  } catch (e) {
    table.innerHTML = `<div class="q-error">✗ Bağlantı hatası</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🔎 Sorgula';
  }
}

function renderResult(data) {
  const table = document.getElementById('resultTable');
  const cnt = document.getElementById('resultCount');
  if (data.error || data.hata) { table.innerHTML = `<div class="q-error">✗ ${esc(data.error || data.hata)}</div>`; cnt.textContent = ''; return; }
  if (data.success === false) { table.innerHTML = `<div class="q-error">✗ ${esc(data.error || 'Kayıt yok')}</div>`; cnt.textContent = ''; return; }

  if (data.data && typeof data.data === 'object' && !Array.isArray(data.data) && data.data.kisi) {
    const d = data.data;
    const kategoriler = { kisi:'👤 Kişi', baba:'👨 Baba', anne:'👩 Anne', buyukbaba:'👴 Büyükbaba', buyukanne:'👵 Büyükanne', kardesler:'👫 Kardeşler', cocuklar:'👶 Çocuklar', torunlar:'👦 Torunlar', ailesirano_uyeleri:'📋 Aile Üyeleri' };
    let html = '', toplam = 0;
    if (d.ozet) {
      let s = [];
      for (const [k, v] of Object.entries(d.ozet)) if (v) s.push(`${k}: ${v}`);
      if (s.length) html += `<div class="result-ozet">📊 ${s.join(' · ')}</div>`;
    }
    for (const [key, list] of Object.entries(d)) {
      if (Array.isArray(list) && list.length > 0) {
        toplam += list.length;
        const b = kategoriler[key] || key;
        html += `<div class="result-category"><div class="result-category-title">${b} <span class="cat-count">${list.length} kişi</span></div><table class="q-table"><thead><tr>`;
        const keys = [], seen = new Set();
        for (const it of list) for (const k of Object.keys(it)) if (!seen.has(k)) { seen.add(k); keys.push(k); }
        for (const k of keys) html += `<th>${esc(k)}</th>`;
        html += '</tr></thead><tbody>';
        for (const it of list) {
          html += '<tr>';
          for (const k of keys) {
            let v = it[k];
            if (v === null || v === undefined) v = '-';
            else if (typeof v === 'object') v = JSON.stringify(v);
            html += `<td>${esc(v)}</td>`;
          }
          html += '</tr>';
        }
        html += '</tbody></table></div>';
      }
    }
    if (toplam === 0) { table.innerHTML = '<div class="q-empty">✗ Kayıt yok</div>'; return; }
    table.innerHTML = html;
    cnt.textContent = toplam + ' kayıt';
    return;
  }

  let items = data.rows || data.data?.results || data.data?.rows || data.data || data.results || data;
  if (!Array.isArray(items)) items = [items];
  items = items.filter(x => x && Object.keys(x).length > 0);
  if (items.length === 0) { table.innerHTML = '<div class="q-empty">✗ Kayıt yok</div>'; return; }

  const keys = [], seen = new Set();
  for (const it of items) for (const k of Object.keys(it)) if (!seen.has(k)) { seen.add(k); keys.push(k); }

  let html = '<table class="q-table"><thead><tr>';
  for (const k of keys) html += `<th>${esc(k)}</th>`;
  html += '</tr></thead><tbody>';
  for (const it of items) {
    html += '<tr>';
    for (const k of keys) {
      let v = it[k];
      if (v === null || v === undefined) v = '-';
      else if (typeof v === 'object') v = JSON.stringify(v);
      html += `<td>${esc(v)}</td>`;
    }
    html += '</tr>';
  }
  html += '</tbody></table>';
  table.innerHTML = html;
  cnt.textContent = items.length + ' kayıt';
}

// ═══════════ HAVA DURUMU ═══════════
async function runHava() {
  const il = document.getElementById('input_il')?.value.trim();
  const gun = document.getElementById('input_gun')?.value.trim() || '7';
  const btn = document.getElementById('queryBtn');
  const wrap = document.getElementById('resultWrap');
  const table = document.getElementById('resultTable');
  const cnt = document.getElementById('resultCount');
  if (!il) { toast('İl adı gerekli', 'error'); return; }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Sorgulanıyor...';
  wrap.classList.add('active');
  table.innerHTML = '<div class="q-empty"><span class="spinner"></span> Hava verisi çekiliyor...</div>';
  cnt.textContent = '';

  try {
    const r = await fetch(HAVA + '?il=' + encodeURIComponent(il) + '&gun=' + encodeURIComponent(gun));
    const d = await r.json();

    if (d.error || d.hata) {
      table.innerHTML = `<div class="q-error">✗ ${esc(d.error || d.hata)}</div>`;
      return;
    }

    renderHava(d);
  } catch (e) {
    table.innerHTML = '<div class="q-error">✗ Bağlantı hatası</div>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🔎 Sorgula';
  }
}

function renderHava(d) {
  const table = document.getElementById('resultTable');
  const cnt   = document.getElementById('resultCount');

  const a = d.anlik || d.current || {};
  const gunluk = d.gunluk || d.daily || [];
  const saatlik = d.saatlik || d.hourly || [];
  const k = d.konum || {};
  const ozet = d.ozet || {};

  const konumAd = k.ad || k.il || d.il || '-';
  const konumUlke = k.ulke || k.country || '';
  let html = `<div class="result-ozet">📍 ${esc(konumAd)}, ${esc(konumUlke)}</div>`;

  if (ozet.gun_sayisi) {
    html += `<div class="result-ozet" style="background:rgba(163,113,247,.1);border-color:rgba(163,113,247,.3);color:#d2a8ff;">
      📊 ${esc(ozet.gun_sayisi)} günlük özet:
      En düşük <b>${esc(ozet.en_dusuk)}°</b> ·
      En yüksek <b>${esc(ozet.en_yuksek)}°</b> ·
      Ort. <b>${esc(ozet.ort_sicak || ozet.ortalama)}°</b> ·
      Toplam yağış <b>${esc(ozet.toplam_yagis)} mm</b>
    </div>`;
  }

  const anlikSicaklik = a.sicaklik ?? a.temperature_2m ?? '-';
  const anlikHissedilen = a.hissedilen ?? a.apparent_temperature ?? '-';
  const anlikNem = a.nem ?? a.relative_humidity_2m ?? '-';
  const anlikRuzgar = a.ruzgar ?? a.wind_speed_10m ?? '-';
  const anlikBasinc = a.basinc ?? a.pressure_msl ?? '-';
  const anlikDurum = a.durum || a.weather_desc || '-';
  const anlikSaat = a.saat || a.time || '';

  html += `<div class="result-category">
    <div class="result-category-title">🌡️ Anlık Durum <span class="cat-count">${esc(anlikSaat)}</span></div>
    <table class="q-table"><tbody>
      <tr><th>Sıcaklık</th><td>${esc(anlikSicaklik)} °C</td>
          <th>Hissedilen</th><td>${esc(anlikHissedilen)} °C</td></tr>
      <tr><th>Nem</th><td>${esc(anlikNem)} %</td>
          <th>Rüzgar</th><td>${esc(anlikRuzgar)} km/s</td></tr>
      <tr><th>Basınç</th><td>${esc(anlikBasinc)} hPa</td>
          <th>Durum</th><td>${esc(anlikDurum)}</td></tr>
    </tbody></table></div>`;

  if (Array.isArray(gunluk) && gunluk.length) {
    html += `<div class="result-category">
      <div class="result-category-title">📅 ${gunluk.length} Günlük Tahmin <span class="cat-count">${gunluk.length} gün</span></div>
      <table class="q-table"><thead><tr>
        <th>Tarih</th><th>Gün</th><th>Durum</th><th>Min</th><th>Max</th><th>Yağış</th><th>Rüzgar</th><th>UV</th>
      </tr></thead><tbody>`;
    for (const g of gunluk) {
      const tarih = g.tarih || g.date || '-';
      const gunAdi = g.gun || g.gun_adi || '';
      const durum = g.durum || '';
      const min = g.en_dusuk ?? g.min ?? '-';
      const max = g.en_yuksek ?? g.max ?? '-';
      const yagis = g.yagis_mm ?? g.yagis ?? 0;
      const ruzgar = g.ruzgar_max ?? g.ruzgar ?? '-';
      const uv = g.uv ?? '-';
      html += `<tr>
        <td>${esc(tarih)}</td><td>${esc(gunAdi)}</td><td>${esc(durum)}</td>
        <td>${esc(min)}°</td><td>${esc(max)}°</td>
        <td>${esc(yagis)} mm</td><td>${esc(ruzgar)} km/s</td><td>${esc(uv)}</td>
      </tr>`;
    }
    html += `</tbody></table></div>`;
  }

  if (Array.isArray(saatlik) && saatlik.length) {
    html += `<div class="result-category">
      <div class="result-category-title">⏰ Saatlik (${saatlik.length} saat)</div>
      <table class="q-table"><thead><tr>
        <th>Saat</th><th>Sıcaklık</th><th>Durum</th><th>Yağış %</th><th>Rüzgar</th>
      </tr></thead><tbody>`;
    for (const s of saatlik) {
      const saat = s.saat || s.time || '';
      const sic = s.sicaklik ?? s.temperature_2m ?? '-';
      const durum = s.durum || '';
      const yagis = s.yagis_yuz ?? s.yagis ?? s.precipitation_probability ?? '-';
      const ruzgar = s.ruzgar ?? s.wind_speed_10m ?? '-';
      html += `<tr>
        <td>${esc(String(saat).replace('T',' '))}</td>
        <td>${esc(sic)}°C</td><td>${esc(durum)}</td>
        <td>%${esc(yagis)}</td><td>${esc(ruzgar)} km/s</td>
      </tr>`;
    }
    html += `</tbody></table></div>`;
  }

  if (d.not) {
    html += `<div class="result-ozet" style="background:rgba(248,81,73,.08);border-color:rgba(248,81,73,.3);color:#ffa198;">⚠️ ${esc(d.not)}</div>`;
  }
  if (d.telegram) {
    html += `<div class="result-ozet" style="margin-top:.75rem;background:rgba(88,166,255,.1);border-color:rgba(88,166,255,.3);color:#79c0ff;">📢 Telegram: <b>${esc(d.telegram)}</b></div>`;
  }

  table.innerHTML = html;
  cnt.textContent = (gunluk.length || 0) + ' gün · ' + (saatlik.length || 0) + ' saat';
}

async function gosterHava() {
  const il = document.getElementById('havaIl')?.value.trim();
  const gun = document.getElementById('havaGun')?.value.trim() || '7';
  const btn = document.getElementById('havaBtn');
  const wrap = document.getElementById('havaResultWrap');
  const table = document.getElementById('havaResult');
  const cnt = document.getElementById('havaCount');
  if (!il) { toast('İl adı gerekli', 'error'); return; }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Sorgulanıyor...';
  wrap.classList.add('active');
  table.innerHTML = '<div class="q-empty"><span class="spinner"></span> Çekiliyor...</div>';

  try {
    const r = await fetch(HAVA + '?il=' + encodeURIComponent(il) + '&gun=' + encodeURIComponent(gun));
    const d = await r.json();
    if (d.error || d.hata) { table.innerHTML = `<div class="q-error">✗ ${esc(d.error || d.hata)}</div>`; return; }
    renderHavaTo(d, table, cnt);
  } catch (e) {
    table.innerHTML = '<div class="q-error">✗ Bağlantı hatası</div>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🔎 Sorgula';
  }
}

function renderHavaTo(d, table, cnt) {
  const tempDiv = document.createElement('div');
  const tempCnt = document.createElement('span');
  renderHava(d, tempDiv, tempCnt);
  table.innerHTML = tempDiv.innerHTML;
  cnt.textContent = tempCnt.textContent;
}

async function loadChat() {
  try {
    const r = await fetch(CHAT + '?action=messages');
    const d = await r.json();
    if (!d.success) return;
    const box = document.getElementById('chatMessages');
    if (!box) return;
    const atBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 80;
    let html = '';
    for (const m of d.messages) {
      const me = CURRENT_USER && m.user_id === CURRENT_USER.id;
      html += `
        <div class="msg ${me ? 'me' : ''}">
          <img class="av" src="${esc(m.avatar)}" alt="">
          <div><div class="msg-bubble">
            <div class="msg-name">${esc(m.username)} ${m.verified ? '✅' : ''}<span class="msg-rank">${esc(m.rank)}</span></div>
            <div class="msg-text">${esc(m.text)}</div>
            <div class="msg-time">${esc(m.time)}</div>
          </div></div>
        </div>`;
    }
    box.innerHTML = html || '<div class="q-empty">Henüz mesaj yok.</div>';
    if (atBottom) box.scrollTop = box.scrollHeight;
    try {
      const ur = await fetch(AUTH + '?action=users');
      const ud = await ur.json();
      if (ud.success) document.getElementById('chatOnlineCount').textContent = ud.users.filter(u => u.online).length;
    } catch (e) {}
  } catch (e) {}
}
function scrollChatBottom() { const b = document.getElementById('chatMessages'); if (b) b.scrollTop = b.scrollHeight; }
async function sendMessage() {
  const inp = document.getElementById('chatInput');
  const t = inp.value.trim();
  if (!t) return;
  const btn = document.getElementById('chatSendBtn');
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action','send'); fd.append('text',t);
    const r = await fetch(CHAT, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { inp.value=''; await loadChat(); scrollChatBottom(); }
    else toast('❌ ' + (d.error || 'Hata'), 'error');
  } catch (e) { toast('❌ Bağlantı hatası', 'error'); }
  finally { btn.disabled = false; }
}

async function loadAIHistory() {
  try {
    const r = await fetch(AIAPI + '?action=history');
    const d = await r.json();
    if (!d.success) return;
    const box = document.getElementById('aiMessages');
    if (!box) return;
    let html = '';
    for (const m of d.messages) {
      html += `
        <div class="msg me"><div><div class="msg-bubble"><div class="msg-text">${esc(m.soru)}</div></div></div></div>
        <div class="msg"><img class="av" src="${DEFAULT_AVATAR}" alt=""><div><div class="msg-bubble"><div class="msg-text">${esc(m.cevap)}</div>${m.komut ? '<div class="msg-time" style="color:#f59e0b;">⚡ Komut</div>' : ''}</div></div></div>`;
    }
    box.innerHTML = html || '<div class="q-empty">AI ile sohbete başla! 👋</div>';
  } catch (e) {}
}
function scrollAIBottom() { const b = document.getElementById('aiMessages'); if (b) b.scrollTop = b.scrollHeight; }
async function sendAI() {
  const inp = document.getElementById('aiInput');
  const q = inp.value.trim();
  if (!q) return;
  const box = document.getElementById('aiMessages');
  box.innerHTML += `<div class="msg me"><div><div class="msg-bubble"><div class="msg-text">${esc(q)}</div></div></div></div>`;
  inp.value = ''; scrollAIBottom();

  const btn = document.getElementById('aiSendBtn');
  btn.disabled = true;
  const waitId = 'wait_' + Date.now();
  box.innerHTML += `<div class="msg" id="${waitId}"><img class="av" src="${DEFAULT_AVATAR}" alt=""><div><div class="msg-bubble"><div class="msg-text"><span class="spinner"></span> düşünüyor...</div></div></div></div>`;
  scrollAIBottom();

  try {
    const fd = new FormData();
    fd.append('action','chat'); fd.append('q', q);
    const r = await fetch(AIAPI, { method:'POST', body: fd });
    const d = await r.json();
    document.getElementById(waitId)?.remove();
    if (d.success) {
      box.innerHTML += `<div class="msg"><img class="av" src="${DEFAULT_AVATAR}" alt=""><div><div class="msg-bubble"><div class="msg-text">${esc(d.response)}</div>${d.command ? '<div class="msg-time" style="color:#f59e0b;">⚡ Komut Uygulandı</div>' : ''}</div></div></div>`;
    } else {
      box.innerHTML += `<div class="msg"><div><div class="msg-bubble"><div class="msg-text" style="color:#f85149">❌ ${esc(d.error || 'Hata')}</div></div></div></div>`;
    }
    scrollAIBottom();
  } catch (e) {
    document.getElementById(waitId)?.remove();
    box.innerHTML += `<div class="msg"><div><div class="msg-bubble"><div class="msg-text" style="color:#f85149">❌ Bağlantı hatası</div></div></div></div>`;
  } finally { btn.disabled = false; btn.textContent = 'Gönder'; }
}
async function clearAI() {
  if (!confirm('AI geçmişini temizle?')) return;
  await fetch(AIAPI + '?action=clear');
  document.getElementById('aiMessages').innerHTML = '<div class="q-empty">Geçmiş temizlendi!</div>';
}

async function loadUsers() {
  try {
    const r = await fetch(AUTH + '?action=users');
    const d = await r.json();
    if (!d.success) return;
    const users = d.users.sort((a,b) => (b.online - a.online) || a.username.localeCompare(b.username));
    document.getElementById('usersCount').textContent = users.length + ' kullanıcı';
    let html = '';
    for (const u of users) {
      const cls = u.banned ? 'banned' : (u.online ? 'on' : 'off');
      const txt = u.banned ? 'BANLI' : (u.online ? 'Çevrimiçi' : 'Çevrimdışı');
      let rankCls = '';
      if (u.is_admin) rankCls = 'admin';
      else if (u.is_vip || u.rank === 'VIP') rankCls = 'vip';
      html += `
        <div class="user-card">
          <img class="av" src="${esc(avatarOf(u))}" alt="">
          <div class="info">
            <div class="nm">
              ${esc(u.username)} ${u.verified ? '<span class="tk">✅</span>' : ''}
              <span class="rk ${rankCls}">${esc(u.rank)}</span>
            </div>
            <div class="bio">${esc(u.bio || '')} ${u.horoscope ? '· ' + esc(u.horoscope) : ''}</div>
            <div class="st ${cls}"><span class="dot"></span> ${txt}</div>
          </div>
        </div>`;
    }
    document.getElementById('usersList').innerHTML = html || '<div class="q-empty">Kayıt yok</div>';
  } catch (e) {}
}

async function loadProfile() {
  try {
    const r = await fetch(AUTH + '?action=profile&user=' + encodeURIComponent(CURRENT_USER.username));
    const d = await r.json();
    if (!d.success) return;
    const u = d.user;
    document.getElementById('pAvatar').src = avatarOf(u);
    document.getElementById('pUsername').textContent = u.username;
    document.getElementById('pTick').classList.toggle('hidden', !u.verified);
    const rk = document.getElementById('pRank');
    rk.textContent = u.rank;
    rk.className = 'profile-rank-badge' + ((u.is_vip || u.rank === 'VIP') ? ' vip' : '');
    document.getElementById('pBio').textContent = u.bio || '';
    document.getElementById('pMeta').textContent = `${u.email || '-'} · ${u.horoscope || '-'} · ${u.city || '-'} · ${u.job || '-'}`;
    document.getElementById('editEmail').value = u.email || '';
    document.getElementById('editBio').value = u.bio || '';
    document.getElementById('editBirthday').value = u.birthday || '';
    document.getElementById('editCity').value = u.city || '';
    document.getElementById('editJob').value = u.job || '';
    document.getElementById('editInstagram').value = u.instagram || '';
    document.getElementById('editTelegram').value = u.telegram || '';
    document.getElementById('editAvatar').value = u.avatar || '';
  } catch (e) {}
}
async function saveProfile() {
  const fd = new FormData();
  fd.append('action','update_profile');
  fd.append('email', document.getElementById('editEmail').value.trim());
  fd.append('bio', document.getElementById('editBio').value.trim());
  fd.append('birthday', document.getElementById('editBirthday').value.trim());
  fd.append('city', document.getElementById('editCity').value.trim());
  fd.append('job', document.getElementById('editJob').value.trim());
  fd.append('instagram', document.getElementById('editInstagram').value.trim());
  fd.append('telegram', document.getElementById('editTelegram').value.trim());
  fd.append('avatar', document.getElementById('editAvatar').value.trim());
  try {
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Profil güncellendi', 'success'); loadProfile(); }
    else toast('❌ ' + d.error, 'error');
  } catch (e) { toast('❌ Bağlantı hatası', 'error'); }
}

async function changePassword() {
  const o = document.getElementById('oldPass').value;
  const n = document.getElementById('newPass').value;
  if (!o || !n) return toast('Şifre alanlarını doldur', 'error');
  const fd = new FormData();
  fd.append('action','change_password');
  fd.append('old_password',o); fd.append('new_password',n);
  try {
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Şifre değiştirildi', 'success'); document.getElementById('oldPass').value=''; document.getElementById('newPass').value=''; }
    else toast('❌ ' + d.error, 'error');
  } catch (e) { toast('❌ Bağlantı hatası', 'error'); }
}
async function loadAccountInfo() {
  try {
    const r = await fetch(AUTH + '?action=check');
    const d = await r.json();
    if (!d.success || !d.logged) return;
    const u = d.user;
    document.getElementById('settingsAccountInfo').innerHTML = `
      <div>👤 <b>Kullanıcı:</b> ${esc(u.username)}</div>
      <div>📧 <b>Email:</b> ${esc(u.email || '-')}</div>
      <div>🎂 <b>Doğum:</b> ${esc(u.birthday || '-')}</div>
      <div>♈ <b>Burç:</b> ${esc(u.horoscope || '-')}</div>
      <div>🏙️ <b>Şehir:</b> ${esc(u.city || '-')}</div>
      <div>💼 <b>Meslek:</b> ${esc(u.job || '-')}</div>
      <div>📷 <b>Instagram:</b> ${esc(u.instagram || '-')}</div>
      <div>✈️ <b>Telegram:</b> ${esc(u.telegram || '-')}</div>
      <div>🏅 <b>Rütbe:</b> ${esc(u.rank)} ${u.is_vip ? '💎' : ''}</div>
      <div>✅ <b>Doğrulanmış:</b> ${u.verified ? 'Evet' : 'Hayır'}</div>
      <div>📅 <b>Kayıt:</b> ${u.created_at ? new Date(u.created_at).toLocaleString('tr-TR') : '-'}</div>
    `;
  } catch (e) {}
}

// ═══════════ BRUTE ═══════════
async function loadBruteInfo() {
  try {
    const r = await fetch(BRUTE + '?action=info');
    const d = await r.json();
    if (!d.success) return;
    const infoEl = document.getElementById('bruteInfo');
    if (infoEl) {
      if (d.exists) {
        infoEl.innerHTML = `✅ <b>tr_wordlist.txt</b> hazır — ${d.lines} satır · ${(d.size/1024).toFixed(1)} KB`;
        infoEl.style.color = '#86efac';
      } else {
        infoEl.innerHTML = `❌ <b>tr_wordlist.txt</b> bulunamadı. <code>data/wordlists/</code> klasörüne yükleyin.`;
        infoEl.style.color = '#fca5a5';
      }
    }
  } catch (e) {}
}

async function runBrute() {
  const target = document.getElementById('bruteTarget').value.trim();
  const delay = document.getElementById('bruteDelay').value;
  const max = document.getElementById('bruteMax').value;
  if (!target) return toast('Hedef kullanıcı gerekli', 'error');

  const btn = document.getElementById('bruteBtn');
  const wrap = document.getElementById('bruteResultWrap');
  const table = document.getElementById('bruteResultTable');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Çalışıyor...';
  wrap.classList.add('active');
  table.innerHTML = '<div class="q-empty"><span class="spinner"></span> İşleniyor...</div>';

  try {
    const fd = new FormData();
    fd.append('action', 'run');
    fd.append('target', target);
    fd.append('delay', delay);
    fd.append('max', max);
    const r = await fetch(BRUTE, { method: 'POST', body: fd });
    const d = await r.json();
    if (!d.success) {
      table.innerHTML = `<div class="q-error">✗ ${esc(d.error)}</div>`;
      document.getElementById('bruteResultCount').textContent = '';
      return;
    }
    document.getElementById('bruteResultCount').textContent = d.denenen + ' deneme';
    if (!d.sonuclar.length) {
      table.innerHTML = '<div class="q-empty">✗ Eşleşen kullanıcı bulunamadı (tr_wordlist.txt içinde bu kullanıcı yok)</div>';
      return;
    }
    let html = '<table class="q-table"><thead><tr><th>#</th><th>Kullanıcı</th><th>Şifre</th><th>Durum</th><th>Mesaj</th></tr></thead><tbody>';
    let i = 1;
    for (const s of d.sonuclar) {
      html += `<tr>
        <td>${i++}</td>
        <td>${esc(s.username)}</td>
        <td>${esc(s.password)}</td>
        <td>${s.success ? '✅' : '❌'} ${esc(s.status)}</td>
        <td>${esc(s.message)}</td>
      </tr>`;
    }
    html += '</tbody></table>';
    table.innerHTML = html;
  } catch (e) {
    table.innerHTML = '<div class="q-error">✗ Bağlantı hatası</div>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🚀 Başlat (Simülasyon)';
  }
}

async function loadBruteLogs() {
  try {
    const r = await fetch(BRUTE + '?action=log');
    const d = await r.json();
    if (!d.success) return;
    const wrap = document.getElementById('bruteResultWrap');
    const table = document.getElementById('bruteResultTable');
    wrap.classList.add('active');
    document.getElementById('bruteResultCount').textContent = d.logs.length + ' kayıt';
    if (!d.logs.length) { table.innerHTML = '<div class="q-empty">Kayıt yok</div>'; return; }
    let html = '<table class="q-table"><thead><tr><th>Hedef</th><th>Kullanıcı</th><th>Şifre</th><th>Durum</th><th>Tarih</th></tr></thead><tbody>';
    for (const l of d.logs) {
      html += `<tr>
        <td>${esc(l.target)}</td>
        <td>${esc(l.username)}</td>
        <td>${esc(l.password)}</td>
        <td>${l.success ? '✅' : '❌'} ${esc(l.status)}</td>
        <td>${new Date(l.ts*1000).toLocaleString('tr-TR')}</td>
      </tr>`;
    }
    html += '</tbody></table>';
    table.innerHTML = html;
  } catch (e) {}
}

// ═══════════ KRAFTON ═══════════
async function kraftonSingle() {
  const email = document.getElementById('kSingleEmail').value.trim();
  const pass  = document.getElementById('kSinglePass').value;
  if (!email || !pass) return toast('email/şifre gerekli', 'error');

  const btn = document.getElementById('kSingleBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Deneniyor...';

  try {
    const fd = new FormData();
    fd.append('action','single');
    fd.append('email', email);
    fd.append('password', pass);
    const r = await fetch(KRAFTON, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) toast('✔ ' + d.message, 'success');
    else toast('✘ ' + (d.error || 'başarısız') + (d.status ? ' [' + d.status + ']' : ''), 'error');
    console.log('KRAFTON_RESULT:', d);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🔎 Tek Giriş Dene';
  }
}

async function kraftonCombo() {
  const content = document.getElementById('kComboContent').value.trim();
  const delay   = document.getElementById('kComboDelay').value;
  const max     = document.getElementById('kComboMax').value;
  if (!content) return toast('combo içeriği gerekli', 'error');

  const btn = document.getElementById('kComboBtn');
  const wrap = document.getElementById('kResultWrap');
  const table = document.getElementById('kResultTable');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Çalışıyor...';
  wrap.classList.add('active');
  table.innerHTML = '<div class="q-empty"><span class="spinner"></span> İşleniyor...</div>';
  document.getElementById('kResultCount').textContent = '';

  try {
    const fd = new FormData();
    fd.append('action','combo');
    fd.append('content', content);
    fd.append('delay', delay);
    fd.append('max', max);
    const r = await fetch(KRAFTON, { method:'POST', body: fd });
    const d = await r.json();
    if (!d.success) {
      table.innerHTML = `<div class="q-error">✗ ${esc(d.error)}</div>`;
      return;
    }
    document.getElementById('kResultCount').textContent = `${d.denenen} deneme · ${d.hit} hit`;
    if (!d.sonuclar.length) { table.innerHTML = '<div class="q-empty">Sonuç yok</div>'; return; }

    let html = '<table class="q-table"><thead><tr><th>#</th><th>Email</th><th>Durum</th><th>Mesaj</th></tr></thead><tbody>';
    let i = 1;
    for (const s of d.sonuclar) {
      html += `<tr>
        <td>${i++}</td>
        <td>${esc(s.email)}</td>
        <td>${s.success ? '✅' : '❌'} ${esc(s.status)}</td>
        <td>${esc(s.message)}</td>
      </tr>`;
    }
    html += '</tbody></table>';
    table.innerHTML = html;
  } catch (e) {
    table.innerHTML = '<div class="q-error">✗ Bağlantı hatası</div>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🚀 Combo Başlat';
  }
}

async function loadKraftonLogs() {
  try {
    const r = await fetch(KRAFTON + '?action=log');
    const d = await r.json();
    if (!d.success) return;
    const wrap = document.getElementById('kResultWrap');
    const table = document.getElementById('kResultTable');
    wrap.classList.add('active');
    document.getElementById('kResultCount').textContent = d.logs.length + ' kayıt';
    if (!d.logs.length) { table.innerHTML = '<div class="q-empty">Kayıt yok</div>'; return; }
    let html = '<table class="q-table"><thead><tr><th>Tip</th><th>Email</th><th>Şifre</th><th>Durum</th><th>Tarih</th></tr></thead><tbody>';
    for (const l of d.logs) {
      html += `<tr>
        <td>${esc(l.target)}</td>
        <td>${esc(l.email)}</td>
        <td>${esc(l.password)}</td>
        <td>${l.success ? '✅' : '❌'} ${esc(l.status)}</td>
        <td>${new Date(l.ts*1000).toLocaleString('tr-TR')}</td>
      </tr>`;
    }
    html += '</tbody></table>';
    table.innerHTML = html;
  } catch (e) {}
}

async function clearKraftonLogs() {
  if (!confirm('pubg logu temizlensin mi?')) return;
  await fetch(KRAFTON + '?action=clear');
  loadKraftonLogs();
  toast('Temizlendi', 'success');
}

document.getElementById('loginPass')?.addEventListener('keypress', e => { if (e.key === 'Enter') doLogin(); });
document.getElementById('regPass')?.addEventListener('keypress', e => { if (e.key === 'Enter') doRegister(); });
document.getElementById('chatInput')?.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });
document.getElementById('aiInput')?.addEventListener('keypress', e => { if (e.key === 'Enter') sendAI(); });

checkSession();