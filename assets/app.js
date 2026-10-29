import { apiRequest } from './api.js';
import {
  formatCurrency,
  formatDate,
  formatPaymentTime,
  formatRemaining,
  hideNotice,
  parseDate,
  providerLabel,
  setButtonBusy,
  setNotice,
} from './ui.js';

const elements = {
  connectionBadge: document.querySelector('#connectionBadge'),
  connectionBadgeText: document.querySelector('#connectionBadgeText'),
  tokenForm: document.querySelector('#tokenForm'),
  apiToken: document.querySelector('#apiToken'),
  toggleToken: document.querySelector('#toggleToken'),
  tokenError: document.querySelector('#tokenError'),
  connectButton: document.querySelector('#connectButton'),
  disconnectButton: document.querySelector('#disconnectButton'),
  connectionNotice: document.querySelector('#connectionNotice'),
  accountCard: document.querySelector('#accountCard'),
  accountName: document.querySelector('#accountName'),
  accountBalance: document.querySelector('#accountBalance'),
  accountProvider: document.querySelector('#accountProvider'),
  accountFee: document.querySelector('#accountFee'),
  maskedToken: document.querySelector('#maskedToken'),
  paymentForm: document.querySelector('#paymentForm'),
  paymentFields: document.querySelector('#paymentFields'),
  amount: document.querySelector('#amount'),
  amountError: document.querySelector('#amountError'),
  provider: document.querySelector('#provider'),
  generateButton: document.querySelector('#generateButton'),
  createNotice: document.querySelector('#createNotice'),
  pollingEnabled: document.querySelector('#pollingEnabled'),
  pollingInterval: document.querySelector('#pollingInterval'),
  receiptTitle: document.querySelector('#receiptTitle'),
  receiptDescription: document.querySelector('#receiptDescription'),
  paymentStatusBadge: document.querySelector('#paymentStatusBadge'),
  paymentStatusText: document.querySelector('#paymentStatusText'),
  emptyReceipt: document.querySelector('#emptyReceipt'),
  activeReceipt: document.querySelector('#activeReceipt'),
  heroStatus: document.querySelector('#heroStatus'),
  amountLabel: document.querySelector('#amountLabel'),
  receiptAmount: document.querySelector('#receiptAmount'),
  receiptRequested: document.querySelector('#receiptRequested'),
  pendingVisual: document.querySelector('#pendingVisual'),
  qrImage: document.querySelector('#qrImage'),
  qrFallback: document.querySelector('#qrFallback'),
  qrFallbackTitle: document.querySelector('#qrFallbackTitle'),
  qrFallbackCopy: document.querySelector('#qrFallbackCopy'),
  paidVisual: document.querySelector('#paidVisual'),
  paidTime: document.querySelector('#paidTime'),
  expiredVisual: document.querySelector('#expiredVisual'),
  receiptProvider: document.querySelector('#receiptProvider'),
  receiptExpiry: document.querySelector('#receiptExpiry'),
  receiptReference: document.querySelector('#receiptReference'),
  transactionIdRow: document.querySelector('#transactionIdRow'),
  transactionId: document.querySelector('#transactionId'),
  issuerRow: document.querySelector('#issuerRow'),
  receiptIssuer: document.querySelector('#receiptIssuer'),
  pollingStateText: document.querySelector('#pollingStateText'),
  nextCheckText: document.querySelector('#nextCheckText'),
  paymentNotice: document.querySelector('#paymentNotice'),
  checkButton: document.querySelector('#checkButton'),
  resetButton: document.querySelector('#resetButton'),
  responseDetails: document.querySelector('#responseDetails'),
};

const app = {
  connected: false,
  tokenMasked: '',
  account: null,
  transaction: null,
  checking: false,
  pollTimer: null,
  nextCheckAt: 0,
  qrKey: '',
  lastEffectiveStatus: 'empty',
};

function readPreference(key, fallback) {
  try {
    const value = localStorage.getItem(key);
    return value === null ? fallback : JSON.parse(value);
  } catch {
    return fallback;
  }
}

function writePreference(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // The setting still works for this page when storage is unavailable.
  }
}

elements.pollingEnabled.checked = Boolean(readPreference('qrisLabPollingEnabled', false));
const storedInterval = Number(readPreference('qrisLabPollingInterval', 5));
if ([2, 3, 5, 10, 15, 30].includes(storedInterval)) {
  elements.pollingInterval.value = String(storedInterval);
}

