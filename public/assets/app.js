'use strict';
const $ = (selector, root = document) => root.querySelector(selector);
const page = document.body.dataset.page;
const money = value => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value));
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const dateWib = () => new Intl.DateTimeFormat('en-CA', {timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
const dateLabel = value => new Intl.DateTimeFormat('id-ID', {dateStyle:'medium', timeZone:'Asia/Jakarta'}).format(new Date(value.replace(' ', 'T') + '+07:00'));
const timeLabel = value => String(value).slice(11,16);
const token = () => sessionStorage.getItem('skybook_token');
const user = () => { try { return JSON.parse(sessionStorage.getItem('skybook_user') || 'null'); } catch { return null; } };
function storeAuth(data) { if (data.token) sessionStorage.setItem('skybook_token', data.token); if (data.user) sessionStorage.setItem('skybook_user', JSON.stringify(data.user)); }
function clearAuth() { sessionStorage.removeItem('skybook_token'); sessionStorage.removeItem('skybook_user'); }
async function api(path, options = {}) {
  const response = await fetch('/api/' + path, {...options, headers: {'Content-Type':'application/json', ...(token() ? {Authorization:'Bearer ' + token()} : {}), ...options.headers}});
  const data = await response.json();
  if (!response.ok) {
    if (response.status === 401 && !path.startsWith('auth/')) {
      clearAuth(); location.href = '/login?next=' + encodeURIComponent(location.pathname + location.search);
    }
    throw new Error(data.errors ? Object.values(data.errors).join(' ') : (data.message || 'Terjadi kesalahan. Coba lagi.'));
  }
  return data;
}
function message(form, value, success = false) { const box = $('.form-message', form); if (box) {box.textContent = value; box.classList.toggle('success', success);} }
function toast(value) { const box = $('#toast'); box.textContent = value; box.hidden = false; setTimeout(() => box.hidden = true, 4000); }
function bindForm(selector, action) {
  const form = $(selector); if (!form) return;
  form.addEventListener('submit', async event => {
    event.preventDefault(); const button = $('button[type="submit"],button:not([type])', form); const label = button.textContent;
    button.disabled = true; button.textContent = 'Mohon tunggu…'; message(form, '');
    try { await action(Object.fromEntries(new FormData(form)), form); } catch (error) { message(form, error.message); }
    finally { button.disabled = false; button.textContent = label; }
  });
}
function safeNext() { const value = new URLSearchParams(location.search).get('next'); return value && value.startsWith('/') && !value.startsWith('//') && !value.includes('\\') ? value : '/flights'; }
function route(ticket) { return `<div class="flight-route"><div class="airport"><strong>${escapeHtml(ticket.origin)}</strong><small>${escapeHtml(timeLabel(ticket.departure_at))} WIB</small></div><div class="flight-line"><span>✈</span><small>2 jam · langsung</small></div><div class="airport"><strong>${escapeHtml(ticket.destination)}</strong><small>${escapeHtml(timeLabel(ticket.arrival_at))} WIB</small></div></div>`; }
function empty(title, description, link = false) { return `<div class="empty-state"><div class="empty-icon">↗</div><h3>${escapeHtml(title)}</h3><p>${escapeHtml(description)}</p>${link ? '<a class="button" href="/flights">Cari penerbangan ↗</a>' : ''}</div>`; }
function requireAuth() { if (!token()) {location.replace('/login?next=' + encodeURIComponent(location.pathname + location.search)); return false;} return true; }
const toggle = $('.menu-toggle'); toggle.addEventListener('click', () => { const open = $('.nav-wrap').classList.toggle('open'); toggle.setAttribute('aria-expanded', String(open)); });
if (token() && user()) $('#auth-nav').innerHTML = `<a class="button ghost small" href="/account">${escapeHtml(user().name.split(' ')[0])} ↗</a>`;

if (page === 'home') {
  $('#home-date').value = dateWib(); $('#home-date').min = dateWib();
  $('#home-search').addEventListener('submit', event => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); const params = new URLSearchParams({date:data.date}); if (data.time) params.set('time',data.time); if (data.route) {const [origin,destination] = data.route.split(','); params.set('origin',origin); params.set('destination',destination);} location.href = '/flights?' + params; });
}
if (['login','register','forgot','reset'].includes(page)) {
  bindForm('#auth-form', async (data, form) => {
    const path = {login:'login',register:'register',forgot:'forgot-password',reset:'reset-password'}[page];
    if (page === 'reset') data.token = new URLSearchParams(location.search).get('token') || '';
    const result = await api('auth/' + path, {method:'POST',body:JSON.stringify(data)});
    if (result.token) {storeAuth(result); location.href = safeNext();}
    else {message(form, result.message, true); if (result.demo_reset_url) {const box = $('#demo-reset'); box.replaceChildren(); const p = document.createElement('p'); p.textContent = 'Mode demo lokal: buka tautan berikut (berlaku 30 menit).'; const a = document.createElement('a'); a.href = result.demo_reset_url; a.textContent = 'Atur password baru ↗'; box.append(p,a);} if (page === 'reset') setTimeout(() => location.href = '/login', 1500);}
  });
}
if (page === 'flights') {
  const form = $('#flight-filters'); const params = new URLSearchParams(location.search); let currentPage = 1;
  form.elements.date.value = params.get('date') || dateWib(); form.elements.date.min = dateWib();
  for (const key of ['time','origin','destination']) if (params.has(key)) form.elements[key].value = params.get(key);
  async function load() {
    $('#ticket-list').innerHTML = '<div class="loader">Mencari kursi untuk perjalanan Anda…</div>'; $('#pagination').replaceChildren();
    const query = new URLSearchParams(); for (const [key,value] of new FormData(form)) if (value) query.set(key,value); query.set('page',currentPage); query.set('per_page',6);
    history.replaceState(null,'','/flights?' + query);
    try {
      const result = await api('tickets?' + query); $('#result-count').textContent = result.pagination.total + ' kursi tersedia';
      $('#ticket-list').innerHTML = result.data.length ? result.data.map(ticket => `<article class="ticket-card"><div class="ticket-top"><div class="airline"><span class="airline-logo">✈</span><div><strong>${escapeHtml(ticket.airline)}</strong><small>${escapeHtml(ticket.flight_number)} · ${escapeHtml(dateLabel(ticket.departure_at))}</small></div></div><span class="badge">KURSI ${escapeHtml(ticket.seat_number)}</span></div><div class="ticket-main">${route(ticket)}<div class="ticket-price"><strong>${money(ticket.price)}</strong><small>per penumpang · tanpa biaya tambahan</small></div><a class="button" href="/checkout/${Number(ticket.id)}">Pilih kursi ↗</a></div></article>`).join('') : empty('Belum ada kursi untuk pilihan ini','Coba tanggal, jam, atau maskapai lain.');
      const {page: p,total_pages: total} = result.pagination;
      if (total > 1) { $('#pagination').innerHTML = `<button class="button ghost small" id="prev-page" ${p <= 1 ? 'disabled':''}>← Sebelumnya</button><span>${p} / ${total}</span><button class="button ghost small" id="next-page" ${p >= total ? 'disabled':''}>Berikutnya →</button>`; $('#prev-page').onclick = () => {currentPage--;load();}; $('#next-page').onclick = () => {currentPage++;load();}; }
    } catch (error) {$('#result-count').textContent = 'Pencarian belum berhasil'; $('#ticket-list').innerHTML = empty('Coba lagi',error.message);}
  }
  api('airlines').then(result => { for (const airline of result.data) {const option = new Option(airline,airline); $('#airlines').add(option);} if (params.has('airline')) form.elements.airline.value = params.get('airline'); load(); }).catch(error => {toast(error.message);load();});
  form.onsubmit = event => {event.preventDefault();currentPage=1;load();};
  $('#clear-filters').onclick = () => {form.reset();form.elements.date.value = dateWib();currentPage=1;load();};
}

