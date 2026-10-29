<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
boot_session();

header("Content-Security-Policy: default-src 'self'; connect-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="Demo sederhana untuk membuat QRIS dinamis dan memeriksa status pembayaran.">
  <title>QRIS Lab — Demo pembayaran dinamis</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <header class="site-header">
    <div class="site-header__inner">
      <a class="brand" href="index.php" aria-label="QRIS Lab — halaman utama">
        <span class="brand__mark" aria-hidden="true">
          <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM15 14h2v2h-2zM19 14h1v3h-3v3h-3v-2h2v-2h3z"/></svg>
        </span>
        QRIS Lab
      </a>
      <span class="status-chip" id="connectionBadge" aria-live="polite">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M8.5 12h7"/></svg>
        <span id="connectionBadgeText">Belum terhubung</span>
      </span>
    </div>
  </header>

  <main class="page-shell">
    <header class="intro">
      <p class="eyebrow">Dynamic QRIS demo</p>
      <h1>Buat QRIS, lalu pantau pembayarannya.</h1>
      <p class="intro__copy">Hubungkan API token Karuhun Developer, tentukan nominal dan provider, lalu cek pembayaran secara manual atau otomatis pada interval yang kamu pilih.</p>
    </header>

    <div class="app-grid">
      <section class="panel" aria-labelledby="configurationTitle">
        <div class="panel__header">
          <h2 class="panel__title" id="configurationTitle">Konfigurasi pembayaran</h2>
          <p class="panel__description">Token dikirim sekali ke server dan disimpan hanya di session PHP.</p>
        </div>
        <div class="panel__body">
          <section class="section-stack" aria-labelledby="tokenSectionTitle">
            <div>
              <h3 class="section-heading" id="tokenSectionTitle">1. Hubungkan API</h3>
              <p class="section-copy">Nilai token tidak pernah dikirim kembali ke JavaScript setelah tersimpan.</p>
            </div>

            <form class="section-stack" id="tokenForm" novalidate>
              <div class="field">
                <label class="field__label" for="apiToken">API token</label>
                <div class="input-shell">
                  <input class="input input--with-action" id="apiToken" name="token" type="password" autocomplete="new-password" spellcheck="false" placeholder="Tempel API token di sini" aria-describedby="tokenHint tokenError">
                  <button class="input-action" id="toggleToken" type="button" aria-label="Tampilkan token" title="Tampilkan token">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                  </button>
                </div>
                <p class="field__hint" id="tokenHint">Password field ini masked secara default.</p>
                <p class="field__message field__message--error is-hidden" id="tokenError"></p>
              </div>
              <div class="button-cluster">
                <button class="button button--primary" id="connectButton" type="submit">
                  <span class="button__spinner is-hidden" aria-hidden="true"></span>
                  <span data-button-label>Hubungkan token</span>
                </button>
                <button class="button button--danger is-hidden" id="disconnectButton" type="button">Hapus dari session</button>
              </div>
            </form>

            <div class="notice is-hidden" id="connectionNotice" role="status" aria-live="polite">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
              <div class="notice__content"><p class="notice__title" data-notice-title></p><p class="notice__copy" data-notice-copy></p></div>
            </div>

            <div class="account-card is-hidden" id="accountCard" aria-label="Ringkasan akun API">
              <div class="metric"><span class="metric__label">Akun</span><span class="metric__value" id="accountName">—</span></div>
              <div class="metric"><span class="metric__label">Saldo</span><span class="metric__value" id="accountBalance">—</span></div>
              <div class="metric"><span class="metric__label">Provider aktif</span><span class="metric__value" id="accountProvider">—</span></div>
              <div class="metric"><span class="metric__label">Biaya pembuatan</span><span class="metric__value" id="accountFee">—</span></div>
              <div class="metric metric--wide"><span class="metric__label">Token session</span><span class="metric__value mono" id="maskedToken">—</span></div>
            </div>
          </section>

          <hr class="divider">

          <section class="section-stack" aria-labelledby="paymentSectionTitle">
            <div>
              <h3 class="section-heading" id="paymentSectionTitle">2. Buat QRIS</h3>
              <p class="section-copy">Provider boleh mengikuti pengaturan aktif akun atau dipilih secara eksplisit.</p>
            </div>
            <form class="section-stack" id="paymentForm" novalidate>
              <fieldset class="form-fieldset" id="paymentFields" disabled>
                <div class="field-grid">
                  <div class="field">
                    <label class="field__label" for="amount">Nominal pembayaran</label>
                    <input class="input" id="amount" name="amount" type="number" min="1" max="100000000" step="1" inputmode="numeric" value="25000" aria-describedby="amountHint amountError">
                    <p class="field__hint" id="amountHint">Bilangan bulat dalam Rupiah.</p>
                    <p class="field__message field__message--error is-hidden" id="amountError"></p>
                  </div>
                  <div class="field">
                    <label class="field__label" for="provider">Provider</label>
                    <select class="select" id="provider" name="provider">
                      <option value="">Ikuti akun</option>
                      <option value="gopay">GoPay</option>
                      <option value="shopee">ShopeePay</option>
                    </select>
                    <p class="field__hint">Pilihan didukung API: GoPay dan ShopeePay.</p>
                  </div>
                </div>
              </fieldset>

              <button class="button button--primary button--block" id="generateButton" type="submit" disabled>
                <span class="button__spinner is-hidden" aria-hidden="true"></span>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span data-button-label>Generate QRIS</span>
              </button>

              <div class="notice is-hidden" id="createNotice" role="status" aria-live="polite">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                <div class="notice__content"><p class="notice__title" data-notice-title></p><p class="notice__copy" data-notice-copy></p></div>
              </div>
            </form>
          </section>

          <hr class="divider">

          <section class="section-stack" aria-labelledby="pollingSectionTitle">
            <div>
              <h3 class="section-heading" id="pollingSectionTitle">3. Pengaturan pengecekan</h3>
              <p class="section-copy">Request berikutnya baru dijadwalkan setelah request sebelumnya selesai.</p>
            </div>
            <label class="switch-row">
              <span class="switch-row__copy">
                <span class="switch-row__label">Cek pembayaran otomatis</span>
                <span class="switch-row__hint">Saat aktif, transaksi pending diperiksa sesuai interval.</span>
              </span>
              <span class="switch">
                <input class="switch__input" id="pollingEnabled" type="checkbox" disabled>
                <span class="switch__track" aria-hidden="true"><span class="switch__thumb"><svg class="icon" viewBox="0 0 24 24"><path d="m6 12 4 4 8-9"/></svg></span></span>
              </span>
            </label>
            <div class="field">
              <label class="field__label" for="pollingInterval">Interval pengecekan</label>
              <select class="select" id="pollingInterval" disabled>
                <option value="2">Setiap 2 detik</option>
                <option value="3">Setiap 3 detik</option>
                <option value="5" selected>Setiap 5 detik</option>
                <option value="10">Setiap 10 detik</option>
                <option value="15">Setiap 15 detik</option>
                <option value="30">Setiap 30 detik</option>
              </select>
            </div>
          </section>
        </div>
      </section>

      <section class="panel panel--receipt" aria-labelledby="receiptTitle">
        <div class="panel__header">
          <div class="panel__header-row">
            <div>
              <h2 class="panel__title" id="receiptTitle">Status pembayaran</h2>
              <p class="panel__description" id="receiptDescription">QRIS yang dibuat akan tampil di sini.</p>
            </div>
            <span class="status-chip" id="paymentStatusBadge" aria-live="polite">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M8.5 12h7"/></svg>
              <span id="paymentStatusText">Belum ada QRIS</span>
            </span>
          </div>
        </div>

        <div class="panel__body receipt" id="receiptLive" aria-live="polite">
          <div class="qr-placeholder" id="emptyReceipt">
            <div class="qr-placeholder__icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM15 14h2v2h-2zM19 14h1v3h-3v3h-3v-2h2v-2h3z"/></svg></div>
            <p class="qr-placeholder__title">Belum ada transaksi</p>
            <p class="qr-placeholder__copy">Hubungkan token, masukkan nominal, lalu pilih Generate QRIS.</p>
          </div>

          <div class="receipt is-hidden" id="activeReceipt">
            <div class="receipt__hero">
              <div class="receipt__status"><span class="status-chip status-chip--pending" id="heroStatus">Menunggu pembayaran</span></div>
              <p class="receipt__amount-label" id="amountLabel">Total pembayaran</p>
              <p class="receipt__amount" id="receiptAmount">Rp0</p>
              <p class="receipt__requested" id="receiptRequested">—</p>
            </div>

            <div class="qr-well" id="pendingVisual">
              <img id="qrImage" alt="QRIS pembayaran yang siap dipindai" width="512" height="512">
              <div class="qr-placeholder" id="qrFallback">
                <div class="qr-placeholder__icon"><svg class="icon" viewBox="0 0 24 24"><path d="M12 4v10M12 18h.01M5.5 20h13L12 3 5.5 20Z"/></svg></div>
                <p class="qr-placeholder__title" id="qrFallbackTitle">Memuat gambar QRIS</p>
                <p class="qr-placeholder__copy" id="qrFallbackCopy">Gambar diamankan melalui proxy session server.</p>
              </div>
            </div>

            <div class="receipt__hero is-hidden" id="paidVisual">
              <div class="paid-mark"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></div>
              <p class="qr-placeholder__title">Pembayaran diterima</p>
              <p class="qr-placeholder__copy" id="paidTime">Status transaksi sudah dikonfirmasi API.</p>
            </div>

            <div class="receipt__hero is-hidden" id="expiredVisual">
              <div class="paid-mark terminal-mark"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></div>
              <p class="qr-placeholder__title">QRIS sudah kedaluwarsa</p>
              <p class="qr-placeholder__copy">Generate QRIS baru untuk menerima pembayaran.</p>
            </div>

            <div class="receipt__meta">
              <div class="meta-row"><span class="meta-row__label">Provider</span><span class="meta-row__value" id="receiptProvider">—</span></div>
              <div class="meta-row"><span class="meta-row__label">Kedaluwarsa</span><span class="meta-row__value countdown" id="receiptExpiry">—</span></div>
              <div class="meta-row"><span class="meta-row__label">Referensi</span><span class="meta-row__value mono" id="receiptReference">—</span></div>
              <div class="meta-row is-hidden" id="transactionIdRow"><span class="meta-row__label">Transaction ID</span><span class="meta-row__value mono" id="transactionId">—</span></div>
              <div class="meta-row is-hidden" id="issuerRow"><span class="meta-row__label">Issuer</span><span class="meta-row__value" id="receiptIssuer">—</span></div>
            </div>

            <div class="polling-state" id="pollingState"><span id="pollingStateText">Pemeriksaan otomatis tidak aktif</span><strong id="nextCheckText">—</strong></div>

            <div class="notice is-hidden" id="paymentNotice" role="status" aria-live="polite">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
              <div class="notice__content"><p class="notice__title" data-notice-title></p><p class="notice__copy" data-notice-copy></p></div>
            </div>

            <div class="button-cluster">
              <button class="button" id="checkButton" type="button">
                <span class="button__spinner is-hidden" aria-hidden="true"></span>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M7.5 7.5A7 7 0 0 1 20 12M4 12a7 7 0 0 0 12.5 4.5"/></svg>
                <span data-button-label>Cek sekarang</span>
              </button>
              <button class="button button--quiet" id="resetButton" type="button">Reset transaksi</button>
            </div>

            <details class="details">
              <summary>Respons terakhir yang aman ditampilkan</summary>
              <pre class="code-well" id="responseDetails">Belum ada respons.</pre>
            </details>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script type="module" src="assets/app.js"></script>
</body>
</html>
