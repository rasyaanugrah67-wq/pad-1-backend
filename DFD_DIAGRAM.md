# DATA FLOW DIAGRAM (DFD)
## SISTEM PENGELOLAAN PERLOMBAAN 17 AGUSTUS

Dokumen ini berisi kode Diagram Konteks (Level 0) dan DFD Level 1 yang siap di-copy ke **[mermaid.live](https://mermaid.live)** atau **[draw.io](https://app.diagrams.net)**.

---

## 1. KODE DIAGRAM KONTEKS (DFD LEVEL 0)

Copy semua kode di dalam kotak di bawah ini:

```mermaid
flowchart TD
    Admin["ADMIN"]
    Panitia["PANITIA"]
    Warga["WARGA"]
    
    System(("0.0<br/>SISTEM PENGELOLAAN<br/>PERLOMBAAN 17 AGUSTUS"))

    %% Aliran Admin
    Admin -->|"Data Wilayah (RT/RW)<br/>Data Warga & Generate Akun<br/>Data Periode Kegiatan<br/>Penetapan Kepanitiaan<br/>Data Master Kategori Lomba"| System
    System -->|"Laporan Data Warga & Akun<br/>Laporan Rekap Pendaftaran<br/>Laporan Keuangan Iuran<br/>Laporan Hasil Kegiatan"| Admin

    %% Aliran Panitia
    Panitia -->|"Data Lomba & Detail Aturan<br/>Jadwal & Lokasi Lomba<br/>Status Verifikasi Pendaftaran<br/>Verifikasi Pembayaran Iuran<br/>Upload File Dokumentasi"| System
    System -->|"Daftar Peserta Masuk<br/>Daftar Pembayaran Iuran Warga<br/>Info Jadwal & Penugasan Panitia"| Panitia

    %% Aliran Warga
    Warga -->|"Kredensial Login<br/>Form Pendaftaran Lomba<br/>Bukti Bayar Iuran & Nominal"| System
    System -->|"Notifikasi WhatsApp/Web (Akun, Jadwal, Tagihan)<br/>Informasi Daftar Lomba & Jadwal<br/>Status Pendaftaran & Bukti Iuran<br/>Galeri Foto/Video Dokumentasi"| Warga
```

---

## 2. KODE DFD LEVEL 1 (DEKOMPOSISI 7 PROSES)

Copy semua kode di dalam kotak di bawah ini:

```mermaid
flowchart TD
    Admin["Admin"]
    Panitia["Panitia"]
    Warga["Warga"]

    %% Data Stores
    D1[("D1: Role & Akun")]
    D2[("D2: RT, RW, & Warga")]
    D3[("D3: Periode & Kepanitiaan")]
    D4[("D4: Lomba, Kategori, Detail, & Jadwal")]
    D5[("D5: Pendaftaran")]
    D6[("D6: Iuran & Pembayaran")]
    D7[("D7: Notifikasi")]
    D8[("D8: Dokumentasi")]

    %% Process 1.0
    P1(("1.0<br/>Kelola Wilayah,<br/>Warga & Akun"))
    Admin -->|"Input RT/RW, Warga, Role"| P1
    P1 -->|"Simpan Data Warga"| D2
    P1 -->|"Generate Akun & Role"| D1
    P1 -->|"Kirim Notif Akun Baru"| D7
    D2 -->|"Data Warga"| P1
    P1 -->|"Laporan Warga & Akun"| Admin

    %% Process 2.0
    P2(("2.0<br/>Kelola Periode &<br/>Kepanitiaan"))
    Admin -->|"Input Tahun, Logo, Panitia"| P2
    D2 -->|"Ambil Data Warga terpilih"| P2
    P2 -->|"Simpan Periode & Panitia"| D3
    D3 -->|"Info Panitia Aktif"| Panitia

    %% Process 3.0
    P3(("3.0<br/>Kelola Lomba &<br/>Jadwal Kegiatan"))
    Panitia -->|"Input Lomba, Detail, Jadwal"| P3
    Admin -->|"Input Lomba, Detail, Jadwal"| P3
    D3 -->|"Cek Periode Aktif"| P3
    P3 -->|"Simpan Lomba & Jadwal"| D4
    D4 -->|"Katalog Lomba & Jadwal"| Warga

    %% Process 4.0
    P4(("4.0<br/>Kelola Pendaftaran<br/>Lomba"))
    Warga -->|"Pilih Lomba & Ajukan Daftar"| P4
    D2 -->|"Validasi Data Warga"| P4
    D4 -->|"Validasi Kuota/Syarat Lomba"| P4
    P4 -->|"Simpan Pengajuan"| D5
    D5 -->|"Data Pendaftar"| P4
    Panitia -->|"Verifikasi Status Daftar"| P4
    P4 -->|"Update Status Pendaftaran"| D5
    P4 -->|"Trigger Notif Lomba"| D7
    P4 -->|"Status Pendaftaran Lomba"| Warga

    %% Process 5.0
    P5(("5.0<br/>Kelola Iuran &<br/>Pembayaran"))
    Admin -->|"Setting Tagihan Iuran Periode"| P5
    D2 -->|"Generate Tagihan per Warga"| P5
    P5 -->|"Simpan Tagihan Iuran"| D6
    P5 -->|"Trigger Notif Tagihan"| D7
    D6 -->|"Info Tagihan Iuran"| Warga
    Warga -->|"Upload Bukti Transfer / QRIS"| P5
    P5 -->|"Simpan Data Pembayaran"| D6
    D6 -->|"Data Pembayaran Masuk"| P5
    Panitia -->|"Verifikasi Pembayaran"| P5
    P5 -->|"Update Status Lunas"| D6
    P5 -->|"Trigger Notif Pembayaran Sukses"| D7
    P5 -->|"Laporan Keuangan Iuran"| Admin

    %% Process 6.0
    P6(("6.0<br/>Kelola Dokumentasi<br/>& Galeri"))
    Panitia -->|"Upload Foto/Video Lomba"| P6
    D4 -->|"Kaitkan dengan ID Lomba"| P6
    D3 -->|"Kaitkan dengan ID Panitia"| P6
    P6 -->|"Simpan File Dokumentasi"| D8
    D8 -->|"Tampilkan Galeri & Histori"| Warga

    %% Process 7.0
    P7(("7.0<br/>Broadcast &<br/>Distribusi Notifikasi"))
    D7 -->|"Ambil Antrean Notifikasi"| P7
    P7 -->|"Kirim Notifikasi WA / In-App"| Warga
```

---

## 3. CARA MENGGUNAKAN KODE DI ATAS:

### Pilihan 1: Di Website [Mermaid Live Editor](https://mermaid.live)
1. Buka [https://mermaid.live](https://mermaid.live)
2. Hapus teks contoh di sebelah kiri.
3. Copy salah satu kode blok di atas (mulai dari kata `flowchart TD` sampai baris terakhir).
4. Paste ke kolom sebelah kiri. Diagram langsung tergambar otomatis di kanan!
5. Klik tombol **Download PNG** untuk menyimpan gambar.

### Pilihan 2: Di Website [Draw.io](https://app.diagrams.net)
1. Buka [https://app.diagrams.net](https://app.diagrams.net)
2. Klik menu **Arrange** -> **Insert** -> **Advanced** -> **Mermaid**.
3. Paste kodenya di sana lalu klik **Insert**.