async function showPayment(container, booking, methods) {
  container.innerHTML = `<div class="payment-total"><span>Total pembayaran</span><strong>${money(booking.amount)}</strong></div><p>Referensi <strong>${escapeHtml(booking.reference)}</strong><br>Berlaku sampai ${escapeHtml(timeLabel(booking.expires_at))} WIB.</p><form id="pay-form"><label>Metode pembayaran<select name="payment_method">${methods.map(method => `<option value="${escapeHtml(method.id)}">${escapeHtml(method.name)}</option>`).join('')}</select></label><div class="form-message" role="status"></div><button class="button full">Buat kode pembayaran ↗</button></form><div id="qr-result"></div>`;
  bindForm('#pay-form', async data => {
    const payment = await api('pay',{method:'POST',body:JSON.stringify({...data,booking_id:Number(booking.id)})});
    const response = await fetch(payment.barcode_url,{headers:{Authorization:'Bearer ' + token()}});
    if (!response.ok) throw new Error('Kode pembayaran kedaluwarsa. Silakan periksa perjalanan Anda.');
    const blobUrl = URL.createObjectURL(await response.blob());
    $('#qr-result').innerHTML = `<div class="qr-wrap"><img src="${blobUrl}" alt="QR pembayaran untuk ${escapeHtml(booking.reference)}"><strong>Scan untuk konfirmasi pembayaran</strong><p>Atau buka tautan di bawah untuk mencoba proses scan.</p><a class="button full" id="simulate-scan" href="${escapeHtml(payment.payment_url)}" target="_blank" rel="noopener noreferrer">Simulasikan scan QR ↗</a><p class="muted">Pembayaran dummy · tidak ada uang yang ditransfer.</p><p id="payment-status" role="status">Menunggu pembayaran…</p></div>`;
    let attempts = 0;
    const poll = setInterval(async () => {if (++attempts > 225) {clearInterval(poll); return;} try {const result = await api('book/' + booking.id); if (result.booking.status !== 'pending') {clearInterval(poll); URL.revokeObjectURL(blobUrl); $('#qr-result').innerHTML = `<div class="empty-state"><div class="empty-icon">${result.booking.status === 'paid' ? '✓' : '◌'}</div><h3>${result.booking.status === 'paid' ? 'Pembayaran berhasil!' : 'Reservasi kedaluwarsa'}</h3><a class="button" href="/bookings">Lihat perjalanan saya ↗</a></div>`; $('#pay-form').hidden = true;}} catch {clearInterval(poll);}},4000);
    window.addEventListener('pagehide', () => {clearInterval(poll);URL.revokeObjectURL(blobUrl);},{once:true});
  });
}
if (page === 'checkout' && requireAuth()) {
  let selected;
  api('tickets/' + document.body.dataset.ticket).then(result => {
    selected = result.ticket;
    $('#ticket-detail').innerHTML = `<div class="step-label">PERJALANAN PILIHAN ANDA</div><h2>${escapeHtml(selected.airline)}</h2><p>${escapeHtml(selected.flight_number)} · ${escapeHtml(dateLabel(selected.departure_at))}</p>${route(selected)}<div class="detail-meta"><span>Kursi <strong>${escapeHtml(selected.seat_number)}</strong> · Ekonomi</span><strong>${money(selected.price)}</strong></div>`;
    if (!selected.available) {$('#passenger-card').innerHTML = empty('Kursi sudah tidak tersedia','Silakan pilih kursi lain.',true); return;}
    $('#booking-form').elements.passenger_name.value = user()?.name || '';
    bindForm('#booking-form', async data => {const result = await api('book',{method:'POST',body:JSON.stringify({ticket_id:Number(selected.id),passenger_name:data.passenger_name})}); $('#passenger-card').innerHTML = `<div class="step-label">KURSI BERHASIL DIPESAN</div><h2>${escapeHtml(result.booking.passenger_name)}</h2><p>Reservasi ${escapeHtml(result.booking.reference)} sudah dibuat. Lanjutkan pembayaran sebelum batas waktu.</p>`; await showPayment($('#payment-section'),result.booking,result.payment_methods);toast('Kursi disimpan. Lanjutkan pembayaran.');});
  }).catch(error => {$('#ticket-detail').innerHTML = empty('Tiket tidak tersedia',error.message,true);$('#passenger-card').hidden=true;});
}
if (page === 'bookings' && requireAuth()) {
  const labels = {pending:'Menunggu pembayaran',paid:'Terkonfirmasi',expired:'Kedaluwarsa',cancelled:'Dibatalkan'};
  api('book').then(result => {
    $('#booking-list').innerHTML = result.data.length ? result.data.map(booking => `<article class="card"><div class="booking-header"><strong>${escapeHtml(booking.reference)}</strong><span class="badge ${escapeHtml(booking.status)}">${labels[booking.status] || escapeHtml(booking.status)}</span></div><p>${escapeHtml(booking.airline)} · ${escapeHtml(dateLabel(booking.departure_at))}</p>${route(booking)}<div class="detail-meta"><span>${escapeHtml(booking.passenger_name)} · Kursi ${escapeHtml(booking.seat_number)}</span><strong>${money(booking.amount)}</strong></div>${booking.status === 'pending' ? `<button class="button full resume-pay" data-id="${Number(booking.id)}">Lanjutkan pembayaran ↗</button>` : ''}</article>`).join('') : empty('Cerita perjalanan Anda dimulai di sini','Temukan penerbangan dan rencanakan petualangan berikutnya.',true);
    document.querySelectorAll('.resume-pay').forEach(button => button.onclick = async () => {button.disabled=true; try {const result=await api('book/'+button.dataset.id); const box=$('#resume-payment');box.hidden=false;await showPayment(box,result.booking,result.payment_methods);box.scrollIntoView({behavior:'smooth',block:'center'});} catch(error) {toast(error.message);} finally {button.disabled=false;}});
  }).catch(error => $('#booking-list').innerHTML = empty('Belum dapat memuat perjalanan',error.message));
}
if (page === 'account' && requireAuth()) {
  api('users/me').then(result => {storeAuth(result); $('#account-form').elements.name.value=result.user.name;$('#account-form').elements.email.value=result.user.email;}).catch(error => toast(error.message));
  bindForm('#account-form',async (data,form) => {const result=await api('users/me',{method:'PATCH',body:JSON.stringify(data)});storeAuth(result);form.elements.password.value='';form.elements.current_password.value='';message(form,'Informasi akun berhasil diperbarui.',true);});
  $('#logout').onclick=async () => {try {await api('auth/logout',{method:'POST',body:'{}'});} catch(error) {toast(error.message);return;}clearAuth();location.href='/';};
  bindForm('#delete-form',async data => {if (!confirm('Hapus akun secara permanen? Reservasi yang belum dibayar akan dibatalkan.')) return;await api('users/me',{method:'DELETE',body:JSON.stringify(data)});clearAuth();location.href='/';});
}
if (page === 'payment') {
  api('payments/' + encodeURIComponent(document.body.dataset.payment) + '/confirm',{method:'POST',body:'{}'}).then(result => {$('#confirmation-icon').textContent='✓';$('#confirmation-title').textContent='Pembayaran berhasil!';$('#confirmation-message').textContent='Pesanan ' + result.reference + ' sudah dikonfirmasi. Sampai jumpa di perjalanan berikutnya.';}).catch(error => {$('#confirmation-icon').textContent='!';$('#confirmation-title').textContent='Pembayaran belum berhasil';$('#confirmation-message').textContent=error.message;});
}
