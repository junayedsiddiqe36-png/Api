(() => {
'use strict';
const tg = window.Telegram && window.Telegram.WebApp;
const CFG = window.APP_CFG;
const LANGS = window.LANGS || {en: {}};
let langCode = 'en';
const t = k => {
  const d = LANGS[langCode] || {};
  if (d[k] !== undefined) return d[k];
  const e = LANGS.en || {};
  return e[k] !== undefined ? e[k] : k;
};
function setLang(c) {
  if (!LANGS[c]) c = 'en';
  langCode = c;
  document.documentElement.lang = c;
  try { localStorage.setItem('miner_lang', c); } catch (e) {}
}
let reduce = false;
let bigText = false;
function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
function loadPrefs() {
  const l = lsGet('miner_lang') || window.LANG_DEFAULT || 'en';
  langCode = LANGS[l] ? l : 'en';
  document.documentElement.lang = langCode;
  let r = lsGet('miner_reduce');
  if (r === null) {
    const mq = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    r = (mq || (navigator.deviceMemory && navigator.deviceMemory <= 2)) ? '1' : '0';
  }
  reduce = r === '1';
  bigText = lsGet('miner_big') === '1';
  applyPrefs();
}
function applyPrefs() {
  document.documentElement.classList.toggle('reduce', reduce);
  document.documentElement.classList.toggle('big', bigText);
}
let S = null;
let pendingLevel = null;
let pendingDone = null;
let popTimer = null;
let dailyShown = false;
let pendingSkin = null;
let skinsData = null;
let offset = 0;
let view = 'home';
let busy = false;
let refreshing = false;
let lbTab = 'mining';

const $ = s => document.querySelector(s);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const nf = (n, d = 2) => Number(n || 0).toLocaleString('en-US', {minimumFractionDigits: d, maximumFractionDigits: d});
const srvNow = () => Date.now() / 1000 + offset;

/* ---------- Icon system (custom inline SVG, no emoji) ---------- */
const P = {
  home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
  x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
  wheel: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="2"/><path d="M12 2v8M12 14v8M2 12h8M14 12h8M4.9 4.9l5.7 5.7M13.4 13.4l5.7 5.7M19.1 4.9l-5.7 5.7M10.6 13.4l-5.7 5.7"/>',
  hamster: '<circle cx="12" cy="13.5" r="7.5"/><circle cx="6.2" cy="6.8" r="2.6"/><circle cx="17.8" cy="6.8" r="2.6"/><circle cx="9.4" cy="12.4" r=".7"/><circle cx="14.6" cy="12.4" r=".7"/><path d="M10.8 15.6 12 16.8 13.2 15.6"/>',
  gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
  globe: '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
  anchor: '<circle cx="12" cy="5" r="3"/><line x1="12" y1="22" x2="12" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/>',
  lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
  moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
  star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
  chip: '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>',
  rocket: '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
  palette: '<circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.04-.23-.29-.38-.63-.38-1.02 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-4.96-4.48-9-10-9z"/>',
  pick: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  bolt: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
  tasks: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
  users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  swap: '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
  gift: '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
  trophy: '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
  check: '<polyline points="20 6 9 17 4 12"/>',
  clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  copy: '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
  share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
  chat: '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
  chev: '<polyline points="9 18 15 12 9 6"/>',
  up: '<line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>',
  down: '<line x1="17" y1="7" x2="7" y2="17"/><polyline points="17 17 7 17 7 7"/>',
  target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
  flame: '<path d="M12 2c1 4-3 5-3 9a5 5 0 0 0 10 0c0-2-1-3-2-4 0 2-1 3-2 3 1-3-1-6-3-8z"/>',
  gem: '<polygon points="6 3 18 3 22 9 12 22 2 9"/><path d="M2 9h20M9 3l3 6 3-6M12 22 9 9m3 13 3-13"/>',
  user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  phone: '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
  alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
  send: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
  link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
  play: '<polygon points="5 3 19 12 5 21 5 3"/>',
  stop: '<rect x="5" y="5" width="14" height="14" rx="2"/>',
  heart: '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
  wallet: '<path d="M20 12V8H6a2 2 0 0 1 0-4h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-8"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
  history: '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/><polyline points="12 7 12 12 15 14"/>',
  trend: '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
  tool: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'
};
const ic = (n, c) => '<svg class="ic ' + (c || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (P[n] || '') + '</svg>';
const tile = (n, tone) => '<span class="tile ' + (tone || 'gold') + '">' + ic(n) + '</span>';
const coin = s => '<svg class="coinlogo" width="' + (s || 28) + '" height="' + (s || 28) + '" viewBox="0 0 32 32"><circle cx="16" cy="16" r="15" fill="url(#gGold)"/><circle cx="16" cy="16" r="11.5" fill="none" stroke="rgba(0,0,0,.28)" stroke-width="1.4"/><path d="' + themeDef().glyph + '" fill="rgba(0,0,0,.55)"/></svg>';
const usdLogo = s => '<svg class="coinlogo" width="' + (s || 28) + '" height="' + (s || 28) + '" viewBox="0 0 32 32"><circle cx="16" cy="16" r="15" fill="url(#gGreen)"/><text x="16" y="22" text-anchor="middle" font-size="17" font-weight="800" fill="#05361f" font-family="system-ui,sans-serif">$</text></svg>';
const MEDAL = ['#9ca3af', '#d08a4a', '#c9d1dc', '#f5c542', '#7dd3fc', '#b79cff'];
function medal(n, s) {
  const col = MEDAL[Math.min(n, MEDAL.length) - 1];
  return '<svg class="medal" width="' + (s || 36) + '" height="' + (s || 36) + '" viewBox="0 0 40 40"><path d="M20 2l15 8.5v19L20 38 5 29.5v-19z" fill="' + col + '" fill-opacity=".14" stroke="' + col + '" stroke-width="1.5"/>' +
    '<path d="M20 8l9.5 5.4v13.2L20 32l-9.5-5.4V13.4z" fill="' + col + '"/><text x="20" y="25.4" text-anchor="middle" font-size="13" font-weight="800" fill="#0b0e11" font-family="system-ui,sans-serif">' + n + '</text></svg>';
}
/* ---------- Animated mining scenes (one per theme, pure SVG/SMIL, no emoji) ---------- */
const CY = '1.4s';
const anim = (name, values, kt, dur, extra) => '<animate attributeName="' + name + '" values="' + values + '" keyTimes="' + kt + '" dur="' + (dur || CY) + '" repeatCount="indefinite"' + (extra || '') + '/>';
const animT = (type, values, kt, dur, extra) => '<animateTransform attributeName="transform" type="' + type + '" values="' + values + '" keyTimes="' + kt + '" dur="' + (dur || CY) + '" repeatCount="indefinite"' + (extra || '') + '/>';
const SC = {};

/* 1. Gold Rush: a miner smashes a Bitcoin with a hammer */
SC.gold = (on, lvl) => {
  const A = x => (on ? x : '');
  const L = Math.max(1, Math.min(6, lvl || 1)), K = (0.88 + 0.05 * (L - 1)).toFixed(2), helm = MEDAL[L - 1];
  const headFill = L <= 2 ? 'url(#gSteel)' : (L <= 4 ? 'url(#gGold)' : 'url(#gDia)');
  let s = '<path d="M0 118 Q40 92 84 116 T168 108 T252 118 T320 98 V200 H0Z" fill="#0e131a"/>' +
    '<rect y="170" width="320" height="30" fill="#141a22"/><rect y="170" width="320" height="2" fill="rgba(255,255,255,.06)"/>' +
    '<ellipse cx="204" cy="118" rx="70" ry="52" style="fill:var(--gold)" opacity="' + (L >= 4 ? '.2' : '.1') + '"/>' +
    '<path d="M168 172 L177 150 H231 L240 172Z" fill="#2a303b"/><path d="M177 150 H231 L228 155 H180Z" fill="#3b4453"/>';
  // bitcoin
  s += '<g transform="translate(204 150) scale(' + K + ') translate(-204 -150)"><g>' + A(animT('translate', '0 0;0 0;4 0;-3 0;0 0;0 0', '0;.32;.36;.4;.46;1')) +
    '<circle cx="204" cy="118" r="32" fill="url(#gGold)"/><circle cx="204" cy="118" r="26" fill="none" stroke="rgba(0,0,0,.28)" stroke-width="2"/>' +
    '<text x="204" y="130" text-anchor="middle" font-size="36" font-weight="900" fill="rgba(0,0,0,.55)" font-family="Arial,Helvetica,sans-serif">B</text>' +
    '<rect x="198" y="97" width="3" height="7" fill="rgba(0,0,0,.55)"/><rect x="206" y="97" width="3" height="7" fill="rgba(0,0,0,.55)"/>' +
    '<rect x="198" y="131" width="3" height="7" fill="rgba(0,0,0,.55)"/><rect x="206" y="131" width="3" height="7" fill="rgba(0,0,0,.55)"/>' +
    '<path d="M186 104 L197 115 L189 124 L200 134 M197 115 L212 111" stroke="#1a1100" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0">' +
    A(anim('opacity', '0;0;1;1;0', '0;.32;.34;.8;1')) + '</path></g></g>';
  // flying chips
  const chips = [[-34, -36], [-22, -8], [-30, 16], [14, -52], [30, -34], [8, 30]];
  if (on) {
    chips.forEach(c => {
      s += '<g transform="translate(176 116)"><g opacity="0">' + animT('translate', '0 0;0 0;' + c[0] + ' ' + c[1] + ';' + c[0] + ' ' + (c[1] + 22), '0;.32;.52;.82') +
        anim('opacity', '0;0;1;0', '0;.32;.34;.82') + '<polygon points="0,-5 5,0 0,5 -5,0" fill="url(#gGold)"/></g></g>';
    });
    s += '<circle cx="174" cy="118" r="2" fill="#fff6c4" opacity="0">' + anim('r', '2;2;22;26', '0;.32;.42;.6') + anim('opacity', '0;0;.9;0', '0;.32;.36;.6') + '</circle>';
    s += '<g opacity="0"><g>' + animT('translate', '0 0;0 0;0 -30;0 -46', '0;.34;.7;1') + '<circle cx="204" cy="70" r="8" fill="url(#gGold)"/><text x="204" y="74" text-anchor="middle" font-size="11" font-weight="900" fill="rgba(0,0,0,.6)" font-family="Arial,sans-serif">+</text></g>' +
      anim('opacity', '0;0;1;0', '0;.34;.5;.98') + '</g>';
  }
  if (L >= 5) {
    [[168, 80, 0], [250, 88, .6]].forEach(q => {
      s += '<polygon points="' + q[0] + ',' + (q[1] - 8) + ' ' + (q[0] + 2) + ',' + (q[1] - 2) + ' ' + (q[0] + 8) + ',' + q[1] + ' ' + (q[0] + 2) + ',' + (q[1] + 2) + ' ' + q[0] + ',' + (q[1] + 8) + ' ' + (q[0] - 2) + ',' + (q[1] + 2) + ' ' + (q[0] - 8) + ',' + q[1] + ' ' + (q[0] - 2) + ',' + (q[1] - 2) + '" fill="#fff" opacity=".8">' +
        A(anim('opacity', '.2;1;.2', '0;.5;1', '1.4s', ' begin="' + q[2] + 's"')) + '</polygon>';
    });
  }
  // miner
  s += '<rect x="72" y="128" width="12" height="42" rx="5" fill="#2b3a5c"/><rect x="90" y="128" width="12" height="42" rx="5" fill="#243250"/>' +
    '<rect x="68" y="164" width="18" height="8" rx="3" fill="#0b0e11"/><rect x="88" y="164" width="18" height="8" rx="3" fill="#0b0e11"/>' +
    '<rect x="66" y="86" width="40" height="50" rx="13" fill="#ff8a3d"/><rect x="66" y="110" width="40" height="5" fill="#ffe08a" opacity=".9"/>' +
    '<circle cx="86" cy="72" r="13" fill="#f0c39a"/><path d="M71 70 A15 15 0 0 1 101 70Z" fill="' + helm + '"/><rect x="69" y="68" width="34" height="4" rx="2" style="fill:var(--amber)"/>' +
    '<circle cx="102" cy="64" r="12" fill="#fff6c4" opacity=".16"/><circle cx="102" cy="64" r="4.5" fill="#fff6c4"/>' +
    '<circle cx="93" cy="75" r="1.7" fill="#1a1100"/><path d="M88 80 q4 3 8 0" stroke="#7a4a2a" stroke-width="1.4" fill="none" stroke-linecap="round"/>';
  // arm + hammer (pivot at the shoulder)
  s += '<g transform="rotate(-62 98 96)">' + A(animT('rotate', '-62 98 96;17 98 96;10 98 96;-62 98 96', '0;.32;.4;1')) +
    '<rect x="120" y="93" width="54" height="6" rx="3" fill="url(#gWood)"/>' +
    '<path d="M98 96 L128 96" stroke="#ff8a3d" stroke-width="11" stroke-linecap="round"/><circle cx="130" cy="96" r="6" fill="#f0c39a"/>' +
    '<rect x="167" y="79" width="17" height="34" rx="3" fill="' + headFill + '" stroke="rgba(0,0,0,.35)"/><rect x="167" y="79" width="5" height="34" rx="2" fill="rgba(255,255,255,.35)"/></g>';
  return s;
};

/* 2. Ocean Crystal: a pickaxe cracks a crystal, mine cart beside it */
SC.ocean = on => {
  const A = x => (on ? x : '');
  let s = '<path d="M0 130 Q50 100 100 128 T200 120 T320 110 V200 H0Z" fill="#0b1520"/><rect y="170" width="320" height="30" fill="#0f1b28"/><rect y="170" width="320" height="2" fill="rgba(255,255,255,.06)"/>' +
    '<path d="M122 172 L136 148 H226 L240 172Z" fill="#1c3447"/><path d="M136 148 H226 L222 154 H140Z" fill="#2a4a63"/>' +
    '<ellipse cx="184" cy="108" rx="70" ry="52" style="fill:var(--gold)" opacity=".12">' + A(anim('opacity', '.1;.1;.34;.12', '0;.32;.4;1')) + '</ellipse>';
  s += '<g>' + A(animT('translate', '0 0;0 0;3 0;-2 0;0 0;0 0', '0;.32;.36;.4;.46;1')) +
    '<path d="M148 150 L140 112 L154 92 L168 120 L162 150Z" fill="url(#gGold)" opacity=".75"/>' +
    '<path d="M214 150 L220 118 L234 104 L242 128 L238 150Z" fill="url(#gGold)" opacity=".75"/>' +
    '<path d="M178 150 L162 98 L188 48 L214 98 L202 150Z" fill="url(#gGold)"/>' +
    '<path d="M188 48 L174 98 H198Z" fill="rgba(255,255,255,.32)"/><path d="M188 48 L202 98 L202 150 M174 98 L178 150 M162 98 H214" stroke="rgba(255,255,255,.5)" stroke-width="1.4" fill="none" stroke-linejoin="round"/>' +
    '<path d="M168 92 L178 106 L172 116 L182 128" stroke="#06202c" stroke-width="1.8" fill="none" stroke-linecap="round" opacity="0">' + A(anim('opacity', '0;0;1;1;0', '0;.32;.34;.8;1')) + '</path></g>';
  if (on) {
    [[-34, -30], [-24, -6], [-16, 24], [-40, 8], [10, -44]].forEach(c => {
      s += '<g transform="translate(168 100)"><g opacity="0">' + animT('translate', '0 0;0 0;' + c[0] + ' ' + c[1] + ';' + c[0] + ' ' + (c[1] + 24), '0;.32;.52;.82') +
        anim('opacity', '0;0;1;0', '0;.32;.34;.82') + '<polygon points="0,-6 4,2 -4,2" fill="url(#gGold)"/></g></g>';
    });
    s += '<path d="M176 40 L180 48 L188 50 L180 53 L176 61 L172 53 L164 50 L172 48Z" fill="#fff" opacity="0">' + anim('opacity', '0;.9;0;0', '0;.5;.7;1', '1.4s') + '</path>';
  }
  // pickaxe (pivot = the hand, bottom-left)
  s += '<g transform="rotate(-100 86 170)">' + A(animT('rotate', '-100 86 170;-42 86 170;-48 86 170;-100 86 170', '0;.32;.4;1')) +
    '<rect x="86" y="166" width="104" height="8" rx="4" fill="url(#gWood)"/>' +
    '<path d="M182 134 Q226 170 182 206 Q200 170 182 134Z" fill="url(#gSteel)" stroke="rgba(0,0,0,.3)"/>' +
    '<rect x="180" y="163" width="12" height="14" rx="3" style="fill:var(--gold)"/><circle cx="86" cy="170" r="9" fill="#f0c39a"/></g>';
  // mine cart
  s += '<path d="M14 142 H66 L60 166 H20Z" fill="#2a303b"/><rect x="12" y="138" width="56" height="6" rx="2" fill="#3b4453"/><circle cx="26" cy="170" r="6" fill="#0b0e11" stroke="#4a5362" stroke-width="2"/><circle cx="54" cy="170" r="6" fill="#0b0e11" stroke="#4a5362" stroke-width="2"/>' +
    '<circle cx="28" cy="136" r="6" fill="url(#gGold)"/><circle cx="42" cy="132" r="7" fill="url(#gGold)"/><circle cx="54" cy="137" r="5.5" fill="url(#gGold)"/>';
  return s;
};

/* 3. Neon Night: a glowing mining rig with spinning fans */
SC.violet = on => {
  const A = x => (on ? x : '');
  let s = '<path d="M0 40H320M0 80H320M0 120H320M0 160H320M40 0V200M80 0V200M120 0V200M160 0V200M200 0V200M240 0V200M280 0V200" stroke="rgba(255,255,255,.04)" stroke-width="1"/>' +
    '<rect y="172" width="320" height="28" fill="#12161d"/><rect y="172" width="320" height="2" fill="rgba(255,255,255,.07)"/>' +
    '<ellipse cx="160" cy="108" rx="110" ry="60" style="fill:var(--gold)" opacity=".1"/>' +
    '<path d="M110 158 Q110 180 56 184 M210 158 Q210 180 264 184" stroke="#2a303b" stroke-width="4" fill="none" stroke-linecap="round"/>' +
    '<rect x="60" y="54" width="200" height="106" rx="14" fill="#121823" stroke="rgba(255,255,255,.16)" stroke-width="1.5"/>';
  for (let i = 0; i < 8; i++) {
    s += '<rect x="' + (78 + i * 22) + '" y="64" width="14" height="5" rx="2" style="fill:var(--gold)" opacity="' + (on ? '.35' : '.2') + '">' +
      A(anim('opacity', '.2;1;.2', '0;.5;1', '1.2s', ' begin="' + (i * 0.15).toFixed(2) + 's"')) + '</rect>';
  }
  [120, 200].forEach((cx, k) => {
    s += '<circle cx="' + cx + '" cy="116" r="34" fill="#0a0e14" stroke="rgba(255,255,255,.14)" stroke-width="2"/>' +
      '<g transform="translate(' + cx + ' 116)"><g>' + A(animT('rotate', '0 0 0;360 0 0', '0;1', '0.9s')) +
      [0, 90, 180, 270].map(a => '<path d="M0 0 C9 -5 13 -20 0 -29 C-11 -21 -7 -8 0 0Z" transform="rotate(' + a + ')" style="fill:var(--gold)" opacity=".85"/>').join('') +
      '</g><circle r="7" fill="#0a0e14" stroke="var(--gold)" stroke-width="2"/></g>';
  });
  s += '<rect x="76" y="148" width="168" height="4" rx="2" fill="rgba(255,255,255,.08)"/>';
  if (on) {
    [0, 0.5, 1.0].forEach((d, i) => {
      s += '<g opacity="0"><g>' + animT('translate', '0 0;0 -40', '0;1', '1.5s', ' begin="' + d + 's"') + '<circle cx="' + (130 + i * 30) + '" cy="52" r="7" fill="url(#gGold)"/></g>' +
        anim('opacity', '0;1;0', '0;.35;1', '1.5s', ' begin="' + d + 's"') + '</g>';
    });
  }
  return s;
};

/* 4. Rose Rocket: a rocket on the pad with orbiting coins */
SC.rose = on => {
  const A = x => (on ? x : '');
  let s = '';
  [[30, 30], [70, 70], [110, 24], [250, 28], [290, 80], [210, 62], [50, 120], [280, 130], [140, 54], [180, 20]].forEach((p, i) => {
    s += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="1.6" fill="#fff" opacity=".6">' + A(anim('opacity', '.2;.9;.2', '0;.5;1', (1.6 + (i % 3) * .5) + 's', ' begin="' + (i * 0.2).toFixed(1) + 's"')) + '</circle>';
  });
  s += '<circle cx="266" cy="52" r="22" fill="#1b2230"/><ellipse cx="266" cy="52" rx="34" ry="8" fill="none" stroke="rgba(255,255,255,.14)" stroke-width="2" transform="rotate(-18 266 52)"/>' +
    '<rect y="176" width="320" height="24" fill="#141a22"/><rect x="124" y="170" width="72" height="8" rx="3" fill="#2a303b"/>' +
    '<ellipse cx="160" cy="100" rx="90" ry="70" style="fill:var(--gold)" opacity=".1"/>';
  s += '<g>' + A(animT('translate', '0 0;0 -5;0 0', '0;.5;1', '1s')) +
    '<path d="M148 152 Q160 190 172 152Z" fill="url(#gGold)" opacity=".95">' + A(anim('d', 'M148 152 Q160 188 172 152Z;M148 152 Q160 206 172 152Z;M148 152 Q160 188 172 152Z', '0;.5;1', '.35s')) + '</path>' +
    '<path d="M144 118 L120 148 L124 164 L144 150Z M176 118 L200 148 L196 164 L176 150Z" fill="url(#gGold)"/>' +
    '<path d="M160 34 C184 60 188 112 176 154 H144 C132 112 136 60 160 34Z" fill="url(#gSteel)" stroke="rgba(0,0,0,.25)"/>' +
    '<path d="M160 34 C170 46 176 56 178 66 H142 C144 56 150 46 160 34Z" style="fill:var(--gold)"/>' +
    '<circle cx="160" cy="92" r="12" fill="#0b0e11"/><circle cx="160" cy="92" r="9" fill="url(#gGold)" opacity=".9"/></g>';
  if (on) {
    [0, 1.6].forEach(d => {
      s += '<g transform="translate(160 100)"><circle r="7" fill="url(#gGold)"><animateMotion dur="3.2s" repeatCount="indefinite" begin="' + d + 's" path="M-100 0 A100 30 0 1 1 100 0 A100 30 0 1 1 -100 0Z"/></circle></g>';
    });
  }
  return s;
};

/* 5. Eid Crescent: crescent, star, swinging lantern and a skyline */
SC.crescent = on => {
  const A = x => (on ? x : '');
  let s = '<ellipse cx="160" cy="70" rx="120" ry="70" style="fill:var(--gold)" opacity=".1"/>';
  [[40, 30], [90, 52], [226, 28], [292, 70], [250, 110], [60, 100], [120, 20]].forEach((p, i) => {
    s += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="1.5" fill="#fff" opacity=".6">' + A(anim('opacity', '.2;1;.2', '0;.5;1', (1.8 + (i % 3) * .4) + 's', ' begin="' + (i * 0.25).toFixed(2) + 's"')) + '</circle>';
  });
  s += '<path d="M168 26 A42 42 0 1 0 168 108 A36 36 0 1 1 168 26Z" fill="url(#gGold)"/>' +
    '<path d="M214 52 L218.5 63 L230 64 L221 71.5 L224 83 L214 76.5 L204 83 L207 71.5 L198 64 L209.5 63Z" fill="url(#gSteel)">' + A(anim('opacity', '.7;1;.7', '0;.5;1', '2s')) + '</path>';
  // skyline
  s += '<rect y="172" width="320" height="28" fill="#111b17"/>' +
    '<path d="M30 172 V148 H96 V172Z" fill="#17241f"/><path d="M38 148 Q63 108 88 148Z" fill="#17241f"/><rect x="62" y="98" width="3" height="14" fill="#17241f"/>' +
    '<rect x="100" y="112" width="9" height="60" fill="#17241f"/><path d="M99 112 L104.5 98 L110 112Z" fill="#17241f"/>' +
    '<rect x="14" y="124" width="9" height="48" fill="#17241f"/><path d="M13 124 L18.5 110 L24 124Z" fill="#17241f"/>' +
    '<path d="M214 172 V154 H282 V172Z" fill="#17241f"/><path d="M222 154 Q248 120 274 154Z" fill="#17241f"/>' +
    '<rect x="284" y="124" width="8" height="48" fill="#17241f"/><path d="M283 124 L288 112 L293 124Z" fill="#17241f"/>' +
    '<rect x="48" y="156" width="6" height="9" rx="3" style="fill:var(--gold)" opacity=".7"/><rect x="72" y="156" width="6" height="9" rx="3" style="fill:var(--gold)" opacity=".7"/><rect x="240" y="160" width="6" height="8" rx="3" style="fill:var(--gold)" opacity=".7"/>';
  // lantern
  s += '<g>' + A(animT('rotate', '-7 258 0;7 258 0;-7 258 0', '0;.5;1', '3s')) +
    '<line x1="258" y1="0" x2="258" y2="48" stroke="#8b94a3" stroke-width="1.5"/><path d="M248 48 H268 L266 54 H250Z" style="fill:var(--amber)"/>' +
    '<ellipse cx="258" cy="70" rx="22" ry="24" style="fill:var(--gold)" opacity=".18"/>' +
    '<path d="M250 54 H266 Q272 70 266 86 H250 Q244 70 250 54Z" fill="url(#gGold)"/><path d="M252 58 Q250 70 252 82 M258 55 V85 M264 58 Q266 70 264 82" stroke="rgba(0,0,0,.28)" stroke-width="1.2" fill="none"/>' +
    '<path d="M251 86 H265 L262 92 H254Z" style="fill:var(--amber)"/></g>';
  if (on) {
    [[40, 3.4, 0], [120, 4.2, 1.1], [180, 3.6, .6], [220, 4.6, 1.8], [150, 3.9, 2.4]].forEach(c => {
      s += '<g opacity="0"><g>' + animT('translate', '0 0;0 178', '0;1', c[1] + 's', ' begin="' + c[2] + 's"') + '<circle cx="' + c[0] + '" cy="-8" r="5.5" fill="url(#gGold)"/></g>' +
        anim('opacity', '0;1;1;0', '0;.1;.85;1', c[1] + 's', ' begin="' + c[2] + 's"') + '</g>';
    });
  }
  return s;
};

/* 6. Iron Drill: a drilling machine bores into an ore wall */
SC.drill = on => {
  const A = x => (on ? x : '');
  let s = '<rect y="172" width="320" height="28" fill="#141a22"/><rect y="172" width="320" height="2" fill="rgba(255,255,255,.06)"/>' +
    '<ellipse cx="236" cy="104" rx="76" ry="60" style="fill:var(--gold)" opacity=".1"/>' +
    '<path d="M198 0 H320 V172 H196 Q206 142 196 112 Q206 82 196 52 Q204 24 198 0Z" fill="#242b36"/>' +
    '<path d="M214 20 L250 36 L236 70 L210 56Z M262 96 L300 110 L286 146 L258 130Z M226 128 L248 140 L236 164 L220 152Z" fill="#1c222b"/>' +
    [[236, 84, 7], [272, 64, 6], [250, 114, 8], [292, 138, 6], [222, 150, 5]].map(n => '<circle cx="' + n[0] + '" cy="' + n[1] + '" r="' + n[2] + '" fill="url(#gGold)"/>').join('');
  // tracks
  s += '<rect x="26" y="150" width="118" height="22" rx="11" fill="#171c24" stroke="#343c49" stroke-width="2"/>' +
    [44, 68, 92, 116].map(x => '<circle cx="' + x + '" cy="161" r="6.5" fill="#232a35" stroke="#3d4553" stroke-width="2"/>').join('');
  // body (shakes)
  s += '<g>' + A(animT('translate', '0 0;0 -1;0 0;0 1;0 0', '0;.25;.5;.75;1', '.18s')) +
    '<rect x="36" y="98" width="96" height="54" rx="12" fill="url(#gGold)"/><rect x="36" y="132" width="96" height="6" fill="rgba(0,0,0,.2)"/>' +
    '<rect x="46" y="106" width="38" height="24" rx="7" fill="#0d1218"/><path d="M50 124 L62 110 H72 L58 124Z" fill="rgba(255,255,255,.14)"/>' +
    '<rect x="100" y="84" width="9" height="16" rx="2" fill="#3b4453"/><circle cx="118" cy="104" r="4" fill="#fff6c4" opacity=".9">' + A(anim('opacity', '.3;1;.3', '0;.5;1', '.8s')) + '</circle>' +
    '<rect x="126" y="116" width="62" height="12" rx="6" fill="url(#gSteel)"/><rect x="130" y="128" width="40" height="5" rx="2.5" fill="#3b4453"/></g>';
  // bit
  s += '<g>' + A(animT('translate', '0 0;-3 0;0 0', '0;.5;1', '.14s')) +
    '<polygon points="186,106 220,122 186,138" fill="url(#gSteel)" stroke="rgba(0,0,0,.3)"/>' +
    '<path d="M192 112 L197 132 M200 110 L205 130 M208 114 L211 128" stroke="rgba(0,0,0,.35)" stroke-width="2.5" stroke-linecap="round">' + A(animT('translate', '0 0;-5 0;0 0', '0;.5;1', '.14s')) + '</path></g>';
  if (on) {
    [[10, -26], [16, -8], [12, 14], [8, 30], [18, -40]].forEach((c, i) => {
      s += '<g transform="translate(222 122)"><g opacity="0">' + animT('translate', '0 0;' + c[0] + ' ' + c[1], '0;1', '.5s', ' begin="' + (i * 0.1).toFixed(1) + 's"') +
        anim('opacity', '0;1;0', '0;.15;1', '.5s', ' begin="' + (i * 0.1).toFixed(1) + 's"') + '<polygon points="0,-3 3,0 0,3 -3,0" fill="#fff6c4"/></g></g>';
    });
    [[0, 1.4], [0.7, 1.4]].forEach(d => {
      s += '<g opacity="0"><g>' + animT('translate', '0 0;-6 48;-8 46', '0;.7;1', '1.4s', ' begin="' + d[0] + 's"') + '<circle cx="204" cy="124" r="5" fill="url(#gGold)"/></g>' +
        anim('opacity', '0;1;1;0', '0;.1;.8;1', '1.4s', ' begin="' + d[0] + 's"') + '</g>';
    });
    s += '<g opacity="0"><g>' + animT('translate', '0 0;4 -26', '0;1', '1.6s') + '<circle cx="104" cy="82" r="5" fill="#8b94a3"/></g>' + anim('opacity', '0;.6;0', '0;.4;1', '1.6s') + '</g>';
  }
  return s;
};

/* 7. Space Mine: a mining drone fires a laser at an asteroid */
SC.space = on => {
  const A = x => (on ? x : '');
  let s = '';
  [[24, 28], [64, 90], [120, 22], [150, 150], [196, 30], [290, 40], [300, 110], [40, 150], [270, 160], [100, 130], [240, 14]].forEach((p, i) => {
    s += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="1.5" fill="#fff" opacity=".6">' + A(anim('opacity', '.2;1;.2', '0;.5;1', (1.5 + (i % 3) * .5) + 's', ' begin="' + (i * 0.2).toFixed(1) + 's"')) + '</circle>';
  });
  s += '<circle cx="30" cy="196" r="62" fill="#1a2030"/><ellipse cx="30" cy="196" rx="86" ry="14" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="3" transform="rotate(-14 30 196)"/>' +
    '<ellipse cx="226" cy="116" rx="86" ry="62" style="fill:var(--gold)" opacity=".1"/>';
  // asteroid
  s += '<g>' + A(animT('translate', '0 0;0 0;3 0;-2 0;0 0;0 0', '0;.3;.34;.4;.46;1', '1.2s')) +
    '<path d="M170 114 Q172 80 206 74 Q248 68 260 102 Q270 138 234 154 Q198 166 176 142Z" fill="#2b3140" stroke="rgba(255,255,255,.08)"/>' +
    '<circle cx="200" cy="130" r="9" fill="#232836"/><circle cx="238" cy="104" r="6" fill="#232836"/><circle cx="228" cy="142" r="5" fill="#232836"/>' +
    '<polygon points="204,80 211,50 220,82" fill="url(#gGold)"/><polygon points="226,76 236,46 244,80" fill="url(#gGold)"/><polygon points="246,92 262,70 258,98" fill="url(#gGold)" opacity=".85"/></g>';
  // drone + beam
  s += '<g>' + A(animT('translate', '0 0;0 -3;0 0', '0;.5;1', '1.6s')) +
    '<polygon points="82,62 188,98 188,126" style="fill:var(--gold)" opacity="' + (on ? '.2' : '.0') + '">' + A(anim('opacity', '.12;.4;.12', '0;.5;1', '.6s')) + '</polygon>' +
    '<ellipse cx="68" cy="60" rx="30" ry="9" fill="url(#gSteel)"/><path d="M50 58 Q68 32 86 58Z" fill="rgba(125,211,252,.5)"/>' +
    '<circle cx="48" cy="62" r="2.5" style="fill:var(--gold)">' + A(anim('opacity', '.3;1;.3', '0;.5;1', '.7s')) + '</circle><circle cx="68" cy="65" r="2.5" style="fill:var(--gold)">' + A(anim('opacity', '1;.3;1', '0;.5;1', '.7s')) + '</circle><circle cx="88" cy="62" r="2.5" style="fill:var(--gold)">' + A(anim('opacity', '.3;1;.3', '0;.5;1', '.7s')) + '</circle></g>';
  if (on) {
    s += '<circle cx="190" cy="112" r="3" fill="#fff6c4" opacity=".8">' + anim('r', '3;9;3', '0;.5;1', '.6s') + '</circle>';
    [[-30, -34], [-14, 30], [10, -48], [-40, -4], [26, 34]].forEach((c, i) => {
      s += '<g transform="translate(192 112)"><g opacity="0">' + animT('translate', '0 0;' + c[0] + ' ' + c[1], '0;1', '.9s', ' begin="' + (i * 0.18).toFixed(2) + 's"') +
        anim('opacity', '0;1;0', '0;.2;1', '.9s', ' begin="' + (i * 0.18).toFixed(2) + 's"') + '<polygon points="0,-5 4,3 -4,3" fill="url(#gGold)"/></g></g>';
    });
  }
  return s;
};

/* 8. Deep Sea Diver: a diver collects coins from a treasure chest */
SC.diver = on => {
  const A = x => (on ? x : '');
  let s = '<rect width="320" height="200" fill="#06202b"/>' +
    '<polygon points="60,0 100,0 150,172 70,172" fill="#fff" opacity=".04">' + A(anim('opacity', '.03;.08;.03', '0;.5;1', '4s')) + '</polygon>' +
    '<polygon points="170,0 220,0 300,172 200,172" fill="#fff" opacity=".04">' + A(anim('opacity', '.08;.03;.08', '0;.5;1', '5s')) + '</polygon>' +
    '<path d="M0 172 Q60 160 120 170 T240 168 T320 164 V200 H0Z" fill="#17353b"/><rect y="176" width="320" height="24" fill="#122c32"/>' +
    '<g>' + A(animT('rotate', '-4 22 172;4 22 172;-4 22 172', '0;.5;1', '3s')) + '<path d="M22 172 Q10 150 24 128 Q32 150 22 172Z" fill="#1f6b55"/><path d="M30 172 Q42 152 32 134 Q26 154 30 172Z" fill="#26856a"/></g>' +
    '<g>' + A(animT('rotate', '4 296 172;-4 296 172;4 296 172', '0;.5;1', '3.4s')) + '<path d="M296 172 Q284 148 298 126 Q306 150 296 172Z" fill="#1f6b55"/></g>' +
    '<ellipse cx="236" cy="140" rx="60" ry="40" style="fill:var(--gold)" opacity=".14"/>';
  // chest
  s += '<path d="M204 140 Q202 112 234 104 Q266 112 264 140Z" fill="#7a4a1d" stroke="#3b2208" stroke-width="2" transform="rotate(-18 204 140)"/>' +
    '<rect x="204" y="140" width="62" height="30" rx="4" fill="#8a5622" stroke="#3b2208" stroke-width="2"/><rect x="204" y="150" width="62" height="5" fill="#3b2208" opacity=".5"/>' +
    '<rect x="230" y="144" width="10" height="12" rx="2" style="fill:var(--gold)"/>' +
    [[212, 138], [224, 134], [238, 136], [250, 133], [260, 138]].map(c => '<circle cx="' + c[0] + '" cy="' + c[1] + '" r="6" fill="url(#gGold)"/>').join('');
  if (on) {
    [[226, 0], [240, 0.5], [252, 1.0]].forEach(c => {
      s += '<g opacity="0"><g>' + animT('translate', '0 0;0 -46', '0;1', '1.5s', ' begin="' + c[1] + 's"') + '<circle cx="' + c[0] + '" cy="126" r="6" fill="url(#gGold)"/></g>' +
        anim('opacity', '0;1;0', '0;.3;1', '1.5s', ' begin="' + c[1] + 's"') + '</g>';
    });
    [[104, 0], [112, 0.6], [108, 1.2]].forEach(c => {
      s += '<g opacity="0"><g>' + animT('translate', '0 0;4 -64', '0;1', '2s', ' begin="' + c[1] + 's"') + '<circle cx="' + c[0] + '" cy="62" r="3" fill="none" stroke="#9fe7ff" stroke-width="1.5"/></g>' +
        anim('opacity', '0;.9;0', '0;.2;1', '2s', ' begin="' + c[1] + 's"') + '</g>';
    });
    s += '<g transform="translate(0 40)"><path d="M0 0 Q8 -6 16 0 Q8 6 0 0Z M16 0 L22 -5 V5Z" style="fill:var(--gold)" opacity=".7"><animateMotion dur="9s" repeatCount="indefinite" path="M-30 0 Q100 -30 200 0 T360 -10"/></path></g>';
  }
  // diver
  s += '<g>' + A(animT('translate', '0 0;0 -3;0 0', '0;.5;1', '2.2s')) +
    '<path d="M78 56 Q60 18 104 0" stroke="#8b94a3" stroke-width="3" fill="none"/>' +
    '<rect x="50" y="90" width="15" height="36" rx="5" fill="#8b94a3"/>' +
    '<rect x="68" y="130" width="12" height="32" rx="5" fill="#ff8a3d"/><rect x="84" y="130" width="12" height="32" rx="5" fill="#e8742a"/>' +
    '<ellipse cx="72" cy="165" rx="12" ry="4" fill="#0b0e11"/><ellipse cx="94" cy="165" rx="12" ry="4" fill="#0b0e11"/>' +
    '<rect x="64" y="88" width="36" height="46" rx="13" fill="#ff8a3d"/><rect x="64" y="108" width="36" height="5" fill="#ffe08a" opacity=".9"/>' +
    '<g>' + A(animT('rotate', '-6 96 98;10 96 98;-6 96 98', '0;.5;1', '1.4s')) + '<path d="M96 98 L136 116" stroke="#ff8a3d" stroke-width="10" stroke-linecap="round"/><circle cx="138" cy="117" r="6" fill="#f0c39a"/></g>' +
    '<circle cx="82" cy="70" r="18" fill="#c47a3a" stroke="#7a4616" stroke-width="2"/><circle cx="87" cy="70" r="11" fill="#0d2a38" stroke="#7a4616" stroke-width="1.5"/><path d="M82 66 Q87 62 92 66" stroke="rgba(255,255,255,.5)" stroke-width="1.5" fill="none"/>' +
    '</g>';
  return s;
};

/* 9. Hamster Boss: an original cute hamster with unlockable character skins */
function hamsterG(skin, on) {
  const A = x => (on ? x : '');
  const fur = 'url(#gFur)', cream = 'url(#gCream)', pink = '#f2b7a0', ol = ' stroke="rgba(120,66,20,.55)" stroke-width="2"';
  const sleeve = {ceo: '#1f2b47', astro: '#eef2f8', ninja: '#1b1f2a'}[skin] || fur;
  let s = '';
  if (skin === 'king') s += '<path d="M54 116 Q26 168 38 194 H162 Q174 168 146 116Z" fill="#b3122b"/><path d="M54 116 Q26 168 38 194 H60 Q50 160 64 122Z" fill="rgba(0,0,0,.2)"/>';
  s += '<ellipse cx="72" cy="181" rx="18" ry="9" fill="' + pink + '"/><ellipse cx="128" cy="181" rx="18" ry="9" fill="' + pink + '"/>';
  s += '<ellipse cx="100" cy="140" rx="46" ry="42" fill="' + fur + '"' + ol + '/><ellipse cx="100" cy="150" rx="30" ry="26" fill="' + cream + '"/>';
  if (skin === 'ceo') s += '<path d="M56 118 Q100 104 144 118 L150 176 Q100 188 50 176Z" fill="#1f2b47"/><path d="M86 110 L100 154 L114 110Z" fill="#fff"/><path d="M95 116 H105 L109 152 L100 164 L91 152Z" fill="#d62839"/>';
  if (skin === 'astro') s += '<path d="M54 120 Q100 106 146 120 L152 178 Q100 190 48 178Z" fill="#eef2f8"/><rect x="50" y="152" width="100" height="8" fill="#ff8a3d" opacity=".9"/><circle cx="100" cy="134" r="7" fill="#7dd3fc"/>';
  if (skin === 'ninja') s += '<path d="M56 118 Q100 104 144 118 L150 176 Q100 188 50 176Z" fill="#1b1f2a"/><path d="M58 150 H142" stroke="#d62839" stroke-width="7"/>';
  if (skin === 'king') s += '<path d="M62 118 Q100 134 138 118 L134 132 Q100 148 66 132Z" fill="#fff" stroke="rgba(0,0,0,.15)"/>';
  if (skin === 'wizard') s += '<path d="M56 118 Q100 104 144 118 L150 176 Q100 188 50 176Z" fill="#5b3fc4"/><circle cx="84" cy="150" r="3" fill="#ffd96b"/><circle cx="116" cy="140" r="2.4" fill="#ffd96b"/><circle cx="100" cy="164" r="2.8" fill="#ffd96b"/>';
  // arms
  s += '<g>' + A(animT('rotate', '0 66 122;-32 66 122;0 66 122', '0;.5;1', '.7s')) + '<ellipse cx="54" cy="138" rx="11" ry="21" fill="' + sleeve + '" transform="rotate(16 54 138)"/><circle cx="47" cy="159" r="9" fill="' + pink + '"/></g>';
  s += '<g>' + A(animT('rotate', '0 134 122;32 134 122;0 134 122', '0;.5;1', '.7s', ' begin=".35s"')) + '<ellipse cx="146" cy="138" rx="11" ry="21" fill="' + sleeve + '" transform="rotate(-16 146 138)"/><circle cx="153" cy="159" r="9" fill="' + pink + '"/></g>';
  // head
  s += '<circle cx="50" cy="46" r="18" fill="' + fur + '"' + ol + '/><circle cx="50" cy="46" r="10" fill="#f6a0a8"/><circle cx="150" cy="46" r="18" fill="' + fur + '"' + ol + '/><circle cx="150" cy="46" r="10" fill="#f6a0a8"/>';
  s += '<ellipse cx="100" cy="88" rx="56" ry="48" fill="' + fur + '"' + ol + '/><path d="M82 52 Q100 38 118 52 Q100 62 82 52Z" fill="#efba7a"/>';
  s += '<ellipse cx="57" cy="100" rx="24" ry="20" fill="' + cream + '">' + A(anim('rx', '24;27;24', '0;.5;1', '.7s')) + '</ellipse><ellipse cx="143" cy="100" rx="24" ry="20" fill="' + cream + '">' + A(anim('rx', '24;27;24', '0;.5;1', '.7s')) + '</ellipse>';
  s += '<ellipse cx="100" cy="100" rx="30" ry="22" fill="' + cream + '"/><ellipse cx="46" cy="106" rx="11" ry="7" fill="#ff8fa0" opacity=".42"/><ellipse cx="154" cy="106" rx="11" ry="7" fill="#ff8fa0" opacity=".42"/>';
  if (skin !== 'ninja') {
    s += '<ellipse cx="78" cy="82" rx="7" ry="8.5" fill="#1a1208">' + anim('ry', '8.5;8.5;1;8.5', '0;.92;.95;1', '4s') + '</ellipse><circle cx="80.5" cy="78.5" r="2.8" fill="#fff"/><circle cx="75.5" cy="85" r="1.4" fill="#fff" opacity=".7"/>' +
      '<ellipse cx="122" cy="82" rx="7" ry="8.5" fill="#1a1208">' + anim('ry', '8.5;8.5;1;8.5', '0;.92;.95;1', '4s') + '</ellipse><circle cx="124.5" cy="78.5" r="2.8" fill="#fff"/><circle cx="119.5" cy="85" r="1.4" fill="#fff" opacity=".7"/>';
  }
  s += '<ellipse cx="100" cy="95" rx="6.5" ry="4.8" fill="#e57f8c"/><path d="M100 99 Q96 108 88 104 M100 99 Q104 108 112 104" stroke="#5a3a1c" stroke-width="2.2" fill="none" stroke-linecap="round"/>' +
    '<rect x="95" y="104" width="10" height="10" rx="2.5" fill="#fff" stroke="#d9c7a8"/>' +
    '<path d="M68 98 L40 92 M68 104 L38 106 M68 110 L42 120 M132 98 L160 92 M132 104 L162 106 M132 110 L158 120" stroke="rgba(70,45,20,.45)" stroke-width="1.6" stroke-linecap="round"/>';
  // face / head accessories
  if (skin === 'cool') s += '<rect x="56" y="70" width="38" height="22" rx="8" fill="#0d0d12"/><rect x="106" y="70" width="38" height="22" rx="8" fill="#0d0d12"/><path d="M94 78 H106" stroke="#0d0d12" stroke-width="4"/><path d="M62 74 L74 74 M112 74 L124 74" stroke="rgba(255,255,255,.5)" stroke-width="2.5" stroke-linecap="round"/>' +
    '<path d="M62 124 Q100 156 138 124" stroke="url(#gGold)" stroke-width="5" fill="none" stroke-linecap="round"/><circle cx="100" cy="146" r="9" fill="url(#gGold)" stroke="rgba(0,0,0,.3)"/>';
  if (skin === 'astro') s += '<circle cx="100" cy="88" r="68" fill="rgba(125,211,252,.16)" stroke="#d6ecff" stroke-width="5"/><path d="M52 52 Q66 34 88 30" stroke="rgba(255,255,255,.7)" stroke-width="5" fill="none" stroke-linecap="round"/>';
  if (skin === 'ninja') s += '<ellipse cx="100" cy="88" rx="57" ry="49" fill="#1b1f2a"/><rect x="58" y="68" width="84" height="28" rx="14" fill="#f1d9b5"/>' +
    '<ellipse cx="80" cy="82" rx="6.5" ry="8" fill="#1a1208">' + anim('ry', '8;8;1;8', '0;.92;.95;1', '4s') + '</ellipse><circle cx="82.5" cy="79" r="2.4" fill="#fff"/><ellipse cx="120" cy="82" rx="6.5" ry="8" fill="#1a1208">' + anim('ry', '8;8;1;8', '0;.92;.95;1', '4s') + '</ellipse><circle cx="122.5" cy="79" r="2.4" fill="#fff"/>' +
    '<rect x="44" y="52" width="112" height="13" rx="3" fill="#d62839"/><path d="M152 56 L178 46 L172 62 L184 70 L156 66Z" fill="#d62839"/>';
  if (skin === 'miner') s += '<path d="M46 62 Q100 -6 154 62Z" fill="#ffcc2e" stroke="rgba(0,0,0,.25)"/><rect x="40" y="58" width="120" height="11" rx="5" fill="#f0a50b"/><rect x="90" y="22" width="20" height="30" rx="4" fill="#f5b81c"/><circle cx="100" cy="34" r="8" fill="#fff6c4"/><circle cx="100" cy="34" r="16" fill="#fff6c4" opacity=".25"/>';
  if (skin === 'king') s += '<path d="M60 52 L68 18 L84 40 L100 10 L116 40 L132 18 L140 52Z" fill="url(#gGold)" stroke="rgba(0,0,0,.3)" stroke-width="1.5"/><circle cx="100" cy="30" r="5" fill="#e11d48"/><circle cx="74" cy="40" r="3.6" fill="#7dd3fc"/><circle cx="126" cy="40" r="3.6" fill="#7dd3fc"/>';
  if (skin === 'wizard') s += '<ellipse cx="100" cy="58" rx="64" ry="12" fill="#4a30a8"/><path d="M60 58 L100 -22 L142 58 Q100 68 60 58Z" fill="#5b3fc4"/><path d="M60 58 Q100 68 142 58" stroke="#ffd96b" stroke-width="5" fill="none"/><polygon points="96,16 98,22 104,22 99,26 101,32 96,28 91,32 93,26 88,22 94,22" fill="#ffd96b"/>';
  if (skin === 'ceo') s += '<path d="M64 56 Q100 40 136 56" stroke="rgba(0,0,0,.12)" stroke-width="0" fill="none"/>';
  return s;
}
function hamsterPreview(skin) {
  return '<svg class="skinsvg" viewBox="0 -24 200 224" preserveAspectRatio="xMidYMid meet">' + hamsterG(skin, false) + '</svg>';
}
SC.hamster = (on, lvl, skin) => {
  const A = x => (on ? x : '');
  const stack = (x, n) => {
    let o = '';
    for (let i = 0; i < n; i++) {
      const y = 170 - i * 7;
      o += '<rect x="' + (x - 17) + '" y="' + (y - 3) + '" width="34" height="8" fill="#b8690a"/><ellipse cx="' + x + '" cy="' + (y + 5) + '" rx="17" ry="5" fill="#b8690a"/><ellipse cx="' + x + '" cy="' + (y - 3) + '" rx="17" ry="5" fill="url(#gGold)" stroke="rgba(0,0,0,.2)"/>';
    }
    return o;
  };
  let s = '<polygon points="120,0 200,0 276,176 44,176" style="fill:var(--gold)" opacity=".1"/>' +
    '<rect y="174" width="320" height="26" fill="#17110a"/><rect y="174" width="320" height="2" fill="rgba(255,255,255,.07)"/>' +
    '<ellipse cx="160" cy="178" rx="92" ry="11" fill="rgba(0,0,0,.5)"/>' +
    stack(36, 5) + stack(62, 3) + stack(284, 6) + stack(258, 3);
  s += '<g class="hamtap"><g transform="translate(70 12) scale(.88)">' + '<g>' + A(animT('translate', '0 0;0 -4;0 0', '0;.5;1', '.7s')) + hamsterG(skin || 'classic', on) + '</g></g></g>';
  if (on) {
    [[104, 0], [160, .35], [216, .7]].forEach(c => {
      s += '<g opacity="0"><g>' + animT('translate', '0 0;0 -46', '0;1', '1.2s', ' begin="' + c[1] + 's"') + '<circle cx="' + c[0] + '" cy="132" r="7" fill="url(#gGold)"/><path d="M' + (c[0] - 2.5) + ' 134 L' + c[0] + ' 128 L' + (c[0] + 2.5) + ' 134Z" fill="rgba(0,0,0,.5)"/></g>' +
        anim('opacity', '0;1;0', '0;.3;1', '1.2s', ' begin="' + c[1] + 's"') + '</g>';
    });
    [[42, 30], [296, 44], [150, 18]].forEach((c, i) => {
      s += '<polygon points="' + c[0] + ',' + (c[1] - 6) + ' ' + (c[0] + 2) + ',' + (c[1] - 2) + ' ' + (c[0] + 6) + ',' + c[1] + ' ' + (c[0] + 2) + ',' + (c[1] + 2) + ' ' + c[0] + ',' + (c[1] + 6) + ' ' + (c[0] - 2) + ',' + (c[1] + 2) + ' ' + (c[0] - 6) + ',' + c[1] + ' ' + (c[0] - 2) + ',' + (c[1] - 2) + '" fill="#fff6c4" opacity=".7">' +
        anim('opacity', '.15;.9;.15', '0;.5;1', (1.4 + i * .3) + 's') + '</polygon>';
    });
  }
  return s;
};

/* 10. Gold Verse: isometric gold ore pile on a grass tile, pickaxe strikes it */
const animTapT = (type, values, kt, dur) => '<animateTransform class="tapanim" attributeName="transform" type="' + type + '" values="' + values + '" keyTimes="' + kt + '" dur="' + dur + '" begin="indefinite"/>';
const animTap = (name, values, kt, dur) => '<animate class="tapanim" attributeName="' + name + '" values="' + values + '" keyTimes="' + kt + '" dur="' + dur + '" begin="indefinite"/>';
SC.goldverse = on => {
  const A = x => (on ? x : '');
  let s = '<defs><radialGradient id="gvbg" cx="50%" cy="66%" r="72%"><stop offset="0" stop-color="#4a86f5"/><stop offset=".5" stop-color="#1d49c2"/><stop offset="1" stop-color="#0a1f66"/></radialGradient>' +
    '<linearGradient id="gvgrass" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#a6dc52"/><stop offset="1" stop-color="#5f9b2d"/></linearGradient>' +
    '<linearGradient id="gvgold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffe27a"/><stop offset=".5" stop-color="#ffb300"/><stop offset="1" stop-color="#e07b00"/></linearGradient></defs>' +
    '<rect width="320" height="200" fill="url(#gvbg)"/>';
  [[30, 30], [70, 66], [250, 28], [292, 70], [214, 18], [110, 22]].forEach((p, i) => {
    s += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="1.5" fill="#fff" opacity=".5">' + A(anim('opacity', '.2;.9;.2', '0;.5;1', (1.6 + i * .3) + 's')) + '</circle>';
  });
  s += '<ellipse cx="160" cy="186" rx="104" ry="13" fill="rgba(0,0,40,.4)"/>' +
    '<polygon points="74,152 160,184 160,198 74,166" fill="#3f6e1d"/><polygon points="160,184 246,152 246,166 160,198" fill="#2c5012"/>' +
    '<polygon points="160,120 246,152 160,184 74,152" fill="url(#gvgrass)"/><polyline points="74,152 160,184 246,152" fill="none" stroke="rgba(255,255,255,.28)" stroke-width="1.5"/>' +
    '<ellipse cx="160" cy="146" rx="60" ry="26" fill="#ffb300" opacity=".18"/>';
  // gold pile
  s += '<g transform="translate(160 142)"><g>' + A(animT('translate', '0 0;0 0;3 0;-2 0;0 0;0 0', '0;.32;.36;.4;.46;1')) + animTapT('translate', '0 0;3 0;-2 0;0 0', '0;.3;.6;1', '.4s') +
    '<polygon points="-52,12 -44,-6 -30,-10 -24,10 -36,18" fill="#ffa726"/><polygon points="-44,-6 -30,-10 -34,2" fill="#ffe082"/><polygon points="-30,-10 -24,10 -34,2" fill="#e68a00"/>' +
    '<polygon points="30,14 36,-2 50,-4 58,12 44,20" fill="#ffb300"/><polygon points="36,-2 50,-4 44,6" fill="#ffe082"/><polygon points="50,-4 58,12 44,6" fill="#e07b00"/>' +
    '<polygon points="-34,8 -22,-24 -2,-34 20,-22 28,8 8,16" fill="url(#gvgold)"/>' +
    '<polygon points="-22,-24 -2,-34 4,-12 -14,-8" fill="#ffeaa0"/><polygon points="20,-22 28,8 8,16 6,-8" fill="#e68a00"/><polygon points="-14,-8 4,-12 6,-8 8,16 -34,8" fill="#ffa000"/>' +
    '<polygon points="-2,-34 20,-22 6,-8 4,-12" fill="#ffd54f"/>' +
    '<polygon points="-12,20 -4,2 14,0 24,16 8,24" fill="#ff9800"/><polygon points="-4,2 14,0 8,12" fill="#ffd54f"/><polygon points="14,0 24,16 8,24 8,12" fill="#e07b00"/>' +
    '<polygon points="-10,-14 -6,-22 0,-16" fill="#fff" opacity=".7"/></g></g>';
  if (on) {
    [[-38, -30], [-26, -6], [-32, 18], [-10, -48], [-44, 4]].forEach(c => {
      s += '<g transform="translate(184 112)"><g opacity="0">' + animT('translate', '0 0;0 0;' + c[0] + ' ' + c[1] + ';' + c[0] + ' ' + (c[1] + 22), '0;.32;.52;.82') +
        anim('opacity', '0;0;1;0', '0;.32;.34;.82') + '<polygon points="0,-5 4,0 0,5 -4,0" fill="url(#gvgold)"/></g></g>';
    });
    s += '<circle cx="184" cy="110" r="2" fill="#fff6c4" opacity="0">' + anim('r', '2;2;22;26', '0;.32;.42;.6') + anim('opacity', '0;0;.9;0', '0;.32;.36;.6') + '</circle>';
    s += '<g opacity="0"><g>' + animT('translate', '0 0;0 0;0 -34;0 -52', '0;.34;.7;1') + '<circle cx="160" cy="96" r="9" fill="url(#gvgold)" stroke="rgba(0,0,0,.25)"/><text x="160" y="100" text-anchor="middle" font-size="11" font-weight="900" fill="rgba(0,0,0,.55)" font-family="Arial,sans-serif">+</text></g>' + anim('opacity', '0;0;1;0', '0;.34;.5;.98') + '</g>';
  } else {
    s += '<circle cx="184" cy="110" r="2" fill="#fff6c4" opacity="0">' + animTap('r', '2;22;26', '0;.5;1', '.5s') + animTap('opacity', '.9;.5;0', '0;.5;1', '.5s') + '</circle>';
  }
  // pickaxe (pivot at the tile corner)
  s += '<g transform="translate(250 158)"><g transform="rotate(28)">' + A(animT('rotate', '28 0 0;-54 0 0;-48 0 0;28 0 0', '0;.32;.4;1')) + (on ? '' : animTapT('rotate', '28 0 0;-54 0 0;-48 0 0;28 0 0', '0;.35;.45;1', '.7s')) +
    '<rect x="-4" y="-86" width="8" height="94" rx="4" fill="url(#gWood)"/><path d="M-34 -80 Q0 -112 34 -80 Q0 -94 -34 -80Z" fill="url(#gSteel)" stroke="rgba(0,0,0,.4)" stroke-width="1.2"/>' +
    '<rect x="-8" y="-92" width="16" height="13" rx="3" fill="#3b4453"/></g></g>';
  return s;
};

/* 11. Satoshi Gold: glowing Bitcoin on a golden circuit board */
SC.satoshi = on => {
  const A = x => (on ? x : '');
  let s = '<defs><radialGradient id="stbg" cx="50%" cy="50%" r="70%"><stop offset="0" stop-color="#4a2c10"/><stop offset=".6" stop-color="#1d1007"/><stop offset="1" stop-color="#0b0603"/></radialGradient><clipPath id="stclip"><circle r="46"/></clipPath></defs>' +
    '<rect width="320" height="200" fill="url(#stbg)"/>';
  const lines = ['M0 40 H62 L80 58 H122', 'M320 36 H260 L242 54 H202', 'M0 156 H46 L66 136 H112', 'M320 160 H272 L252 140 H210', 'M160 0 V30', 'M160 200 V172', 'M40 0 V26 L56 42', 'M280 200 V176 L264 160'];
  s += '<g fill="none" stroke="rgba(255,196,84,.3)" stroke-width="1.5">' + lines.map(d => '<path d="' + d + '"/>').join('') + '</g>';
  s += '<g fill="none" stroke="#ffd36a" stroke-width="2" stroke-linecap="round" stroke-dasharray="5 16" opacity="' + (on ? '.9' : '.25') + '">' +
    lines.slice(0, 6).map((d, i) => '<path d="' + d + '">' + A(anim('stroke-dashoffset', '0;-42', '0;1', (1.1 + i * .12) + 's')) + '</path>').join('') + '</g>';
  [[122, 58], [202, 54], [112, 136], [210, 140], [160, 30], [160, 172]].forEach(c => { s += '<circle cx="' + c[0] + '" cy="' + c[1] + '" r="3" fill="#ffd36a"/>'; });
  s += '<ellipse cx="160" cy="172" rx="76" ry="14" fill="#241408" stroke="rgba(255,196,84,.45)" stroke-width="1.5"/><ellipse cx="160" cy="172" rx="52" ry="8" fill="none" stroke="rgba(255,196,84,.3)"/>' +
    '<circle cx="160" cy="98" r="76" fill="#ffb300" opacity=".12">' + A(anim('opacity', '.08;.2;.08', '0;.5;1', '2.4s')) + '</circle>';
  s += '<g transform="translate(160 98)"><g>' + A(animT('translate', '0 0;0 -6;0 0', '0;.5;1', '2.4s')) + '<g>' + animTapT('scale', '1 1;1.14 1.14;1 1', '0;.4;1', '.4s') +
    '<circle r="60" fill="none" stroke="#ffd36a" stroke-width="1.6" stroke-dasharray="6 9" opacity=".7">' + A(animT('rotate', '0 0 0;360 0 0', '0;1', '18s')) + '</circle>' +
    '<circle r="46" fill="url(#gGold)" stroke="#8a5600" stroke-width="3"/><circle r="38" fill="none" stroke="rgba(0,0,0,.25)" stroke-width="2"/>' +
    '<text y="19" text-anchor="middle" font-size="56" font-weight="900" fill="rgba(70,40,0,.7)" font-family="Arial,Helvetica,sans-serif">B</text>' +
    '<rect x="-9" y="-33" width="3.4" height="9" fill="rgba(70,40,0,.7)"/><rect x="5" y="-33" width="3.4" height="9" fill="rgba(70,40,0,.7)"/><rect x="-9" y="24" width="3.4" height="9" fill="rgba(70,40,0,.7)"/><rect x="5" y="24" width="3.4" height="9" fill="rgba(70,40,0,.7)"/>' +
    '<g clip-path="url(#stclip)"><g>' + animT('translate', '-80 0;90 0', '0;1', '2.6s') + '<rect x="-8" y="-60" width="16" height="120" fill="#fff" opacity=".4" transform="rotate(22)"/></g></g>' +
    '</g></g></g>';
  if (on) {
    s += '<g transform="translate(160 98)"><circle r="6" fill="url(#gGold)"><animateMotion dur="3.4s" repeatCount="indefinite" path="M-96 0 A96 26 0 1 1 96 0 A96 26 0 1 1 -96 0Z"/></circle></g>';
    [[104, 60, 0], [220, 126, .7], [140, 160, 1.4]].forEach(c => {
      s += '<polygon points="' + c[0] + ',' + (c[1] - 7) + ' ' + (c[0] + 2) + ',' + (c[1] - 2) + ' ' + (c[0] + 7) + ',' + c[1] + ' ' + (c[0] + 2) + ',' + (c[1] + 2) + ' ' + c[0] + ',' + (c[1] + 7) + ' ' + (c[0] - 2) + ',' + (c[1] + 2) + ' ' + (c[0] - 7) + ',' + c[1] + ' ' + (c[0] - 2) + ',' + (c[1] - 2) + '" fill="#fff6c4" opacity="0">' +
        anim('opacity', '0;1;0', '0;.5;1', '1.8s', ' begin="' + c[2] + 's"') + '</polygon>';
    });
  }
  return s;
};

function sceneSvg(on) {
  const fn = SC[curTheme()] || SC.gold;
  const lvl = S && S.speed && S.speed.level ? S.speed.level.level : 1;
  return '<svg class="scenesvg" viewBox="0 0 320 200" preserveAspectRatio="xMidYMid slice"><rect width="320" height="200" fill="#0c1016"/>' + fn(!!on && !reduce, lvl, S && S.skin ? S.skin.current : 'classic') + '</svg>';
}
const THEMES = [
  {id: 'goldverse', icon: 'pick', mine: 'pick', boost: 'zap', glyph: 'M16 6.5 24 14 16 25.5 8 14z'},
  {id: 'satoshi', icon: 'zap', mine: 'zap', boost: 'bolt', glyph: 'M12.5 8v16h1.8V8zM14.5 9h4a2.6 2.6 0 0 1 0 5.2h-4zM14.5 14.2h4.7a2.8 2.8 0 0 1 0 5.6h-4.7z'},
  {id: 'hamster', icon: 'hamster', mine: 'hamster', boost: 'zap', glyph: 'M16 10c-4 0-7 3-7 7s3 6 7 6 7-2 7-6-3-7-7-7zM10.5 8a2.6 2.6 0 1 0 0 5.2 2.6 2.6 0 0 0 0-5.2zM21.5 8a2.6 2.6 0 1 1 0 5.2 2.6 2.6 0 0 1 0-5.2z'},
  {id: 'gold', icon: 'pick', mine: 'pick', boost: 'bolt', glyph: 'M17.5 7 11 17.5h4.5L14.5 25 21 14.5h-4.5z'},
  {id: 'ocean', icon: 'gem', mine: 'gem', boost: 'trend', glyph: 'M16 7.5 23 15 16 24.5 9 15z'},
  {id: 'drill', icon: 'gear', mine: 'gear', boost: 'zap', glyph: 'M16 7.5 23 11.5V19.5L16 24.5 9 19.5V11.5Z'},
  {id: 'violet', icon: 'chip', mine: 'chip', boost: 'bolt', glyph: 'M11.5 11.5h9v9h-9z'},
  {id: 'diver', icon: 'anchor', mine: 'anchor', boost: 'trend', glyph: 'M16 7C20 13 22 16 22 19a6 6 0 0 1-12 0C10 16 12 13 16 7z'},
  {id: 'rose', icon: 'rocket', mine: 'rocket', boost: 'flame', glyph: 'M16 7c3.6 3 4.4 8 2.6 14h-5.2C11.6 15 12.4 10 16 7z'},
  {id: 'space', icon: 'globe', mine: 'globe', boost: 'star', glyph: 'M16 7l2.6 5.6 6 .8-4.4 4.2 1.1 6L16 20.7 10.7 23.6l1.1-6L7.4 13.4l6-.8z'},
  {id: 'crescent', icon: 'moon', mine: 'moon', boost: 'star', glyph: 'M18 8a8.5 8.5 0 1 0 0 16a12 12 0 0 1 0-16z'}
];
function curTheme() {
  const a = document.documentElement.getAttribute('data-theme');
  return THEMES.some(x => x.id === a) ? a : 'gold';
}
function themeDef() { const c = curTheme(); return THEMES.find(x => x.id === c); }
function applyTheme(id) {
  if (!THEMES.some(x => x.id === id)) id = 'gold';
  document.documentElement.setAttribute('data-theme', id);
  lsSet('miner_theme', id);
  lsSet('miner_theme_admin', (window.APP_THEME && window.APP_THEME.ver) || '');
}
function themeUnlockLevel(id) { const u = (window.APP_THEME && window.APP_THEME.unlock) || {}; return Number(u[id] || 1); }
function myLevel() { return S && S.speed && S.speed.level ? S.speed.level.level : 1; }

const NAV = ['home', 'boost', 'tasks', 'referrals', 'wallet'];
const NAVIC = {home: 'pick', boost: 'bolt', tasks: 'tasks', referrals: 'users', wallet: 'swap'};

/* ---------- API ---------- */
async function api(action, data = {}) {
  let r, j;
  try {
    r = await fetch(CFG.api, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Init-Data': tg ? tg.initData : ''},
      body: JSON.stringify(Object.assign({action}, data))
    });
    j = await r.json();
  } catch (e) { throw new Error(t('err_network')); }
  if (!j.ok) throw new Error(j.error || t('err_generic'));
  if (j.state) applyState(j.state);
  return j;
}
function applyState(s) {
  if (S) {
    if (s.speed.level.level > S.speed.level.level) {
      pendingLevel = s.speed.level;
      if (s.skin && S.skin && s.skin.auto && s.skin.current !== S.skin.current) pendingSkin = s.skin.current;
    }
    if (S.mining.status === 'active' && s.mining.status === 'completed') pendingDone = s.mining.credited;
  }
  S = s;
  offset = s.now - Date.now() / 1000;
}

/* ---------- UI helpers ---------- */
let toastTimer;
function toast(msg, type) {
  const el = $('#toast');
  el.textContent = msg; el.className = 'show ' + (type || '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.className = ''; }, 3200);
}
function haptic(kind) { try { tg && tg.HapticFeedback && tg.HapticFeedback.notificationOccurred(kind); } catch (e) {} }
async function run(fn, okMsg, cel) {
  if (busy) return;
  busy = true;
  const before = S ? S.user.coins : 0;
  try {
    const res = await fn();
    const gain = S ? S.user.coins - before : 0;
    if (cel && gain > 0) { rewardPopup({title: cel, amount: gain, sub: res && res.message}); }
    else if (res && res.message) { toast(res.message, 'ok'); haptic('success'); }
    else if (okMsg) toast(okMsg, 'ok');
    return res;
  } catch (e) { toast(e.message, 'err'); haptic('error'); }
  finally { busy = false; render(); }
}
function openModal(html) { const m = $('#modal'); m.innerHTML = '<div class="sheet"><div class="grab"></div>' + html + '</div>'; m.className = 'show'; }
function closeModal() { const m = $('#modal'); m.className = ''; m.innerHTML = ''; }
$('#modal').addEventListener('click', e => { if (e.target.id === 'modal') closeModal(); });
function confirmBox(msg) {
  return new Promise(res => {
    if (tg && tg.showConfirm) tg.showConfirm(msg, ok => res(ok)); else res(window.confirm(msg));
  });
}
function openLink(url) {
  if (!url) return;
  if (tg && /^https:\/\/t\.me\//.test(url) && tg.openTelegramLink) tg.openTelegramLink(url);
  else if (tg && tg.openLink) tg.openLink(url);
  else window.open(url, '_blank');
}
async function copyText(txt) {
  try { await navigator.clipboard.writeText(txt); }
  catch (e) { const i = document.createElement('input'); i.value = txt; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); }
  toast(t('copied'), 'ok');
}
function dur(sec) {
  sec = Math.max(0, Math.floor(sec));
  const h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
  return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}
function fdate(s) { return String(s || '').slice(0, 16).replace('T', ' '); }
function tierName(x) { return t(x) || x; }
const rowcard = (icn, tone, title, sub, attr) =>
  '<div class="rowcard" ' + attr + '>' + tile(icn, tone) + '<div class="rc-t"><b>' + title + '</b><span>' + sub + '</span></div>' + ic('chev', 'chev') + '</div>';
const stat = (icn, tone, label, value) =>
  '<div class="stat2"><div class="s-h">' + tile(icn, tone) + '<span>' + label + '</span></div><b>' + value + '</b></div>';


/* ---------- Skeletons, empty states ---------- */
const skel = n => Array.from({length: n}, () => '<div class="card skel"><i class="sk w1"></i><i class="sk w2"></i><i class="sk w3"></i></div>').join('');
const emptyBox = (icn, tone, title, text) => '<div class="card emptybox">' + tile(icn, tone) + '<b>' + title + '</b><span>' + text + '</span></div>';
function skeletonHome() {
  return '<div class="scenecard skelbox"><i class="sk fill"></i></div><div class="card skel"><i class="sk w1"></i><i class="sk w2"></i></div>' +
    '<div class="statgrid"><div class="sbox skel"><i class="sk w2"></i></div><div class="sbox skel"><i class="sk w2"></i></div><div class="sbox skel"><i class="sk w2"></i></div></div>';
}

/* ---------- Celebrations & reward popups ---------- */
function haptic2() { try { tg && tg.HapticFeedback && tg.HapticFeedback.impactOccurred('medium'); } catch (e) {} }
function coinRain() {
  if (reduce) return;
  const fx = $('#fx');
  fx.innerHTML = '';
  for (let i = 0; i < 26; i++) {
    const c = document.createElement('i');
    c.className = 'rain';
    c.style.left = (Math.random() * 100) + '%';
    c.style.animationDelay = (Math.random() * 0.8).toFixed(2) + 's';
    c.style.animationDuration = (1.6 + Math.random() * 1.2).toFixed(2) + 's';
    c.style.setProperty('--s', (0.7 + Math.random() * 0.8).toFixed(2));
    fx.appendChild(c);
  }
  setTimeout(() => { fx.innerHTML = ''; }, 3400);
}
function countUpEl(el, to, ms) {
  if (!el) return;
  const dec = to % 1 ? 2 : 0;
  if (reduce) { el.textContent = nf(to, dec); return; }
  const t0 = performance.now();
  (function f(now) {
    const k = Math.min(1, (now - t0) / ms), e = 1 - Math.pow(1 - k, 3);
    el.textContent = nf(to * e, dec);
    if (k < 1) requestAnimationFrame(f);
  })(t0);
}
function closePopup() { clearTimeout(popTimer); const el = $('#pop'); el.className = ''; el.innerHTML = ''; setTimeout(flushPending, 350); }
function rewardPopup(o) {
  const el = $('#pop');
  el.innerHTML = '<div class="popcard"><div class="popicon">' + (o.iconHtml || (o.level ? medal(o.level, 92) : coin(88))) + '</div><div class="poptitle">' + esc(o.title) + '</div>' +
    (o.amount != null ? '<div class="popamt">+<b id="popAmt">0</b> <small>' + t('coins') + '</small></div>' : '') +
    (o.sub ? '<div class="popsub">' + esc(o.sub) + '</div>' : '') +
    '<button class="btn gold" data-act="pop-close">' + t('awesome') + '</button></div>';
  el.className = 'show';
  coinRain(); haptic('success'); haptic2();
  if (o.amount != null) countUpEl($('#popAmt'), o.amount, 900);
  clearTimeout(popTimer);
  popTimer = setTimeout(closePopup, 7000);
}
function flushPending() {
  if ($('#pop').className === 'show') return;
  if (pendingLevel) {
    const lv = pendingLevel; pendingLevel = null;
    rewardPopup({level: lv.level, title: t('level_up'), sub: lv.name + ' · +' + nf(lv.bonus) + ' ' + t('coins_min')});
  } else if (pendingSkin) {
    const sk = pendingSkin; pendingSkin = null;
    rewardPopup({iconHtml: '<div class="popskin">' + hamsterPreview(sk) + '</div>', title: t('new_skin'), sub: t('skin_' + sk)});
  } else if (pendingDone !== null) {
    const d = pendingDone; pendingDone = null;
    if (d > 0) rewardPopup({title: t('mining_completed'), amount: d});
  }
}

/* ---------- Welcome tour, daily prompt, appearance ---------- */
const WEL = [{ic: 'pick', tone: 'gold', t: 'wel1_t', d: 'wel1_d'}, {ic: 'bolt', tone: 'teal', t: 'wel2_t', d: 'wel2_d'}, {ic: 'swap', tone: 'green', t: 'wel3_t', d: 'wel3_d'}];
function welcomeModal(i) {
  const w = WEL[i], last = i === WEL.length - 1;
  openModal('<div class="wel">' + tile(w.ic, w.tone) + '<h3>' + t(w.t) + '</h3><p>' + t(w.d) + '</p><div class="dots2">' + WEL.map((x, k) => '<i class="' + (k === i ? 'on' : '') + '"></i>').join('') + '</div></div>' +
    '<button class="btn gold" data-act="wel-next" data-id="' + i + '">' + (last ? t('lets_start') : t('next')) + '</button>' + (last ? '' : '<button class="btn" data-act="wel-skip">' + t('skip') + '</button>'));
}
function maybeDailyPrompt() {
  if (dailyShown || !S.daily.enabled || S.daily.claimed || S.cfg.maintenance) return;
  dailyShown = true;
  if (shell === 'tap') { tapDailyModal(); return; }
  const d = S.daily;
  openModal('<div class="wel">' + tile('gift', 'violet') + '<h3>' + t('daily_reward') + '</h3><p>' + t('daily_prompt') + '</p></div><div class="days">' +
    d.rewards.slice(0, 7).map((r, i) => {
      const n = i + 1, done = n < d.day, cur = n === d.day;
      return '<div class="day ' + (done ? 'done' : (cur ? 'cur' : '')) + '">' + (done ? ic('check') : '<em>' + n + '</em>') + '<b>' + nf(r, 0) + '</b></div>';
    }).join('') + '</div>' +
    '<button class="btn gold" data-act="daily">' + ic('gift') + t('claim') + ' +' + nf(d.rewards[d.day - 1], 0) + '</button><button class="btn" data-act="close">' + t('later') + '</button>');
}
async function openSkins() {
  openModal('<div class="ctitle">' + t('skins') + '</div>' + skel(2));
  try { skinsData = await api('skins'); skinsModal(); }
  catch (e) { toast(e.message, 'err'); closeModal(); }
}
function skinCard(k) {
  let act, note;
  if (k.equipped) act = '<span class="pill green">' + ic('check') + t('equipped') + '</span>';
  else if (k.available) act = '<button class="btn sm gold" data-act="skin-equip" data-id="' + k.id + '">' + t('equip') + '</button>';
  else act = '<div class="buyrow">' + (k.coins > 0 ? '<button class="btn sm" data-act="skin-buy" data-id="' + k.id + '" data-with="coins">' + coin(16) + nf(k.coins, 0) + '</button>' : '') +
    (k.usd > 0 ? '<button class="btn sm green" data-act="skin-buy" data-id="' + k.id + '" data-with="usd">$' + nf(k.usd) + '</button>' : '') + '</div>';
  if (k.owned) note = t('skin_owned'); else if (k.available) note = t('skin_by_level'); else if (k.lv < 99) note = t('unlock_at') + ' ' + k.lv; else note = t('skin_buy_only');
  return '<div class="skincard' + (k.equipped ? ' on' : '') + (k.available ? '' : ' locked') + '"><div class="skinprev">' + hamsterPreview(k.id) + '</div><b>' + esc(t('skin_' + k.id)) + '</b><small>' + note + '</small>' + act + '</div>';
}
function skinsModal() {
  const d = skinsData;
  openModal('<div class="ctitle">' + t('skins') + '</div>' +
    '<div class="toggle" data-act="skin-auto"><div class="rc-t"><b>' + t('skin_auto') + '</b><span>' + t('skin_auto_d') + '</span></div><i class="sw ' + (d.auto ? 'on' : '') + '"></i></div>' +
    '<div class="skins">' + d.items.map(skinCard).join('') + '</div><button class="btn" data-act="close">' + t('close') + '</button>');
}
let spinning = false;
function wheelSvg(prizes) {
  const n = prizes.length, seg = 360 / n, cols = ['#f5b82e', '#2b1a0a'];
  let d = '';
  prizes.forEach((p, i) => {
    const a0 = (i * seg - 90) * Math.PI / 180, a1 = ((i + 1) * seg - 90) * Math.PI / 180, R = 118;
    const x0 = (R * Math.cos(a0)).toFixed(1), y0 = (R * Math.sin(a0)).toFixed(1), x1 = (R * Math.cos(a1)).toFixed(1), y1 = (R * Math.sin(a1)).toFixed(1);
    const mid = i * seg + seg / 2, dark = i % 2 === 1;
    d += '<path d="M0 0 L' + x0 + ' ' + y0 + ' A' + R + ' ' + R + ' 0 0 1 ' + x1 + ' ' + y1 + 'Z" fill="' + (dark ? cols[1] : 'url(#gGold)') + '" stroke="rgba(255,255,255,.25)" stroke-width="1.5"/>' +
      '<text transform="rotate(' + (mid - 90) + ') translate(74 0)" text-anchor="middle" dominant-baseline="middle" font-size="15" font-weight="800" fill="' + (dark ? '#ffd36a' : '#3a2300') + '" font-family="Arial,sans-serif">' + nf(p, 0) + '</text>';
  });
  return '<div class="wheelbox"><svg class="wheel" viewBox="-130 -130 260 260"><circle r="128" fill="#1a1006" stroke="url(#gGold)" stroke-width="5"/><g id="wheelG" style="transform:rotate(0deg)">' + d + '</g><circle r="16" fill="#1a1006" stroke="url(#gGold)" stroke-width="4"/></svg><div class="wptr"></div></div>';
}
function spinModal() {
  const sp = S.spin;
  openModal('<div class="ctitle">' + t('spin_title') + '</div>' + wheelSvg(sp.prizes) +
    '<button class="btn gold" data-act="spin-go" ' + (sp.ready ? '' : 'disabled') + '>' + ic('wheel') + t('spin') + '</button><button class="btn" data-act="close">' + t('close') + '</button>');
}
async function doSpin() {
  if (spinning) return;
  spinning = true;
  try {
    const r = await api('spin');
    const n = S.spin.prizes.length, seg = 360 / n, g = $('#wheelG');
    const target = 360 * 6 - (r.index + 0.5) * seg;
    if (g) { g.style.transition = reduce ? 'none' : 'transform 4s cubic-bezier(.12,.7,.1,1)'; g.style.transform = 'rotate(' + target + 'deg)'; }
    setTimeout(() => { closeModal(); rewardPopup({title: t('spin_title'), amount: r.prize}); render(true); spinning = false; }, reduce ? 200 : 4300);
  } catch (e) { toast(e.message, 'err'); spinning = false; }
}
function appearanceModal() {
  const allow = !!(window.APP_THEME && window.APP_THEME.allow), cur = curTheme(), lvl = myLevel();
  let h = '<div class="ctitle">' + t('appearance') + '</div>';
  const langs = Object.keys(LANGS).filter(c => LANGS[c].lang_name);
  if (langs.length > 1) {
    h += '<div class="sec first">' + t('language') + '</div><div class="chips">' + langs.map(c =>
      '<button class="chipb ' + (c === langCode ? 'on' : '') + '" data-act="lang-set" data-id="' + c + '">' + esc(LANGS[c].lang_name) + '</button>').join('') + '</div>';
  }
  if (allow) {
    h += '<div class="sec">' + t('theme_label') + '</div><div class="themes">' + THEMES.map(th => {
      const need = themeUnlockLevel(th.id), locked = need > lvl;
      return '<button class="themeopt ' + (th.id === cur ? 'on' : '') + (locked ? ' locked' : '') + '" data-theme="' + th.id + '" data-act="theme-set" data-id="' + th.id + '">' +
        '<span class="swatch">' + ic(locked ? 'lock' : th.icon) + '</span><div class="tn"><b>' + esc(t('theme_' + th.id)) + '</b>' + (locked ? '<small>' + t('unlock_at') + ' ' + need + '</small>' : '') + '</div>' + (th.id === cur ? ic('check', 'tick') : '') + '</button>';
    }).join('') + '</div>';
  }
  h += '<div class="sec">' + t('comfort') + '</div>' +
    '<div class="toggle" data-act="toggle-reduce"><div class="rc-t"><b>' + t('reduce_motion') + '</b><span>' + t('reduce_motion_d') + '</span></div><i class="sw ' + (reduce ? 'on' : '') + '"></i></div>' +
    '<div class="toggle" data-act="toggle-big"><div class="rc-t"><b>' + t('large_text') + '</b><span>' + t('large_text_d') + '</span></div><i class="sw ' + (bigText ? 'on' : '') + '"></i></div>' +
    '<button class="btn" data-act="close">' + t('close') + '</button>';
  openModal(h);
}

/* ---------- Live mining numbers ---------- */
function liveCoins() {
  const m = S.mining;
  if (m.status !== 'active') return S.user.coins;
  const now = Math.min(srvNow(), m.ends_at);
  let acc = Math.max(0, now - m.started_at) / 60 * m.speed;
  if (S.mine_room !== null && S.mine_room !== undefined) acc = Math.min(acc, m.credited + S.mine_room);
  return S.user.coins - m.credited + Math.max(acc, m.credited);
}
function liveSession() {
  const m = S.mining;
  if (m.status !== 'active') return m.credited || 0;
  return liveCoins() - S.user.coins + m.credited;
}
function progressPct() {
  const m = S.mining;
  if (m.status === 'completed') return 100;
  if (m.status !== 'active') return 0;
  return Math.min(100, Math.max(0, (srvNow() - m.started_at) / (m.ends_at - m.started_at) * 100));
}
function tick() {
  if (!S) return;
  if (shell === 'tap') { tickTap(); return; }
  const m = S.mining;
  const lc = $('#liveCoins'); if (lc) lc.textContent = nf(liveCoins());
  const lu = $('#liveUsd'); if (lu) lu.textContent = '≈ $' + nf(liveCoins() / (S.cfg.coins_per_usd || 1), 4);
  const p = progressPct();
  const left = m.status === 'active' ? m.ends_at - srvNow() : 0;
  const rt = $('#ringTime'); if (rt) rt.textContent = m.status === 'active' ? dur(left) : '00:00:00';
  const pb = $('#pbar'); if (pb) pb.style.width = p.toFixed(1) + '%';
  const eb = $('#enBar'); if (eb) eb.style.width = (m.status === 'active' ? 100 - p : 100).toFixed(1) + '%';
  const et = $('#enTxt'); if (et) et.textContent = m.status === 'active' ? dur(left) : t('ready');
  const ls = $('#liveSess'); if (ls) ls.textContent = nf(liveSession());
  if (m.status === 'active' && left < -1 && !refreshing) refreshState();
}
async function refreshState(silent = true) {
  if (refreshing) return;
  refreshing = true;
  try {
    await api('state');
    const ae = document.activeElement;
    const typing = ae && /INPUT|SELECT|TEXTAREA/.test(ae.tagName) && $('#view').contains(ae);
    if (silent && !typing) render(true);
  } catch (e) { if (!silent) toast(e.message, 'err'); }
  refreshing = false;
}

/* ---------- Shared pieces ---------- */
function topbar() {
  const u = S.user, lv = S.speed.level, name = u.first_name || u.username || 'Miner';
  $('#topbar').innerHTML =
    '<div class="avatar" data-go="profile"><span>' + esc(name.charAt(0).toUpperCase()) + '</span></div>' +
    '<div class="who"><b>' + esc(name) + '</b><span class="sub">' + esc(lv.name) + ' ' + t('miner') + ' · ' + esc(tierName(u.tier)) + '</span></div>' +
    ((S.cfg.lb_mining || S.cfg.lb_ref) ? '<button class="chip" data-go="leaderboard" aria-label="leaderboard">' + ic('trophy') + '</button>' : '') +
    '<button class="chip" data-act="theme-open" aria-label="appearance">' + ic('palette') + '</button>';
}
function nav() {
  const th = themeDef();
  $('#nav').innerHTML = NAV.map(k => {
    const icn = k === 'home' ? th.mine : (k === 'boost' ? th.boost : NAVIC[k]);
    return '<button data-go="' + k + '" class="' + (view === k ? 'on' : '') + '">' + ic(icn) + '<span>' + esc(t('nav_' + k)) + '</span></button>';
  }).join('');
}
function mineBtn() {
  const m = S.mining;
  if (!S.cfg.mine_enabled) return '<button class="btn" disabled>Mining disabled</button>';
  return m.status === 'active'
    ? '<button class="btn danger" data-act="mine-stop">' + ic('stop') + t('stop_mining') + '</button>'
    : '<button class="btn gold" data-act="mine-start">' + ic(themeDef().boost) + t('start_mining') + '</button>';
}
function statusPill() {
  const m = S.mining;
  if (m.status === 'active') return '<div class="status on"><i></i>' + t('mining_active') + '</div>';
  if (m.status === 'completed') return '<div class="status done">' + ic('check') + t('mining_completed') + ' · +' + nf(m.credited) + '</div>';
  return '<div class="status"><i></i>' + t('mining_idle') + '</div>';
}
function tapEntry() {
  if (!S.tap || !S.tap.enabled) return '';
  return '<div class="card tapentry" data-act="tap-open">' + coin(46) + '<div class="rc-t"><b>' + t('tap_title') + '</b><span>' + t('tap_sub') + '</span></div>' + ic('chev', 'chev') + '</div>';
}
function squadBar() {
  return '<div class="squadbar" data-go="referrals">' + ic('users') + '<span>' + t('squad_bar') + '</span><b>+' + nf(S.cfg.ref_coin_bonus, 0) + '</b></div>';
}
function vHomeVerse() {
  const sp = S.speed, m = S.mining, u = S.user, on = m.status === 'active';
  return squadBar() +
    '<div class="vbal">' + coin(46) + '<b id="liveCoins">' + nf(liveCoins(), 0) + '</b></div>' +
    '<div class="vsub"><span id="liveUsd">≈ $0</span><span class="vp">' + ic(themeDef().boost) + '+' + nf(sp.speed * 60) + ' ' + t('per_hour') + '</span></div>' +
    '<div class="vscene' + (on ? ' on' : '') + '">' + sceneSvg(on) + '</div>' +
    '<div class="energyrow"><div class="en"><div class="en-t">' + ic('zap') + '<b id="enTxt">' + t('ready') + '</b><span>' + t('energy') + '</span></div><div class="enbar"><i id="enBar" style="width:100%"></i></div></div>' +
    '<button class="boostsbtn" data-go="boost">' + ic('rocket') + t('boosts') + '</button></div>' +
    mineBtn() +
    '<div class="statgrid">' +
    '<div class="sbox">' + tile(themeDef().boost, 'gold') + '<b>' + nf(sp.speed) + '</b><span>' + t('coins_min') + '</span></div>' +
    '<div class="sbox">' + tile('pick', 'teal') + '<b id="liveSess">' + nf(liveSession()) + '</b><span>' + t('current_session') + '</span></div>' +
    '<div class="sbox">' + tile('trend', 'violet') + '<b>' + nf(u.total_mined, 0) + '</b><span>' + t('total_mined') + '</span></div></div>' +
    '<div class="actions2"><button data-go="wallet">' + ic('swap') + t('convert') + '</button><button class="wd" data-act="withdraw-open">' + ic('up') + t('withdraw') + '</button></div>' + tapEntry();
}

function leagueBar() {
  const lv = S.speed.level, next = lv.next;
  const span = next !== null ? Math.max(1, next - lv.from) : 1;
  const pct = next === null ? 100 : Math.min(100, Math.max(0, (lv.refs - lv.from) / span * 100));
  return '<div class="league" data-go="boost"><div class="lg-t"><b>' + esc(lv.name) + '</b><span>' + t('level') + ' ' + lv.level + '/' + lv.max + '</span></div>' +
    '<div class="lg-bar"><i style="width:' + pct.toFixed(0) + '%"></i></div><span class="lg-r">' + (next === null ? t('max_level') : lv.refs + '/' + next) + '</span></div>';
}

/* ---------- Views ---------- */
function vHome() {
  if (curTheme() === 'goldverse' || curTheme() === 'satoshi') return vHomeVerse();
  const sp = S.speed, m = S.mining, u = S.user, on = m.status === 'active', isHam = curTheme() === 'hamster';
  const label = on ? t('mining_active') : (m.status === 'completed' ? t('mining_completed') : t('ready'));
  return (isHam ? leagueBar() : '') + '<div class="scenecard ' + (on ? 'on' : '') + (isHam ? ' hamsterscene' : '') + '">' +
    '<div class="scenehead"><span class="live"><i></i>' + label + '</span><span class="timer">' + ic('clock') + '<b id="ringTime">00:00:00</b></span></div>' +
    sceneSvg(on) + (isHam ? '<button class="skinbtn" data-act="skins-open">' + ic('hamster') + t('skins') + '</button>' : '<div class="scenelvl">' + medal(sp.level.level, 40) + '</div>') + '<div class="scenebar"><i id="pbar"></i></div></div>' +
    '<div class="card balancecard"><div class="bc-l">' + coin(42) + '<div><span>' + t('coin_balance') + '</span><b id="liveCoins">' + nf(liveCoins()) + '</b></div></div>' +
    '<div class="bc-r"><b id="liveUsd">≈ $0</b><span>' + ic(themeDef().boost) + '+' + nf(sp.speed * 60) + ' ' + t('per_hour') + '</span></div></div>' +
    '<div class="statgrid">' +
    '<div class="sbox">' + tile(themeDef().boost, 'gold') + '<b>' + nf(sp.speed) + '</b><span>' + t('coins_min') + '</span></div>' +
    '<div class="sbox">' + tile('pick', 'teal') + '<b id="liveSess">' + nf(liveSession()) + '</b><span>' + t('current_session') + '</span></div>' +
    '<div class="sbox">' + tile('trend', 'violet') + '<b>' + nf(u.total_mined, 0) + '</b><span>' + t('total_mined') + '</span></div></div>' +
    mineBtn() +
    '<div class="actions2"><button data-go="wallet">' + ic('swap') + t('convert') + '</button><button class="wd" data-act="withdraw-open">' + ic('up') + t('withdraw') + '</button></div>' + tapEntry();
}

function premiumCard() {
  const c = S.cfg, u = S.user;
  if (!c.premium_enabled) return '';
  if (u.premium_boost) {
    return '<div class="card okcard"><div class="rowc">' + tile('gem', 'green') + '<div class="rc-t"><b>' + t('premium_active') + '</b><span>+' + nf(c.premium_bonus) + ' ' + t('coins_min') + '</span></div>' + ic('check', 'okic') + '</div></div>';
  }
  return '<div class="card goldcard"><div class="rowc">' + tile('gem', 'gold') + '<div class="rc-t"><b>' + t('premium_boost') + '</b><span>+' + nf(c.premium_bonus) + ' ' + t('coins_min') + '</span></div></div>' +
    '<p class="note">' + esc(c.premium_message) + '</p>' +
    '<div class="steps"><div class="step"><i>1</i><span>' + t('step1') + '</span></div><div class="step"><i>2</i><span>' + t('step2') + '</span></div></div>' +
    (c.premium_url ? '<button class="btn" data-act="premium-join">' + ic('send') + esc(c.premium_join_text) + '</button>' : '') +
    '<button class="btn gold" data-act="premium-verify">' + ic('check') + t('verify_membership') + '</button></div>';
}

function vBoost() {
  const sp = S.speed, c = S.cfg, lv = sp.level;
  const line = (k, v) => '<div class="kv"><span>' + k + '</span><b>' + v + '</b></div>';
  let h = '<div class="card metric"><div class="m-ic">' + ic(themeDef().boost) + '</div><div class="m-l">' + t('boost_title') + '</div><div class="m-v">' + nf(sp.speed * 60) + '</div>' +
    '<div class="m-s">' + t('coins') + ' ' + t('per_hour') + ' · ' + nf(sp.speed) + ' ' + t('coins_min') + '</div></div>';
  h += premiumCard();
  h += '<div class="card"><div class="ctitle">' + t('speed_breakdown') + '</div>' +
    line(t('base_speed'), nf(sp.base)) +
    line(t('level_bonus') + ' · LVL ' + lv.level, '+' + nf(sp.level_bonus)) +
    line(t('referral_bonus') + ' · ' + sp.active_refs, '+' + nf(sp.referral)) +
    line(t('premium_bonus'), '+' + nf(sp.premium)) +
    line(t('extra_boost'), '+' + nf(sp.boost)) +
    line(t('multiplier') + ' · ' + esc(tierName(S.user.tier)), '×' + nf(sp.multiplier)) + '</div>';
  h += '<div class="card"><div class="ctitle">' + t('level_roadmap') + '</div><div class="tl">' + (c.levels || []).map(L =>
    '<div class="tl-i ' + (L.level === lv.level ? 'cur' : (L.level < lv.level ? 'done' : '')) + '">' + medal(L.level, 38) +
    '<div class="rc-t"><b>' + esc(L.name) + '</b><span>' + L.need + ' ' + t('friends') + '</span></div><span class="pill green">+' + nf(L.bonus) + '</span></div>').join('') + '</div></div>';
  h += '<button class="btn gold" data-go="referrals">' + ic('users') + t('invite_friends') + '</button>';
  return h;
}

let tasksCache = null;
let earnTab = 'daily';
const TASKIC = {telegram_channel: ['send', 'blue'], telegram_group: ['users', 'blue'], website: ['link', 'teal'], video: ['play', 'orange'], social: ['heart', 'violet']};
const erow = (icn, tone, title, reward, btn, extra) =>
  '<div class="card erow"><div class="erow-in"><span class="eic ' + tone + '">' + ic(icn) + '</span><div class="rc-t"><b>' + title + '</b><span class="rw2">' + reward + '</span></div>' + btn + '</div>' + (extra || '') + '</div>';
const rwd = n => coin(16) + '<em>+' + nf(n, 0) + '</em>';
function vTasks() {
  const d = S.daily, ci = S.checkin, sp = S.spin;
  let h = '<div class="etabs"><button class="' + (earnTab === 'daily' ? 'on' : '') + '" data-etab="daily">' + t('tab_daily') + '</button><button class="' + (earnTab === 'tasks' ? 'on' : '') + '" data-etab="tasks">' + t('tab_tasks') + '</button></div>';
  if (earnTab === 'daily') {
    if (d.enabled) {
      const days = '<div class="days">' + d.rewards.slice(0, 7).map((r, i) => {
        const n = i + 1, done = d.claimed ? n <= d.day : n < d.day, cur = !d.claimed && n === d.day;
        return '<div class="day ' + (done ? 'done' : (cur ? 'cur' : '')) + '">' + (done ? ic('check') : '<em>' + n + '</em>') + '<b>' + nf(r, 0) + '</b></div>';
      }).join('') + '</div>';
      h += erow('gift', 'violet', t('daily_reward') + ' · ' + d.streak + ' ' + t('streak'), rwd(d.rewards[d.day - 1]),
        '<button class="gbtn" data-act="daily" ' + (d.claimed ? 'disabled' : '') + '>' + (d.claimed ? t('claimed') : t('claim')) + '</button>', days);
    }
    if (ci.enabled) {
      h += erow('flame', 'orange', t('check_in') + ' · ' + ci.streak + ' ' + t('streak'), rwd(ci.reward) + '<small>' + ci.streak_days + 'd = +' + nf(ci.streak_bonus, 0) + '</small>',
        '<button class="gbtn" data-act="checkin" ' + (ci.claimed ? 'disabled' : '') + '>' + (ci.claimed ? t('claimed') : t('claim')) + '</button>');
    }
    if (sp && sp.enabled) {
      h += erow('wheel', 'gold', t('spin_title'), sp.ready ? '<em>' + t('spin_free') + '</em>' : '<em>' + t('spin_next') + ' ' + dur(sp.next_in) + '</em>',
        '<button class="gbtn" data-act="spin-open" ' + (sp.ready ? '' : 'disabled') + '>' + t('spin') + '</button>');
    }
    if (!d.enabled && !ci.enabled && !(sp && sp.enabled)) h += emptyBox('gift', 'violet', t('no_tasks'), t('no_tasks_d'));
  } else {
    h += '<div id="taskList">' + (tasksCache ? taskHtml(tasksCache) : skel(3)) + '</div>';
  }
  return h;
}
function taskHtml(items) {
  if (!S.cfg.tasks_enabled || !items.length) return emptyBox('tasks', 'blue', t('no_tasks'), t('no_tasks_d'));
  return items.map(k => {
    const ti = TASKIC[k.type] || ['target', 'gold'];
    let btn;
    if (k.status === 'done') btn = '<span class="pill green">' + ic('check') + t('done') + '</span>';
    else if (k.status === 'started') btn = '<button class="gbtn" data-act="task-claim" data-id="' + k.id + '">' + t('verify') + '</button>';
    else btn = '<button class="gbtn ghost" data-act="task-start" data-id="' + k.id + '">' + t('start') + '</button>';
    return erow(ti[0], ti[1], esc(k.title) + (k.description ? '<small class="td">' + esc(k.description) + '</small>' : ''), rwd(k.reward), btn);
  }).join('');
}
async function loadTasks() {
  try { const r = await api('tasks'); tasksCache = r.items; const el = $('#taskList'); if (el) el.innerHTML = (shell === 'tap' ? tapTaskHtml : taskHtml)(tasksCache); }
  catch (e) { toast(e.message, 'err'); }
}

let refData = null;
function vReferrals() {
  const u = S.user, c = S.cfg;
  let h = '<div class="card goldcard"><div class="rowc">' + tile('users', 'gold') + '<div class="rc-t"><b>' + t('invite_earn') + '</b><span>+' + nf(c.ref_coin_bonus, 0) + ' ' + t('coins') + ' · +' + nf(c.ref_speed_bonus) + ' ' + t('coins_min') + '</span></div></div>' +
    '<div class="linkbox"><span>' + esc(u.ref_link || 'Bot username is not configured') + '</span><button data-act="copy-ref" aria-label="copy">' + ic('copy') + '</button></div>' +
    '<button class="btn gold" data-act="invite">' + ic('share') + t('invite_friends') + '</button></div>';
  if (!refData) return h + skel(2);
  h += '<div class="stats2">' +
    stat('users', 'blue', t('total_referrals'), refData.total) + stat('check', 'green', t('active_referrals'), refData.active) +
    stat('gift', 'violet', t('referral_earnings'), nf(refData.earnings, 0)) + stat('bolt', 'gold', t('speed_bonus'), '+' + nf(S.speed.referral)) + '</div>';
  h += '<div class="sec">' + t('referral_history') + '</div><div class="card list">' +
    (refData.history.length ? refData.history.map(r => '<div class="li"><span class="ini">' + esc(r.name.replace('@', '').charAt(0).toUpperCase()) + '</span><div class="rc-t"><b>' + esc(r.name) + '</b><span>' + fdate(r.date) + '</span></div>' +
      '<span class="pill ' + (r.active ? 'green' : 'gold') + '">' + (r.active ? t('active') : t('pending')) + '</span></div>').join('') : emptyBox('users', 'blue', t('no_referrals'), t('no_referrals_d'))) + '</div>';
  if (c.lb_ref) h += rowcard('trophy', 'gold', t('referral_lb') + ' ' + t('leaderboard'), t('leaderboard'), 'data-go="leaderboard" data-tab="referral"');
  return h;
}
async function loadRefs() {
  try { refData = await api('referrals'); if (view === 'referrals') render(true); } catch (e) { toast(e.message, 'err'); }
}

let wdList = null;
function vWallet() {
  const u = S.user, c = S.cfg;
  let h = '<div class="duo"><div class="card bal gold"><div class="b-h">' + coin(24) + '<span>' + t('coin_balance') + '</span></div><b>' + nf(liveCoins(), 0) + '</b></div>' +
    '<div class="card bal green"><div class="b-h">' + usdLogo(24) + '<span>' + t('usd_balance') + '</span></div><b>$' + nf(u.usd) + '</b></div></div>';
  if (c.conv_enabled) {
    h += '<div class="card swapcard"><div class="rate">' + nf(c.coins_per_usd, 0) + ' ' + t('coins') + ' = $1.00 · ' + t('min_label') + ' ' + nf(c.conv_min, 0) + '</div>' +
      '<div class="leg">' + coin(32) + '<div class="leg-t"><span>' + t('swap_from') + '</span><b>' + t('coins') + '</b></div><input id="cvIn" type="number" inputmode="numeric" min="0" placeholder="0"></div>' +
      '<div class="pcts"><button data-act="cv-pct" data-pct="25">25%</button><button data-act="cv-pct" data-pct="50">50%</button><button data-act="cv-pct" data-pct="75">75%</button><button data-act="cv-pct" data-pct="100">MAX</button></div>' +
      '<div class="swapmid">' + ic('swap') + '</div>' +
      '<div class="leg out">' + usdLogo(32) + '<div class="leg-t"><span>' + t('swap_to') + '</span><b>USD</b></div><div class="outv" id="cvOut">$0.0000</div></div>' +
      '<button class="btn gold" data-act="cv-go">' + ic('swap') + t('convert') + '</button></div>';
  }
  h += '<button class="btn green" data-act="withdraw-open">' + ic('up') + t('withdraw') + ' USD</button>' +
    '<button class="btn" data-go="history">' + ic('history') + t('history') + '</button>';
  h += '<div class="stats2">' +
    stat('trend', 'gold', t('earned_coins'), nf(u.total_earned, 0)) + stat('swap', 'teal', t('converted_coins'), nf(u.converted_coins, 0)) +
    stat('wallet', 'green', t('total_earned') + ' USD', '$' + nf(u.usd_earned)) + stat('up', 'blue', t('total_withdrawn'), '$' + nf(u.usd_withdrawn)) +
    stat('clock', 'orange', t('pending_withdrawal'), '$' + nf(u.usd_pending)) + stat('wallet', 'violet', t('available_withdrawal'), '$' + nf(u.usd)) + '</div>';
  if (wdList && wdList.length) {
    h += '<div class="sec">' + t('recent_withdrawals') + '</div><div class="card list">' + wdList.slice(0, 6).map(w =>
      '<div class="li">' + tile('up', 'green') + '<div class="rc-t"><b>$' + nf(w.amount) + ' · ' + esc(w.method) + '</b><span>' + fdate(w.created_at) + '</span></div><div class="ra">' +
      '<span class="pill ' + (w.status === 'paid' ? 'green' : (w.status === 'pending' || w.status === 'approved' ? 'gold' : 'red')) + '">' + esc(w.status) + '</span>' +
      (w.status === 'pending' ? '<button class="lnk" data-act="wd-cancel" data-id="' + w.id + '">' + t('cancel') + '</button>' : '') + '</div></div>').join('') + '</div>';
  }
  return h;
}
async function loadWd() {
  try { const r = await api('withdrawals'); wdList = r.items; if (view === 'wallet') render(true); } catch (e) {}
}

let hist = {items: [], done: false, loaded: false};
const TXIC = {mining: ['pick', 'gold'], referral: ['users', 'blue'], task: ['target', 'teal'], daily_reward: ['gift', 'violet'], checkin: ['flame', 'orange'],
  conversion: ['swap', 'teal'], withdrawal: ['up', 'green'], refund: ['down', 'blue'], admin_adjust: ['shield', 'violet'], tap: ['zap', 'blue'], spin: ['wheel', 'gold'], skin: ['hamster', 'violet']};
function vHistory() {
  if (!hist.loaded) return '<div class="sec first">' + t('history') + '</div>' + skel(4);
  const rows = hist.items.map(x => {
    const usd = x.currency === 'USD', amt = Number(x.amount), sign = amt >= 0 ? '+' : '-';
    const val = usd ? sign + '$' + nf(Math.abs(amt)) : sign + nf(Math.abs(amt), amt % 1 ? 2 : 0);
    const ti = TXIC[x.type] || ['wallet', 'gold'];
    return '<div class="li">' + tile(ti[0], ti[1]) + '<div class="rc-t"><b>' + esc((x.type || '').replace('_', ' ')) + (x.note ? ' · ' + esc(x.note) : '') + '</b><span>' + fdate(x.created_at) + ' · ' + esc(x.tx_id) + '</span></div>' +
      '<div class="ra"><b class="' + (amt >= 0 ? 'pos' : 'neg') + '">' + val + '</b><span class="pill ' + (x.status === 'completed' || x.status === 'paid' ? 'green' : (x.status === 'rejected' || x.status === 'cancelled' ? 'red' : 'gold')) + '">' + esc(x.status) + '</span></div></div>';
  }).join('');
  return '<div class="sec first">' + t('history') + '</div><div class="card list">' + (rows || emptyBox('history', 'teal', t('no_history'), t('no_history_d'))) + '</div>' +
    (hist.done || !hist.items.length ? '' : '<button class="btn" data-act="hist-more">' + t('load_more') + '</button>');
}
async function loadHist(more) {
  try {
    const before = more && hist.items.length ? hist.items[hist.items.length - 1].id : 0;
    const r = await api('history', before ? {before} : {});
    hist.items = more ? hist.items.concat(r.items) : r.items;
    hist.done = r.items.length < 30;
    hist.loaded = true;
    if (view === 'history') render(true);
  } catch (e) { toast(e.message, 'err'); }
}

let lbData = null;
function vLeaderboard() {
  const c = S.cfg;
  if (!lbData) return skel(3);
  if (lbTab === 'mining' && !c.lb_mining) lbTab = 'referral';
  if (lbTab === 'referral' && !c.lb_ref) lbTab = 'mining';
  const d = lbData[lbTab];
  let h = '<div class="tabs">' +
    (c.lb_mining ? '<button data-tab="mining" class="' + (lbTab === 'mining' ? 'on' : '') + '">' + t('mining_lb') + '</button>' : '') +
    (c.lb_ref ? '<button data-tab="referral" class="' + (lbTab === 'referral' ? 'on' : '') + '">' + t('referral_lb') + '</button>' : '') + '</div>';
  if (!d) return h + emptyBox('trophy', 'gold', t('lb_empty'), t('lb_empty_d'));
  h += '<div class="card list">' + (d.list.length ? d.list.map((r, i) =>
    '<div class="li ' + (r.me ? 'me' : '') + '"><span class="rank r' + (i < 3 ? i + 1 : 0) + '">' + (i + 1) + '</span><div class="rc-t"><b>' + esc(r.name) + '</b></div><b class="gv">' +
    (lbTab === 'mining' ? nf(r.value, 0) : r.value) + '</b></div>').join('') : emptyBox('trophy', 'gold', t('lb_empty'), t('lb_empty_d'))) + '</div>';
  if (lbTab === 'mining' && d.rank) h += '<div class="hint center">' + t('your_rank') + ' #' + d.rank + '</div>';
  return h;
}
async function loadLb() {
  try { lbData = await api('leaderboard'); if (view === 'leaderboard') render(true); } catch (e) { toast(e.message, 'err'); }
}

function vProfile() {
  const u = S.user, lv = S.speed.level, name = u.first_name || u.username || 'Miner';
  const kv = (k, v) => '<div class="kv"><span>' + k + '</span><b>' + v + '</b></div>';
  return '<div class="card prof"><div class="avatar big"><span>' + esc(name.charAt(0).toUpperCase()) + '</span></div><b>' + esc(name) + '</b><span class="sub">' +
    (u.username ? '@' + esc(u.username) : '') + '</span><div class="pbadge">' + medal(lv.level, 30) + '<span>' + esc(lv.name) + ' · ' + esc(tierName(u.tier)) + '</span></div></div>' +
    '<div class="card">' + kv(t('telegram_id'), esc(u.telegram_id)) + kv(t('current_speed'), nf(S.speed.speed) + ' ' + t('coins_min')) + kv(t('total_coins'), nf(u.coins)) +
    kv(t('usd_balance'), '$' + nf(u.usd)) + kv(t('total_referrals'), u.total_refs) + kv(t('total_withdrawals'), u.withdrawals) + kv(t('account_status'), esc(u.status)) + '</div>' +
    '<div class="card"><div class="ctitle">' + t('referral_link') + '</div><div class="linkbox"><span>' + esc(u.ref_link || '—') + '</span><button data-act="copy-ref" aria-label="copy">' + ic('copy') + '</button></div></div>' +
    '<button class="btn gold" data-act="copy-ref">' + ic('copy') + t('copy_link') + '</button>' +
    (S.cfg.support_url ? '<button class="btn" data-act="support">' + ic('chat') + t('support') + '</button>' : '');
}

/* ---------- Render ---------- */
function render(keepScroll) {
  if (!S) return;
  if (tapOnly() && shell !== 'tap') { shell = 'tap'; applyTapVars(); }
  if (shell === 'tap' && S.tap && S.tap.enabled) { renderTap(keepScroll); return; }
  if (shell === 'tap') { shell = 'main'; clearTapVars(); }
  topbar(); nav();
  const V = {home: vHome, boost: vBoost, tasks: vTasks, referrals: vReferrals, wallet: vWallet, history: vHistory, leaderboard: vLeaderboard, profile: vProfile};
  const y = window.scrollY;
  if (S.cfg.maintenance) {
    $('#view').innerHTML = '<div class="maint">' + tile('tool', 'gold') + '<h2>' + t('maintenance') + '</h2></div>';
    return;
  }
  $('#view').innerHTML = (V[view] || vHome)();
  if (keepScroll) window.scrollTo(0, y);
  tick();
  flushPending();
}
function go(v, tab) {
  view = v;
  if (tab) lbTab = tab;
  closeModal();
  window.scrollTo(0, 0);
  if (tg && tg.BackButton) { (v === 'history' || v === 'leaderboard' || v === 'profile') ? tg.BackButton.show() : tg.BackButton.hide(); }
  render();
  if (v === 'tasks' && earnTab === 'tasks') loadTasks();
  if (v === 'referrals') loadRefs();
  if (v === 'wallet') loadWd();
  if (v === 'history') loadHist(false);
  if (v === 'leaderboard') loadLb();
}

/* ---------- Modals ---------- */
function withdrawModal(pre) {
  const c = S.cfg;
  openModal('<div class="ctitle">' + t('withdraw') + '</div><div class="note">' + t('available_withdrawal') + ': $' + nf(S.user.usd) + ' · ' + t('min_label') + ' $' + nf(c.wd_min) + (c.wd_max > 0 ? ' · ' + t('max_label') + ' $' + nf(c.wd_max) : '') + '</div>' +
    '<label>' + t('amount') + ' (USD)</label><input id="wdAmt" type="number" inputmode="decimal" step="0.01" placeholder="' + nf(c.wd_min) + '">' +
    '<label>' + t('payment_method') + '</label><select id="wdMethod">' + c.wd_methods.map(m => '<option' + (m === pre ? ' selected' : '') + '>' + esc(m) + '</option>').join('') + '</select>' +
    '<label>' + t('wallet_address') + '</label><input id="wdAddr" type="text" autocomplete="off" autocapitalize="off" spellcheck="false">' +
    '<button class="btn green" data-act="wd-go">' + ic('up') + t('confirm_withdrawal') + '</button><button class="btn" data-act="close">' + t('close') + '</button>');
}

/* ---------- Events ---------- */
document.addEventListener('click', async e => {
  const vs = e.target.closest('.vscene');
  if (vs && !e.target.closest('button')) {
    vs.querySelectorAll('.tapanim').forEach(a => { try { a.beginElement(); } catch (er) {} });
    try { tg && tg.HapticFeedback && tg.HapticFeedback.impactOccurred('light'); } catch (er) {}
  }
  const hs = e.target.closest('.scenecard.hamsterscene');
  if (hs && !e.target.closest('button')) {
    hs.classList.remove('tapped'); void hs.offsetWidth; hs.classList.add('tapped');
    try { tg && tg.HapticFeedback && tg.HapticFeedback.impactOccurred('light'); } catch (er) {}
  }
  const g = e.target.closest('[data-go]');
  if (g) { go(g.dataset.go, g.dataset.tab); return; }
  const tg2 = e.target.closest('[data-tgo]');
  if (tg2) { tgo(tg2.dataset.tgo); return; }
  const et = e.target.closest('[data-etab]');
  if (et) { earnTab = et.dataset.etab; render(true); if (earnTab === 'tasks') loadTasks(); return; }
  const tabBtn = e.target.closest('.tabs [data-tab]');
  if (tabBtn) { lbTab = tabBtn.dataset.tab; render(true); return; }
  const a = e.target.closest('[data-act]');
  if (!a) return;
  const act = a.dataset.act, id = a.dataset.id;
  switch (act) {
    case 'close': closeModal(); break;
    case 'theme-open': appearanceModal(); break;
    case 'theme-set':
      if (themeUnlockLevel(id) > myLevel()) { toast(t('unlock_at') + ' ' + themeUnlockLevel(id), 'err'); break; }
      applyTheme(id); closeModal(); render(true); haptic('success'); break;
    case 'toggle-reduce': reduce = !reduce; lsSet('miner_reduce', reduce ? '1' : '0'); applyPrefs(); appearanceModal(); render(true); break;
    case 'toggle-big': bigText = !bigText; lsSet('miner_big', bigText ? '1' : '0'); applyPrefs(); appearanceModal(); break;
    case 'lang-set': setLang(id); appearanceModal(); render(true); break;
    case 'pop-close': closePopup(); break;
    case 'tap-open': enterTap(); break;
    case 'tap-exit': leaveTap(); break;
    case 'tap-history': tapHistoryModal(); break;
    case 'tap-daily': tapDailyModal(); break;
    case 'tap-convert': if (!tapOnly()) { leaveTap(); go('wallet'); } break;
    case 'spin-open': spinModal(); break;
    case 'spin-go': doSpin(); break;
    case 'skins-open': openSkins(); break;
    case 'skin-auto': run(async () => { const r = await api('skin_auto'); skinsData = await api('skins'); skinsModal(); return r; }); break;
    case 'skin-equip': run(async () => { const r = await api('skin_equip', {id}); skinsData = await api('skins'); skinsModal(); return r; }); break;
    case 'skin-buy':
      if (await confirmBox(t('confirm_buy'))) {
        const res = await run(async () => { const r = await api('skin_buy', {id, with: a.dataset.with}); skinsData = await api('skins'); skinsModal(); return r; });
        if (res) rewardPopup({iconHtml: '<div class="popskin">' + hamsterPreview(id) + '</div>', title: t('new_skin'), sub: t('skin_' + id)});
      }
      break;
    case 'wel-next': { const i = Number(id); if (i >= WEL.length - 1) { closeModal(); maybeDailyPrompt(); } else welcomeModal(i + 1); break; }
    case 'wel-skip': closeModal(); maybeDailyPrompt(); break;
    case 'mine-start': run(() => api('mine_start')); break;
    case 'mine-stop': if (await confirmBox(t('confirm_stop'))) run(() => api('mine_stop')); break;
    case 'premium-join': openLink(S.cfg.premium_url); break;
    case 'premium-verify': run(() => api('premium_verify')); break;
    case 'daily': closeModal(); run(() => api('daily_claim'), null, t('daily_reward')); break;
    case 'checkin': run(() => api('checkin_claim'), null, t('check_in')); break;
    case 'task-start':
      run(async () => { const r = await api('task_start', {id: Number(id)}); openLink(r.url); await loadTasks(); return null; });
      break;
    case 'task-claim':
      run(async () => { const r = await api('task_claim', {id: Number(id)}); await loadTasks(); return r; }, null, t('tasks'));
      break;
    case 'invite': {
      const l = S.user.ref_link;
      if (!l) { toast('Referral link is not available yet', 'err'); break; }
      openLink('https://t.me/share/url?url=' + encodeURIComponent(l) + '&text=' + encodeURIComponent('Start mining with me and earn coins!'));
      break;
    }
    case 'copy-ref': if (S.user.ref_link) copyText(S.user.ref_link); break;
    case 'support': openLink(S.cfg.support_url); break;
    case 'cv-pct': { const i = $('#cvIn'); i.value = Math.floor(S.user.coins * Number(a.dataset.pct) / 100); i.dispatchEvent(new Event('input', {bubbles: true})); break; }
    case 'cv-go': run(async () => { const r = await api('convert', {coins: Number($('#cvIn').value)}); closeModal(); return r; }); break;
    case 'withdraw-open': withdrawModal(a.dataset.method); break;
    case 'wd-go':
      run(async () => {
        const r = await api('withdraw', {amount: Number($('#wdAmt').value), method: $('#wdMethod').value, address: $('#wdAddr').value.trim()});
        closeModal(); wdList = null; loadWd(); return r;
      });
      break;
    case 'wd-cancel': if (await confirmBox(t('cancel') + '?')) run(async () => { const r = await api('withdraw_cancel', {id: Number(id)}); loadWd(); return r; }); break;
    case 'hist-more': loadHist(true); break;
  }
});
document.addEventListener('input', e => {
  if (e.target && e.target.id === 'cvIn') {
    const o = $('#cvOut');
    if (o) o.textContent = '$' + (Math.floor(Number(e.target.value || 0) / S.cfg.coins_per_usd * 10000) / 10000).toFixed(4);
  }
});


/* ================= Tap game shell (admin switch) ================= */
let shell = 'main';
let tview = 'tap';
let tapPending = 0;
let tapFlushTimer = null;
let tapSending = false;
let tapLastMsg = 0;
let tapLastHaptic = 0;
function hex2rgb(h) { h = String(h).replace('#', ''); return [parseInt(h.slice(0, 2), 16) || 0, parseInt(h.slice(2, 4), 16) || 0, parseInt(h.slice(4, 6), 16) || 0]; }
function rgb2hex(r, g, b) { return '#' + [r, g, b].map(x => Math.max(0, Math.min(255, Math.round(x))).toString(16).padStart(2, '0')).join(''); }
function mixHex(a, b, k) { const x = hex2rgb(a), y = hex2rgb(b); return rgb2hex(x[0] + (y[0] - x[0]) * k, x[1] + (y[1] - x[1]) * k, x[2] + (y[2] - x[2]) * k); }
const lighten = (h, k) => mixHex(h, '#ffffff', k);
const darken = (h, k) => mixHex(h, '#000000', k);
const TAPVARS = ['--bg', '--s1', '--s2', '--s3', '--gold', '--gold2', '--amber', '--acc-rgb', '--text', '--c1', '--c2', '--c3', '--line', '--line2', '--tp-glow'];
function applyTapVars() {
  const c = S.tap.colors, st = document.documentElement.style, rgb = hex2rgb(c.accent).join(',');
  st.setProperty('--bg', c.bg); st.setProperty('--s1', c.card); st.setProperty('--s2', lighten(c.card, .05)); st.setProperty('--s3', lighten(c.card, .1));
  st.setProperty('--gold', c.accent); st.setProperty('--gold2', c.accent); st.setProperty('--amber', c.accent2); st.setProperty('--acc-rgb', rgb);
  st.setProperty('--text', c.text); st.setProperty('--c1', lighten(c.accent, .5)); st.setProperty('--c2', c.accent); st.setProperty('--c3', c.accent2);
  st.setProperty('--line', 'rgba(' + rgb + ',.28)'); st.setProperty('--line2', 'rgba(' + rgb + ',.45)'); st.setProperty('--tp-glow', c.glow);
  document.documentElement.setAttribute('data-shell', 'tap');
}
function clearTapVars() {
  const st = document.documentElement.style;
  TAPVARS.forEach(k => st.removeProperty(k));
  document.documentElement.removeAttribute('data-shell');
}
function tapEnergyNow() {
  const t0 = S.tap;
  return Math.max(0, Math.min(t0.max, t0.energy + (srvNow() - t0.energy_at) * t0.regen) - tapPending);
}
function tapBalance() { return S.user.coins + tapPending * S.tap.per_tap; }
function tapDec() { return S.tap.per_tap < 1 ? 3 : 2; }
function tapOnly() { return !!(S.tap && S.tap.enabled && S.tap.open_mode === 'only'); }
function enterTap() {
  if (!S.tap || !S.tap.enabled) return;
  shell = 'tap'; tview = 'tap';
  applyTapVars(); closeModal(); window.scrollTo(0, 0);
  render();
}
function leaveTap() {
  if (tapOnly()) return;
  tapFlush();
  shell = 'main'; clearTapVars();
  try { sessionStorage.setItem('miner_shell_main', '1'); } catch (e) {}
  closeModal(); window.scrollTo(0, 0);
  render();
}
function tgo(v) {
  tview = v; closeModal(); window.scrollTo(0, 0); render();
  if (v === 'task') { if (!tasksCache) loadTasks(); }
  if (v === 'refer') loadRefs();
}
function tapEmblem() {
  if (S.tap.logo) return '<image href="' + esc(S.tap.logo) + '" x="62" y="62" width="116" height="116" preserveAspectRatio="xMidYMid meet"/>';
  return '<g transform="translate(5 8)" opacity=".3"><path d="M130 50 L82 128 H114 L104 190 L160 106 H126Z" fill="#000"/></g>' +
    '<path d="M130 50 L82 128 H114 L104 190 L160 106 H126Z" fill="url(#tcB)"/><path d="M130 50 L82 128 H101 L126 82Z" fill="rgba(255,255,255,.6)"/>';
}
function tapCoinSvg() {
  const c = S.tap.colors, k = c.coin;
  const sweep = reduce ? '' : '<g clip-path="url(#tcClip)"><g><animateTransform attributeName="transform" type="translate" values="-280 0;280 0" dur="3.8s" repeatCount="indefinite"/><rect x="-30" y="-30" width="44" height="300" fill="#fff" opacity=".3" transform="rotate(24 0 120)"/></g></g>';
  return '<div class="tapcoin" id="tapCoin"><svg viewBox="0 0 240 240"><defs>' +
    '<radialGradient id="tcF" cx="34%" cy="28%" r="82%"><stop offset="0" stop-color="' + lighten(k, .45) + '"/><stop offset=".6" stop-color="' + k + '"/><stop offset="1" stop-color="' + darken(k, .36) + '"/></radialGradient>' +
    '<linearGradient id="tcR" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + lighten(k, .6) + '"/><stop offset=".5" stop-color="' + k + '"/><stop offset="1" stop-color="' + darken(k, .55) + '"/></linearGradient>' +
    '<linearGradient id="tcB" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="' + lighten(k, .7) + '"/></linearGradient>' +
    '<clipPath id="tcClip"><circle cx="120" cy="120" r="104"/></clipPath></defs>' +
    '<circle cx="120" cy="120" r="119" fill="url(#tcR)"/><circle cx="120" cy="120" r="113" fill="' + darken(k, .64) + '"/>' +
    '<circle cx="120" cy="120" r="108.5" fill="none" stroke="' + lighten(k, .4) + '" stroke-opacity=".4" stroke-width="5" stroke-dasharray="2.4 6.4"/>' +
    '<circle cx="120" cy="120" r="104" fill="url(#tcF)"/>' +
    '<circle cx="120" cy="120" r="86" fill="none" stroke="rgba(0,0,0,.34)" stroke-width="5"/><circle cx="120" cy="120" r="80.5" fill="none" stroke="rgba(255,255,255,.24)" stroke-width="2"/>' +
    '<path d="M40 92 A88 88 0 0 1 112 32" stroke="rgba(255,255,255,.5)" stroke-width="7" fill="none" stroke-linecap="round"/><path d="M52 150 A80 80 0 0 0 96 200" stroke="rgba(0,0,0,.22)" stroke-width="6" fill="none" stroke-linecap="round"/>' +
    tapEmblem() + sweep + '</svg></div>';
}
function tapObject() {
  if (S.tap.object === 'hamster') return '<div class="tapcoin hamsterobj" id="tapCoin">' + hamsterPreview(S.skin ? S.skin.current : 'classic') + '</div>';
  return tapCoinSvg();
}
function tapLevel() {
  const lv = S.speed.level, next = lv.next;
  const span = next !== null ? Math.max(1, next - lv.from) : 1;
  const pct = next === null ? 100 : Math.min(100, Math.max(0, (lv.refs - lv.from) / span * 100));
  return '<div class="tlevel" data-tgo="refer">' + medal(lv.level, 30) + '<div class="tl-t"><b>' + esc(lv.name) + '</b><span>' + t('level') + ' ' + lv.level + '/' + lv.max + '</span></div>' +
    '<div class="lg-bar"><i style="width:' + pct.toFixed(0) + '%"></i></div><em>' + (next === null ? t('max_level') : lv.refs + '/' + next) + '</em></div>';
}
function tapTopbar() {
  const u = S.user, name = u.first_name || u.username || 'Miner';
  $('#topbar').innerHTML =
    '<div class="avatar" data-tgo="tap"><span>' + esc(name.charAt(0).toUpperCase()) + '</span></div>' +
    '<div class="who"><b>' + esc(name) + '</b><span class="sub">ID: ' + esc(u.telegram_id) + '</span></div>' +
    '<button class="walletpill" data-tgo="twallet"><span>' + t('usd_wallet') + '</span><b>$' + nf(u.usd) + '</b></button>' +
    (tapOnly() ? '' : '<button class="chip" data-act="tap-exit" aria-label="back">' + ic('home') + '</button>');
}
function tapNav() {
  const items = [['refer', 'users', 'tn_refer'], ['task', 'tasks', 'tn_task'], ['tap', 'coin', 'tn_tap'], ['twallet', 'wallet', 'tn_wallet']];
  $('#nav').innerHTML = items.map(k => '<button data-tgo="' + k[0] + '" class="' + (tview === k[0] ? 'on' : '') + '">' +
    (k[1] === 'coin' ? coin(26) : ic(k[1])) + '<span>' + esc(t(k[2])) + '</span></button>').join('');
}
function tapDailyDot() { return S.daily.enabled && !S.daily.claimed ? '<i class="tdot"></i>' : ''; }
function vTapMain() {
  const tp = S.tap, bal = tapBalance(), energy = tapEnergyNow();
  let stars = '';
  for (let i = 1; i <= 8; i++) stars += '<i class="tstar s' + i + '"></i>';
  return tapLevel() +
    '<div class="tbal"><b id="tapBal">' + nf(bal, tapDec()) + '</b><em>' + esc(tp.token) + '</em></div>' +
    '<div class="tusd" id="tapUsd">≈ $' + nf(bal / (S.cfg.coins_per_usd || 1), 2) + ' USD</div>' +
    '<div class="tstage">' + stars + '<button class="tdaily" data-act="tap-daily">' + ic('gift') + '<span>' + t('daily_rewards') + '</span>' + tapDailyDot() + '</button>' +
    (tp.object === 'hamster' ? '<button class="tskins" data-act="skins-open">' + ic('hamster') + '<span>' + t('skins') + '</span></button>' : '') +
    '<div class="tglow"></div><svg class="torbit" viewBox="0 0 300 300"><circle cx="150" cy="150" r="144" fill="none" stroke="var(--gold)" stroke-opacity=".4" stroke-width="2" stroke-dasharray="3 11"/><circle cx="150" cy="6" r="5" fill="var(--gold)"/><circle cx="150" cy="294" r="3.5" fill="var(--gold)" opacity=".7"/></svg>' +
    '<div class="tapfloat">' + tapObject() + '</div></div>' +
    '<div class="thint"><span><i class="finger">' + ic('zap') + '</i>' + t('tap_to_mine') + '</span></div>' +
    '<div class="tboxes"><div class="tbox">' + ic('zap') + '<b id="tapEn">' + Math.floor(energy) + '/' + Math.floor(tp.max) + '</b></div>' +
    '<div class="tbox">' + ic('clock') + '<b id="tapTimer" class="ready">' + t('ready') + '</b></div></div>' +
    '<div class="tbar"><i id="tapBar" style="width:' + (energy / tp.max * 100).toFixed(1) + '%"></i></div>' +
    (tp.cap_left !== null ? '<div class="tcap">' + t('tap_cap_left') + ': <b id="tapCap">' + nf(Math.max(0, tp.cap_left - tapPending * tp.per_tap), 2) + '</b></div>' : '');
}
function vTapTask() {
  return '<h1 class="th1">' + t('tasks_title') + '</h1><div id="taskList">' + (tasksCache ? tapTaskHtml(tasksCache) : skel(3)) + '</div><p class="tnote">' + t('tasks_note') + '</p>';
}
function tapTaskHtml(items) {
  if (!S.cfg.tasks_enabled || !items.length) return emptyBox('tasks', 'blue', t('no_tasks'), t('no_tasks_d'));
  return items.map(k => {
    const ti = TASKIC[k.type] || ['target', 'blue'];
    let btn;
    if (k.status === 'done') btn = '<span class="tdone">' + ic('check') + t('done') + '</span>';
    else if (k.status === 'started') btn = '<button class="tpill go" data-act="task-claim" data-id="' + k.id + '">' + t('verify') + '</button>';
    else btn = '<button class="tpill" data-act="task-start" data-id="' + k.id + '">' + t('start') + '</button>';
    return '<div class="card ttask' + (k.status === 'done' ? ' done' : '') + '"><span class="tic ' + ti[1] + '">' + ic(ti[0]) + '</span><div class="rc-t"><b>' + esc(k.title) + '</b>' +
      (k.description ? '<span class="tlink">' + esc(k.description) + '</span>' : '') + '<span class="trw">' + coin(16) + '<em>+' + nf(k.reward, 0) + '</em></span></div>' + btn + '</div>';
  }).join('');
}
function vTapRefer() {
  const u = S.user, c = S.cfg;
  let h = '<h1 class="th1 left">' + t('refer_earn') + '</h1><p class="tsub">' + t('invite_more') + '</p>' +
    '<div class="card tglass"><span class="tlab">' + t('per_invite') + '</span><div class="tbig">' + nf(c.ref_coin_bonus, 0) + ' <em>' + esc(S.tap.token) + '</em></div></div>' +
    '<div class="card tglass"><span class="tlab">' + t('your_ref_link') + '</span><div class="linkbox"><span>' + esc(u.ref_link || '—') + '</span><button data-act="copy-ref" aria-label="copy">' + ic('copy') + '</button></div>' +
    '<div class="tbtns"><button class="tbtn dark" data-act="copy-ref">' + ic('copy') + t('copy_link2') + '</button><button class="tbtn" data-act="invite">' + ic('send') + t('share') + '</button></div></div>';
  const d = refData || {total: 0, active: 0, earnings: 0};
  h += '<div class="card tglass tstats"><div><span class="tsi blue">' + ic('user') + '</span><b>' + d.total + '</b><small>' + t('total_referrals') + '</small></div>' +
    '<div><span class="tsi green">' + ic('check') + '</span><b>' + d.active + '</b><small>' + t('active_referrals') + '</small></div>' +
    '<div><span class="tsi gold">' + coin(24) + '</span><b>' + nf(d.earnings, 0) + '</b><small>' + t('total_earned') + '</small></div></div>';
  return h;
}
function histRowHtml(x) {
  const usd = x.currency === 'USD', amt = Number(x.amount), sign = amt >= 0 ? '+' : '-';
  const val = usd ? sign + '$' + nf(Math.abs(amt)) : sign + nf(Math.abs(amt), amt % 1 ? 2 : 0);
  const ti = TXIC[x.type] || ['wallet', 'gold'];
  return '<div class="li">' + tile(ti[0], ti[1]) + '<div class="rc-t"><b>' + esc((x.type || '').replace('_', ' ')) + (x.note ? ' · ' + esc(x.note) : '') + '</b><span>' + fdate(x.created_at) + '</span></div>' +
    '<div class="ra"><b class="' + (amt >= 0 ? 'pos' : 'neg') + '">' + val + '</b></div></div>';
}
async function tapHistoryModal() {
  openModal('<div class="ctitle">' + t('history') + '</div>' + skel(3));
  try {
    const r = await api('history');
    const rows = r.items.map(histRowHtml).join('');
    openModal('<div class="ctitle">' + t('history') + '</div><div class="card list" style="margin-top:8px">' + (rows || emptyBox('history', 'teal', t('no_history'), t('no_history_d'))) + '</div><button class="btn" data-act="close">' + t('close') + '</button>');
  } catch (e) { toast(e.message, 'err'); closeModal(); }
}
function vTapWallet() {
  const u = S.user, c = S.cfg, bal = tapBalance();
  let h = '<h1 class="th1">' + t('wallet_title') + '</h1>' +
    '<div class="card tglass tcenter"><span class="tlab">' + t('total_balance') + '</span><div class="tbig2">' + nf(bal, tapDec()) + ' <em>' + esc(S.tap.token) + '</em></div><div class="tusd">≈ $' + nf(bal / (c.coins_per_usd || 1), 2) + ' USD</div></div>' +
    '<div class="tbtns one"><button class="tbtn" data-act="withdraw-open">' + ic('up') + t('withdraw') + ' · $' + nf(u.usd) + '</button></div>';
  if (c.conv_enabled) {
    h += '<div class="card tglass"><span class="tlab">' + t('convert_title') + '</span><div class="trate">' + nf(c.coins_per_usd, 0) + ' ' + t('coins') + ' = $1.00 · ' + t('min_label') + ' ' + nf(c.conv_min, 0) + '</div>' +
      '<input id="cvIn" class="tinput" type="number" inputmode="numeric" min="0" placeholder="0">' +
      '<div class="pcts"><button data-act="cv-pct" data-pct="25">25%</button><button data-act="cv-pct" data-pct="50">50%</button><button data-act="cv-pct" data-pct="75">75%</button><button data-act="cv-pct" data-pct="100">MAX</button></div>' +
      '<div class="trow2"><span>' + t('you_get') + '</span><b id="cvOut">$0.0000</b></div>' +
      '<button class="tbtn dark" data-act="cv-go">' + ic('swap') + t('convert') + '</button></div>';
  }
  h += '<div class="tbtns one"><button class="tbtn dark" data-act="tap-history">' + ic('history') + t('history') + '</button></div><div class="tsec">' + t('your_wallets') + '</div>';
  (c.wd_methods || []).forEach(m => {
    h += '<div class="card twrow"><span class="tw">' + ic('wallet') + '</span><b>' + esc(m) + '</b><button class="tlnk" data-act="withdraw-open" data-method="' + esc(m) + '">' + t('use') + ' →</button></div>';
  });
  return h;
}
function renderTap(keepScroll) {
  tapTopbar(); tapNav();
  const y = window.scrollY;
  const V = {tap: vTapMain, task: vTapTask, refer: vTapRefer, twallet: vTapWallet};
  $('#view').innerHTML = '<div class="tapview tv-' + tview + '">' + (V[tview] || vTapMain)() + '</div>';
  if (keepScroll) window.scrollTo(0, y);
  tickTap(); flushPending();
}
function tickTap() {
  if (!S || !S.tap) return;
  const tp = S.tap, bal = tapBalance(), energy = tapEnergyNow();
  const a = $('#tapBal'); if (a) a.textContent = nf(bal, tapDec());
  const b = $('#tapUsd'); if (b) b.textContent = '≈ $' + nf(bal / (S.cfg.coins_per_usd || 1), 2) + ' USD';
  const c = $('#tapEn'); if (c) c.textContent = Math.floor(energy) + '/' + Math.floor(tp.max);
  const d = $('#tapBar'); if (d) d.style.width = (energy / tp.max * 100).toFixed(1) + '%';
  const f = $('#tapTimer'); if (f) { const left = (tp.max - energy) / tp.regen; f.textContent = left > 1 ? dur(left) : t('ready'); f.className = left > 1 ? '' : 'ready'; }
  const g = $('#tapCap'); if (g && tp.cap_left !== null) g.textContent = nf(Math.max(0, tp.cap_left - tapPending * tp.per_tap), 2);
}
function tapSchedule() { if (!tapFlushTimer) tapFlushTimer = setTimeout(tapFlush, 900); }
async function tapFlush() {
  clearTimeout(tapFlushTimer); tapFlushTimer = null;
  if (tapSending || tapPending < 1) return;
  const n = tapPending; tapSending = true;
  try { await api('tap', {n}); }
  catch (e) { toast(e.message, 'err'); }
  tapPending = Math.max(0, tapPending - n);
  tapSending = false;
  if (tapPending > 0) tapSchedule();
  tickTap(); flushPending();
}
function tapHit(e, coinEl) {
  const tp = S.tap;
  if (tapEnergyNow() < 1) { if (Date.now() - tapLastMsg > 1500) { toast(t('no_energy'), 'err'); tapLastMsg = Date.now(); } return; }
  if (tp.cap_left !== null && (tapPending + 1) * tp.per_tap > tp.cap_left) { if (Date.now() - tapLastMsg > 1500) { toast(t('cap_reached'), 'err'); tapLastMsg = Date.now(); } return; }
  tapPending++;
  const r = coinEl.getBoundingClientRect(), stage = coinEl.closest('.tstage'), sr = stage.getBoundingClientRect();
  const dx = (e.clientX - (r.left + r.width / 2)) / (r.width / 2), dy = (e.clientY - (r.top + r.height / 2)) / (r.height / 2);
  if (!reduce) {
    coinEl.style.transform = 'perspective(520px) rotateX(' + (-dy * 14).toFixed(1) + 'deg) rotateY(' + (dx * 14).toFixed(1) + 'deg) scale(.94)';
    setTimeout(() => { coinEl.style.transform = ''; }, 100);
    if (stage.childElementCount < 70) {
      const px = e.clientX - sr.left, py = e.clientY - sr.top;
      const add = (cls, html, life, style) => { const n = document.createElement('span'); n.className = cls; if (html) n.textContent = html; n.style.left = px + 'px'; n.style.top = py + 'px'; if (style) n.style.cssText += style; stage.appendChild(n); setTimeout(() => n.remove(), life); };
      add('tapnum', '+' + nf(tp.per_tap, tp.per_tap < 1 ? 2 : 0), 950, '--drift:' + ((Math.random() - .5) * 60).toFixed(0) + 'px');
      add('tapripple', '', 650);
      for (let i = 0; i < 5; i++) {
        const a = Math.random() * Math.PI * 2, d = 38 + Math.random() * 40;
        add('tapspark', '', 600, '--dx:' + (Math.cos(a) * d).toFixed(0) + 'px;--dy:' + (Math.sin(a) * d).toFixed(0) + 'px');
      }
    }
    const bal = $('#tapBal'); if (bal) { bal.classList.remove('pop'); void bal.offsetWidth; bal.classList.add('pop'); }
  }
  if (Date.now() - tapLastHaptic > 70) { tapLastHaptic = Date.now(); try { tg && tg.HapticFeedback && tg.HapticFeedback.impactOccurred('light'); } catch (er) {} }
  tickTap();
  if (tapPending >= 30) tapFlush(); else tapSchedule();
}
function tapDailyModal() {
  const d = S.daily;
  if (!d.enabled) return;
  const total = Math.min(7, d.rewards.length), cur = d.claimed ? d.streak : d.streak;
  const cards = d.rewards.slice(0, 7).map((r, i) => {
    const n = i + 1, done = d.claimed ? n <= d.day : n < d.day, now = !d.claimed && n === d.day;
    return '<div class="dcard ' + (done ? 'done' : (now ? 'now' : 'lock')) + '"><span>' + t('day') + ' ' + n + '</span>' + coin(30) + '<b>+' + nf(r, 0) + '</b><small>' + esc(S.tap.token) + '</small>' + (done ? '<i class="dk">' + ic('check') + '</i>' : (now ? '' : '<i class="dk">' + ic('lock') + '</i>')) + '</div>';
  }).join('');
  openModal('<div class="dhead"><div><div class="ctitle" style="margin:0">' + ic('gift') + ' ' + t('daily_rewards') + '</div><div class="note" style="margin:2px 0 0">' + t('keep_streak') + '</div></div><button class="xbtn" data-act="close">' + ic('x') + '</button></div>' +
    '<p class="dp">' + t('daily_prompt') + '</p><div class="dstreak"><span>' + t('streak_progress') + '</span><b>' + Math.min(cur, total) + ' / ' + total + ' ' + t('days_word') + '</b></div>' +
    '<div class="dbar"><i style="width:' + (Math.min(cur, total) / total * 100).toFixed(0) + '%"></i></div><div class="dgrid">' + cards + '</div>' +
    '<button class="tbtn big" data-act="daily" ' + (d.claimed ? 'disabled' : '') + '>' + (d.claimed ? t('claimed') : t('claim_day').replace('{n}', d.day)) + '</button>');
}
document.addEventListener('pointerdown', e => {
  if (shell !== 'tap') return;
  const c = e.target.closest('#tapCoin');
  if (!c) return;
  e.preventDefault();
  tapHit(e, c);
}, {passive: false});

/* ---------- Boot ---------- */
async function boot() {
  loadPrefs();
  if (tg) {
    try {
      tg.ready(); tg.expand();
      tg.setHeaderColor && tg.setHeaderColor('#090b0f');
      tg.setBackgroundColor && tg.setBackgroundColor('#090b0f');
      tg.disableVerticalSwipes && tg.disableVerticalSwipes();
      tg.BackButton && tg.BackButton.onClick(() => go('home'));
    } catch (e) {}
  }
  if (!tg || !tg.initData) {
    $('#view').innerHTML = '<div class="maint">' + tile('phone', 'gold') + '<h2>' + t('open_in_telegram') + '</h2></div>';
    return;
  }
  $('#view').innerHTML = skeletonHome();
  try { await api('state'); }
  catch (e) { $('#view').innerHTML = '<div class="maint">' + tile('alert', 'red') + '<h2>' + esc(e.message) + '</h2><button class="btn gold" onclick="location.reload()">Retry</button></div>'; return; }
  try {
    if (S.tap && S.tap.enabled && (S.tap.open_mode === 'only' || (S.tap.open_mode === 'tap' && !sessionStorage.getItem('miner_shell_main')))) { shell = 'tap'; applyTapVars(); }
  } catch (e) {}
  render();
  if (!lsGet('miner_welcome') && !tapOnly()) { lsSet('miner_welcome', '1'); welcomeModal(0); dailyShown = true; setTimeout(() => { dailyShown = false; }, 0); }
  else maybeDailyPrompt();
  setInterval(tick, 1000);
  setInterval(() => { if (!busy && !document.hidden) refreshState(true); }, 30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshState(true); });
}
boot();
})();