function applyServerState(serverState) {
  app.connected = Boolean(serverState?.connected);
  app.tokenMasked = serverState?.token_masked || '';
  app.account = serverState?.account || null;
  app.transaction = serverState?.transaction || null;
  renderAll();
}

function effectiveStatus() {
  const transaction = app.transaction;
  if (!transaction) return 'empty';
  if (transaction.paid || transaction.status === 'success') return 'paid';
  if (transaction.status === 'expired') return 'expired';
  const expiresAt = parseDate(transaction.expires_at);
  if (expiresAt && expiresAt.getTime() <= Date.now()) return 'expired';
  return 'pending';
}

function renderAll() {
  renderConnection();
  renderAccount();
  renderReceipt();
  renderPollingControls();
}

function renderConnection() {
  elements.connectionBadge.className = app.connected ? 'status-chip status-chip--connected' : 'status-chip';
  elements.connectionBadgeText.textContent = app.connected ? 'API terhubung' : 'Belum terhubung';
  elements.disconnectButton.classList.toggle('is-hidden', !app.connected);
  elements.paymentFields.disabled = !app.connected;
  elements.generateButton.disabled = !app.connected;
  elements.pollingEnabled.disabled = !app.connected;
  elements.pollingInterval.disabled = !app.connected;
  elements.apiToken.placeholder = app.connected ? 'Tempel token lain untuk mengganti' : 'Tempel API token di sini';
}

function renderAccount() {
  elements.accountCard.classList.toggle('is-hidden', !app.connected || !app.account);
  if (!app.account) return;
  elements.accountName.textContent = app.account.first_name || app.account.username || 'Akun QRIS';
  elements.accountBalance.textContent = formatCurrency(app.account.balance);
  elements.accountProvider.textContent = providerLabel(app.account.active_provider);
  elements.accountFee.textContent = formatCurrency(app.account.qris_creation_fee);
  elements.maskedToken.textContent = app.tokenMasked || 'Tersimpan di session';
}

function setStatusBadge(status) {
  const map = {
    empty: ['status-chip', 'Belum ada QRIS'],
    pending: ['status-chip status-chip--pending', 'Menunggu pembayaran'],
    checking: ['status-chip status-chip--checking', 'Sedang memeriksa'],
    paid: ['status-chip status-chip--paid', 'Sudah dibayar'],
    expired: ['status-chip status-chip--expired', 'Kedaluwarsa'],
  };
  const value = map[status] || map.empty;
  elements.paymentStatusBadge.className = value[0];
  elements.paymentStatusText.textContent = value[1];
}

function renderReceipt() {
  const transaction = app.transaction;
  const status = effectiveStatus();
  app.lastEffectiveStatus = status;
  elements.emptyReceipt.classList.toggle('is-hidden', Boolean(transaction));
  elements.activeReceipt.classList.toggle('is-hidden', !transaction);

  if (!transaction) {
    elements.receiptTitle.textContent = 'Status pembayaran';
    elements.receiptDescription.textContent = 'QRIS yang dibuat akan tampil di sini.';
    setStatusBadge('empty');
    stopPolling();
    return;
  }

  setStatusBadge(app.checking ? 'checking' : status);
  elements.receiptAmount.textContent = formatCurrency(transaction.amount);
  const requested = formatCurrency(transaction.requested_amount);
  const uniqueCode = Number(transaction.unique_code || 0);
  elements.receiptRequested.textContent = uniqueCode > 0
    ? 'Nominal ' + requested + ' + kode unik ' + formatCurrency(uniqueCode)
    : 'Nominal diminta ' + requested;
  elements.receiptProvider.textContent = providerLabel(transaction.provider);
  elements.receiptReference.textContent = transaction.reference || 'Tersimpan di server';

  const payment = transaction.payment;
  const hasTransactionId = Boolean(payment?.transaction_id);
  elements.transactionIdRow.classList.toggle('is-hidden', !hasTransactionId);
  elements.transactionId.textContent = payment?.transaction_id || '—';
  const hasIssuer = Boolean(payment?.issuer);
  elements.issuerRow.classList.toggle('is-hidden', !hasIssuer);
  elements.receiptIssuer.textContent = payment?.issuer || '—';

  elements.pendingVisual.classList.toggle('is-hidden', status !== 'pending');
  elements.paidVisual.classList.toggle('is-hidden', status !== 'paid');
  elements.expiredVisual.classList.toggle('is-hidden', status !== 'expired');
  elements.checkButton.disabled = app.checking || status !== 'pending';

  if (status === 'pending') {
    elements.receiptTitle.textContent = 'QRIS aktif';
    elements.receiptDescription.textContent = 'Scan dengan aplikasi pembayaran yang mendukung QRIS.';
    elements.heroStatus.className = 'status-chip status-chip--pending';
    elements.heroStatus.textContent = app.checking ? 'Sedang memeriksa' : 'Menunggu pembayaran';
    ensureQrImage(transaction);
  } else if (status === 'paid') {
    elements.receiptTitle.textContent = 'Pembayaran selesai';
    elements.receiptDescription.textContent = 'Pembayaran sudah dikonfirmasi oleh provider.';
    elements.heroStatus.className = 'status-chip status-chip--paid';
    elements.heroStatus.textContent = 'Dibayar';
    elements.paidTime.textContent = formatPaymentTime(payment);
    stopPolling();
  } else {
    elements.receiptTitle.textContent = 'QRIS kedaluwarsa';
    elements.receiptDescription.textContent = 'Buat QRIS baru untuk melanjutkan pembayaran.';
    elements.heroStatus.className = 'status-chip status-chip--expired';
    elements.heroStatus.textContent = 'Kedaluwarsa';
    stopPolling();
  }

  updateClockText();
  updatePollingState();
}

