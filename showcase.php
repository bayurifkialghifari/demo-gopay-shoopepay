<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title>QRIS Lab — Primitive Showcase</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <header class="site-header">
    <div class="site-header__inner">
      <a class="brand" href="showcase.php" aria-label="QRIS Lab primitive showcase">
        <span class="brand__mark" aria-hidden="true">
          <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM15 14h2v2h-2zM19 14h1v3h-3v3h-3v-2h2v-2h3z"/></svg>
        </span>
        QRIS Lab
      </a>
      <span class="status-chip status-chip--info">Showcase komponen</span>
    </div>
  </header>

  <main class="page-shell">
    <header class="showcase-header">
      <p class="eyebrow">Design system gate</p>
      <h1 class="showcase-title">Primitif untuk alur pembayaran yang jelas.</h1>
      <p>Halaman ini memeriksa hierarki, state kontrol, status transaksi, receipt, serta perilaku responsif sebelum layar produk dirakit.</p>
    </header>

    <section class="showcase-section" aria-labelledby="showcase-buttons">
      <div>
        <h2 class="panel__title" id="showcase-buttons">Buttons & status</h2>
        <p class="section-copy">Aksi memakai target sentuh minimum 44px dan state yang dapat dibedakan.</p>
      </div>
      <div class="showcase-grid">
        <article class="showcase-card">
          <h3>Button variants</h3>
          <div class="showcase-stack">
            <button class="button button--primary" type="button"><svg class="icon" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Generate QRIS</button>
            <button class="button" type="button">Cek pembayaran</button>
            <button class="button button--quiet" type="button">Lihat detail</button>
            <button class="button button--danger" type="button">Reset transaksi</button>
            <button class="button" type="button" disabled>Belum tersedia</button>
            <button class="button button--primary" type="button" disabled><span class="button__spinner" aria-hidden="true"></span>Membuat QRIS</button>
          </div>
        </article>
        <article class="showcase-card">
          <h3>Status variants</h3>
          <div class="showcase-stack">
            <span class="status-chip">Belum ada QRIS</span>
            <span class="status-chip status-chip--connected"><svg class="icon" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>Terhubung</span>
            <span class="status-chip status-chip--pending"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>Menunggu pembayaran</span>
            <span class="status-chip status-chip--checking"><span class="status-chip__spinner" aria-hidden="true"></span>Memeriksa</span>
            <span class="status-chip status-chip--paid"><svg class="icon" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>Sudah dibayar</span>
            <span class="status-chip status-chip--expired"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="m9 9 6 6m0-6-6 6"/></svg>Kedaluwarsa</span>
          </div>
        </article>
      </div>
    </section>

    <section class="showcase-section" aria-labelledby="showcase-fields">
      <div>
        <h2 class="panel__title" id="showcase-fields">Fields & settings</h2>
        <p class="section-copy">Label selalu terlihat, error dekat input, dan switch jelas tanpa mengandalkan warna.</p>
      </div>
      <div class="showcase-grid">
        <article class="showcase-card">
          <div class="section-stack">
            <div class="field">
              <label class="field__label" for="demo-token">API token</label>
              <div class="input-shell">
                <input class="input input--with-action" id="demo-token" type="password" value="demo-secret-token" autocomplete="off">
                <button class="input-action" type="button" aria-label="Tampilkan token" title="Tampilkan token"><svg class="icon" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
              </div>
              <p class="field__hint">Token hanya disimpan di session server.</p>
            </div>
            <div class="field">
              <label class="field__label" for="demo-amount">Nominal pembayaran</label>
              <input class="input" id="demo-amount" type="text" inputmode="numeric" value="500" aria-invalid="true" aria-describedby="demo-amount-error">
              <p class="field__message field__message--error" id="demo-amount-error">Nominal minimum adalah Rp1.000.</p>
            </div>
            <div class="field">
              <label class="field__label" for="demo-disabled">Disabled field</label>
              <input class="input" id="demo-disabled" value="Tidak bisa diedit" disabled>
            </div>
          </div>
        </article>
        <article class="showcase-card">
          <div class="section-stack">
            <label class="switch-row">
              <span class="switch-row__copy"><span class="switch-row__label">Cek pembayaran otomatis</span><span class="switch-row__hint">Status diperiksa setelah request sebelumnya selesai.</span></span>
              <span class="switch"><input class="switch__input" type="checkbox" checked><span class="switch__track" aria-hidden="true"><span class="switch__thumb"><svg class="icon" viewBox="0 0 24 24"><path d="m6 12 4 4 8-9"/></svg></span></span></span>
            </label>
            <label class="switch-row">
              <span class="switch-row__copy"><span class="switch-row__label">Polling tidak tersedia</span><span class="switch-row__hint">Buat QRIS lebih dulu untuk mengaktifkannya.</span></span>
              <span class="switch"><input class="switch__input" type="checkbox" disabled><span class="switch__track" aria-hidden="true"><span class="switch__thumb"></span></span></span>
            </label>
          </div>
        </article>
      </div>
    </section>

    <section class="showcase-section" aria-labelledby="showcase-notices">
      <div><h2 class="panel__title" id="showcase-notices">Inline feedback</h2><p class="section-copy">Pesan menjelaskan situasi dan langkah berikutnya.</p></div>
      <div class="showcase-grid">
        <div class="notice"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><div class="notice__content"><p class="notice__title">Token tersimpan di session</p><p class="notice__copy">Browser tidak menerima kembali nilai token lengkap.</p></div></div>
        <div class="notice notice--success"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg><div class="notice__content"><p class="notice__title">Pembayaran diterima</p><p class="notice__copy">Polling dihentikan dan receipt sudah diperbarui.</p></div></div>
        <div class="notice notice--warning"><svg class="icon" viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4M12 16h.01"/></svg><div class="notice__content"><p class="notice__title">QRIS hampir kedaluwarsa</p><p class="notice__copy">Selesaikan pembayaran atau buat QRIS baru setelah waktu habis.</p></div></div>
        <div class="notice notice--error" role="alert"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg><div class="notice__content"><p class="notice__title">Token ditolak API</p><p class="notice__copy">Periksa token, simpan ulang, lalu coba hubungkan kembali.</p></div></div>
      </div>
    </section>

    <section class="showcase-section" aria-labelledby="showcase-receipt">
      <div><h2 class="panel__title" id="showcase-receipt">Live receipt</h2><p class="section-copy">Struktur stabil saat transaksi berpindah dari pending ke paid.</p></div>
      <div class="app-grid">
        <article class="panel panel--receipt">
          <div class="panel__header"><div class="panel__header-row"><div><h3 class="panel__title">QRIS aktif</h3><p class="panel__description">Scan dengan aplikasi pembayaran yang mendukung QRIS.</p></div><span class="status-chip status-chip--pending">Menunggu</span></div></div>
          <div class="panel__body receipt">
            <div class="receipt__hero"><p class="receipt__amount-label">Total pembayaran</p><p class="receipt__amount">Rp25.001</p><p class="receipt__requested">Nominal Rp25.000 + kode unik Rp1</p></div>
            <div class="qr-well"><div class="qr-placeholder"><div class="qr-placeholder__icon"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM15 14h2v2h-2zM19 14h1v3h-3v3h-3v-2h2v-2h3z"/></svg></div><p class="qr-placeholder__title">Area gambar QRIS</p><p class="qr-placeholder__copy">Gambar asli diproksi dari server saat transaksi tersedia.</p></div></div>
            <div class="receipt__meta"><div class="meta-row"><span class="meta-row__label">Provider</span><span class="meta-row__value">GoPay</span></div><div class="meta-row"><span class="meta-row__label">Kedaluwarsa</span><span class="meta-row__value countdown">14 menit 32 detik</span></div><div class="meta-row"><span class="meta-row__label">ID transaksi</span><span class="meta-row__value mono">7a1…92fe</span></div></div>
            <div class="polling-state"><span>Pemeriksaan otomatis aktif</span><strong>Berikutnya 5 detik</strong></div>
            <div class="button-cluster"><button class="button" type="button">Cek sekarang</button><button class="button button--quiet" type="button">Reset transaksi</button></div>
          </div>
        </article>
        <article class="panel panel--receipt">
          <div class="panel__header"><div class="panel__header-row"><div><h3 class="panel__title">Pembayaran selesai</h3><p class="panel__description">Receipt berubah di tempat tanpa kehilangan konteks.</p></div><span class="status-chip status-chip--paid">Dibayar</span></div></div>
          <div class="panel__body receipt">
            <div class="receipt__hero"><div class="paid-mark"><svg class="icon" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></div><p class="receipt__amount-label">Pembayaran diterima</p><p class="receipt__amount">Rp25.001</p><p class="receipt__requested">23 Oktober 2026, 10.15</p></div>
            <div class="receipt__meta"><div class="meta-row"><span class="meta-row__label">Transaction ID</span><span class="meta-row__value mono">GK-20261023-000184</span></div><div class="meta-row"><span class="meta-row__label">Issuer</span><span class="meta-row__value">GoPay / QRIS</span></div><div class="meta-row"><span class="meta-row__label">Provider</span><span class="meta-row__value">GoPay</span></div></div>
            <button class="button button--primary button--block" type="button">Buat QRIS baru</button>
          </div>
        </article>
      </div>
    </section>
  </main>
</body>
</html>
