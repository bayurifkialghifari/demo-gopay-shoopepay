const currencyFormatter = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
  dateStyle: 'medium',
  timeStyle: 'short',
});

export function formatCurrency(value) {
  const amount = Number(value);
  return currencyFormatter.format(Number.isFinite(amount) ? amount : 0);
}

export function providerLabel(value) {
  if (value === 'gopay') return 'GoPay';
  if (value === 'shopee') return 'ShopeePay';
  return value || 'Mengikuti akun';
}

export function parseDate(value) {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value) {
  const date = parseDate(value);
  return date ? dateFormatter.format(date) : (value || '—');
}

export function formatPaymentTime(payment) {
  if (!payment) return 'Status transaksi sudah dikonfirmasi API.';
  if (Number(payment.timestamp) > 0) {
    return dateFormatter.format(new Date(Number(payment.timestamp) * 1000));
  }
  return payment.time || 'Status transaksi sudah dikonfirmasi API.';
}

export function formatRemaining(milliseconds) {
  const totalSeconds = Math.max(0, Math.ceil(milliseconds / 1000));
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  if (minutes > 0) return minutes + ' menit ' + seconds + ' detik';
  return seconds + ' detik';
}

export function setNotice(element, type, title, copy) {
  element.className = 'notice notice--' + type;
  element.querySelector('[data-notice-title]').textContent = title;
  element.querySelector('[data-notice-copy]').textContent = copy;
}

export function hideNotice(element) {
  element.classList.add('is-hidden');
}

export function setButtonBusy(button, busy, busyLabel) {
  const spinner = button.querySelector('.button__spinner');
  const label = button.querySelector('[data-button-label]');
  if (busy) {
    if (!button.dataset.defaultLabel && label) button.dataset.defaultLabel = label.textContent;
    button.disabled = true;
    if (label) label.textContent = busyLabel;
    const timer = window.setTimeout(() => spinner?.classList.remove('is-hidden'), 900);
    button.dataset.spinnerTimer = String(timer);
    return;
  }

  if (button.dataset.spinnerTimer) window.clearTimeout(Number(button.dataset.spinnerTimer));
  delete button.dataset.spinnerTimer;
  spinner?.classList.add('is-hidden');
  if (label && button.dataset.defaultLabel) label.textContent = button.dataset.defaultLabel;
}