function ensureQrImage(transaction) {
  const key = (transaction.reference || '') + '|' + (transaction.created_at || '');
  if (key === app.qrKey) return;
  app.qrKey = key;
  elements.qrImage.classList.add('is-hidden');
  elements.qrFallback.classList.remove('is-hidden');
  if (elements.qrFallbackTitle) elements.qrFallbackTitle.textContent = 'Memuat gambar QRIS';
  if (elements.qrFallbackCopy) elements.qrFallbackCopy.textContent = 'Gambar diamankan melalui proxy session server.';
  elements.qrImage.src = 'qr.php?v=' + encodeURIComponent(transaction.created_at || Date.now());
}

elements.qrImage.addEventListener('load', () => {
  elements.qrFallback.classList.add('is-hidden');
  elements.qrImage.classList.remove('is-hidden');
});

elements.qrImage.addEventListener('error', () => {
  elements.qrImage.classList.add('is-hidden');
  elements.qrFallback.classList.remove('is-hidden');
  if (elements.qrFallbackTitle) elements.qrFallbackTitle.textContent = 'Gambar QRIS belum termuat';
  if (elements.qrFallbackCopy) elements.qrFallbackCopy.textContent = 'Coba generate QRIS baru jika masalah berlanjut.';
});

function renderPollingControls() {
  elements.pollingEnabled.disabled = !app.connected;
  elements.pollingInterval.disabled = !app.connected;
  updatePollingState();
}

function updateClockText() {
  const transaction = app.transaction;
  if (!transaction) return;
  const expiresAt = parseDate(transaction.expires_at);
  const status = effectiveStatus();
  if (status === 'paid') {
    elements.receiptExpiry.textContent = 'Pembayaran selesai';
    elements.receiptExpiry.className = 'meta-row__value';
  } else if (!expiresAt) {
    elements.receiptExpiry.textContent = transaction.expires_at || 'Tidak tersedia';
  } else if (status === 'expired') {
    elements.receiptExpiry.textContent = 'Sudah kedaluwarsa';
    elements.receiptExpiry.className = 'meta-row__value countdown';
  } else {
    elements.receiptExpiry.textContent = formatRemaining(expiresAt.getTime() - Date.now());
    elements.receiptExpiry.className = 'meta-row__value countdown';
  }

  const currentStatus = effectiveStatus();
  if (currentStatus !== app.lastEffectiveStatus) renderReceipt();
}

function shouldPoll() {
  return app.connected && elements.pollingEnabled.checked && effectiveStatus() === 'pending';
}

function clearPollTimer() {
  if (app.pollTimer !== null) window.clearTimeout(app.pollTimer);
  app.pollTimer = null;
  app.nextCheckAt = 0;
}

function stopPolling() {
  clearPollTimer();
  updatePollingState();
}

function schedulePolling() {
  clearPollTimer();
  if (!shouldPoll() || app.checking) {
    updatePollingState();
    return;
  }
  const intervalSeconds = Number(elements.pollingInterval.value) || 5;
  app.nextCheckAt = Date.now() + intervalSeconds * 1000;
  app.pollTimer = window.setTimeout(() => {
    app.pollTimer = null;
    app.nextCheckAt = 0;
    checkPayment(false);
  }, intervalSeconds * 1000);
  updatePollingState();
}

function updatePollingState() {
  if (!app.connected) {
    elements.pollingStateText.textContent = 'Hubungkan API untuk mengatur polling';
    elements.nextCheckText.textContent = '—';
    return;
  }
  if (!elements.pollingEnabled.checked) {
    elements.pollingStateText.textContent = 'Pemeriksaan otomatis tidak aktif';
    elements.nextCheckText.textContent = 'Off';
    return;
  }
  if (!app.transaction) {
    elements.pollingStateText.textContent = 'Polling siap untuk QRIS berikutnya';
    elements.nextCheckText.textContent = 'Siap';
    return;
  }
  if (effectiveStatus() !== 'pending') {
    elements.pollingStateText.textContent = 'Polling dihentikan otomatis';
    elements.nextCheckText.textContent = 'Selesai';
    return;
  }
  if (app.checking) {
    elements.pollingStateText.textContent = 'Sedang memeriksa pembayaran';
    elements.nextCheckText.textContent = 'Sekarang';
    return;
  }
  elements.pollingStateText.textContent = 'Pemeriksaan otomatis aktif';
  if (app.nextCheckAt > 0) {
    const seconds = Math.max(0, Math.ceil((app.nextCheckAt - Date.now()) / 1000));
    elements.nextCheckText.textContent = 'Berikutnya ' + seconds + ' detik';
  } else {
    elements.nextCheckText.textContent = 'Menunggu jadwal';
  }
}

async function checkPayment(manual) {
  if (app.checking || effectiveStatus() !== 'pending') return;
  clearPollTimer();
  app.checking = true;
  if (manual) setButtonBusy(elements.checkButton, true, 'Memeriksa');
  renderReceipt();

  try {
    const result = await apiRequest('check');
    app.transaction = result.transaction || result.state?.transaction || app.transaction;
    elements.responseDetails.textContent = JSON.stringify({ message: result.message, check: result.check, transaction: result.transaction }, null, 2);
    const status = effectiveStatus();
    if (status === 'paid') {
      setNotice(elements.paymentNotice, 'success', 'Pembayaran diterima', 'Polling dihentikan otomatis dan detail transaksi sudah diperbarui.');
    } else if (status === 'expired') {
      setNotice(elements.paymentNotice, 'warning', 'QRIS kedaluwarsa', 'Generate QRIS baru untuk menerima pembayaran.');
    } else {
      setNotice(elements.paymentNotice, 'info', 'Pembayaran belum ditemukan', 'Status terakhir diperbarui pada ' + formatDate(app.transaction.checked_at) + '.');
    }
  } catch (error) {
    setNotice(elements.paymentNotice, 'error', 'Pengecekan gagal', error.message || 'Coba lagi sebentar.');
    elements.responseDetails.textContent = JSON.stringify({ success: false, message: error.message, status: error.status || 0 }, null, 2);
  } finally {
    app.checking = false;
    if (manual) setButtonBusy(elements.checkButton, false);
    renderReceipt();
    if (shouldPoll()) schedulePolling();
  }
}

elements.toggleToken.addEventListener('click', () => {
  const show = elements.apiToken.type === 'password';
  elements.apiToken.type = show ? 'text' : 'password';
  elements.toggleToken.setAttribute('aria-label', show ? 'Sembunyikan token' : 'Tampilkan token');
  elements.toggleToken.title = show ? 'Sembunyikan token' : 'Tampilkan token';
});

elements.tokenForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const token = elements.apiToken.value.trim();
  elements.tokenError.classList.add('is-hidden');
  if (token.length < 8) {
    elements.tokenError.textContent = 'Masukkan API token yang valid.';
    elements.tokenError.classList.remove('is-hidden');
    elements.apiToken.focus();
    return;
  }

  setButtonBusy(elements.connectButton, true, 'Menghubungkan');
  elements.disconnectButton.disabled = true;
  try {
    const result = await apiRequest('save_token', { token });
    applyServerState(result.state);
    elements.apiToken.value = '';
    elements.apiToken.type = 'password';
    setNotice(elements.connectionNotice, 'success', 'API terhubung', result.message);
    elements.responseDetails.textContent = JSON.stringify({ message: result.message, account: result.state?.account }, null, 2);
  } catch (error) {
    setNotice(elements.connectionNotice, 'error', 'Token tidak dapat dihubungkan', error.message || 'Periksa token lalu coba lagi.');
  } finally {
    setButtonBusy(elements.connectButton, false);
    elements.disconnectButton.disabled = false;
    renderConnection();
  }
});

elements.disconnectButton.addEventListener('click', async () => {
  elements.disconnectButton.disabled = true;
  clearPollTimer();
  try {
    const result = await apiRequest('clear_token');
    app.qrKey = '';
    applyServerState(result.state);
    setNotice(elements.connectionNotice, 'info', 'Session dibersihkan', result.message);
    elements.responseDetails.textContent = 'Belum ada respons.';
  } catch (error) {
    setNotice(elements.connectionNotice, 'error', 'Token belum terhapus', error.message || 'Coba lagi sebentar.');
  } finally {
    elements.disconnectButton.disabled = false;
  }
});

elements.paymentForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const amount = Number(elements.amount.value);
  elements.amountError.classList.add('is-hidden');
  if (!Number.isInteger(amount) || amount < 1 || amount > 100000000) {
    elements.amountError.textContent = 'Masukkan nominal bulat antara Rp1 dan Rp100.000.000.';
    elements.amountError.classList.remove('is-hidden');
    elements.amount.focus();
    return;
  }

  clearPollTimer();
  hideNotice(elements.createNotice);
  setButtonBusy(elements.generateButton, true, 'Membuat QRIS');
  elements.paymentFields.disabled = true;
  try {
    const result = await apiRequest('create', { amount, provider: elements.provider.value });
    app.transaction = result.transaction || result.state?.transaction;
    app.account = result.state?.account || app.account;
    app.qrKey = '';
    elements.responseDetails.textContent = JSON.stringify({ message: result.message, transaction: result.transaction }, null, 2);
    renderAll();
    setNotice(elements.paymentNotice, 'success', 'QRIS siap dipindai', result.message);
    if (shouldPoll()) schedulePolling();
  } catch (error) {
    setNotice(elements.createNotice, 'error', 'QRIS gagal dibuat', error.message || 'Periksa input lalu coba lagi.');
    elements.responseDetails.textContent = JSON.stringify({ success: false, message: error.message, status: error.status || 0 }, null, 2);
  } finally {
    setButtonBusy(elements.generateButton, false);
    elements.paymentFields.disabled = !app.connected;
    elements.generateButton.disabled = !app.connected;
  }
});

elements.checkButton.addEventListener('click', () => checkPayment(true));

elements.resetButton.addEventListener('click', async () => {
  elements.resetButton.disabled = true;
  clearPollTimer();
  try {
    const result = await apiRequest('reset_transaction');
    app.transaction = null;
    app.qrKey = '';
    hideNotice(elements.paymentNotice);
    elements.responseDetails.textContent = 'Belum ada respons.';
    renderReceipt();
  } catch (error) {
    setNotice(elements.paymentNotice, 'error', 'Transaksi belum direset', error.message || 'Coba lagi sebentar.');
  } finally {
    elements.resetButton.disabled = false;
  }
});

elements.pollingEnabled.addEventListener('change', () => {
  writePreference('qrisLabPollingEnabled', elements.pollingEnabled.checked);
  if (elements.pollingEnabled.checked) schedulePolling();
  else stopPolling();
});

elements.pollingInterval.addEventListener('change', () => {
  writePreference('qrisLabPollingInterval', Number(elements.pollingInterval.value));
  if (shouldPoll()) schedulePolling();
});

window.setInterval(() => {
  updateClockText();
  updatePollingState();
}, 1000);

window.addEventListener('beforeunload', clearPollTimer);

async function initialize() {
  elements.connectionBadgeText.textContent = 'Memuat session';
  try {
    const result = await apiRequest('state');
    applyServerState(result.state);
    if (shouldPoll()) schedulePolling();
  } catch (error) {
    app.connected = false;
    renderAll();
    setNotice(elements.connectionNotice, 'error', 'Session tidak dapat dimuat', error.message || 'Muat ulang halaman untuk mencoba lagi.');
  }
}

initialize();
