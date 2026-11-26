<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('title', 150);
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')
                ->references('id_akun')
                ->on('akun')
                ->nullOnDelete();
        });

        $now = now();
        DB::table('whatsapp_templates')->insert([
            [
                'key' => 'account_created',
                'title' => 'Informasi Akun Warga Baru',
                'description' => 'Dikirim otomatis setelah Admin menambahkan data warga dan akun login baru.',
                'is_active' => true,
                'body' => "Halo Bapak/Ibu {nama},\n\nAkun Anda untuk Website Perlombaan 17 Agustus telah berhasil dibuat oleh Admin.\n\nUsername: {username}\n\nDemi keamanan, kata sandi tidak dikirimkan melalui WhatsApp. Silakan hubungi Admin atau Panitia untuk akses masuk pertama, lalu segera ubah kata sandi Anda setelah berhasil login.\n\nTerima kasih.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'iuran_reminder',
                'title' => 'Pengingat Iuran Belum Lunas',
                'description' => 'Dikirim otomatis pada H-3, H, dan H+7 jatuh tempo kepada warga yang iurannya belum lunas.',
                'is_active' => true,
                'body' => "Halo Bapak/Ibu {nama},\n\nKami mengingatkan bahwa tagihan {nama_iuran} Anda belum lunas.\n\nNominal: Rp{nominal}\nBatas pembayaran: {tanggal}\nKeterangan: {keterangan}\n\nMohon segera melakukan pembayaran dan mengunggah bukti pembayaran melalui Website Perlombaan 17 Agustus sebelum batas waktu.\n\nTerima kasih atas kerja samanya.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_confirmed',
                'title' => 'Konfirmasi Pembayaran Diterima',
                'description' => 'Dikirim otomatis setelah Admin atau Panitia memverifikasi pembayaran menjadi Diterima.',
                'is_active' => true,
                'body' => "Halo Bapak/Ibu {nama},\n\nTerima kasih, pembayaran {nama_iuran} Anda telah diverifikasi dan diterima oleh Panitia.\n\nNominal: Rp{nominal}\nStatus iuran: Sudah Bayar\nWaktu verifikasi: {waktu}\n\nTerima kasih atas partisipasi dan dukungan Anda untuk kegiatan 17 Agustus.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'lomba_reminder',
                'title' => 'Pengingat Peserta Lomba',
                'description' => 'Dikirim otomatis pada H-1 dan hari H kepada peserta lomba yang pendaftarannya berstatus Diterima.',
                'is_active' => true,
                'body' => "Halo {nama},\n\nIni adalah pengingat bahwa Anda terdaftar sebagai peserta lomba 17 Agustus:\n\nLomba: {nama_lomba}\nTanggal: {tanggal}\nWaktu: {waktu}\nLokasi: {lokasi}\nKeterangan: {keterangan}\n\nMohon hadir tepat waktu dan persiapkan diri dengan baik. Sampai jumpa di arena lomba!",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'match_invitation',
                'title' => 'Undangan Menonton Pertandingan',
                'description' => 'Dikirim otomatis sekitar 30 menit sebelum pertandingan dimulai kepada seluruh warga aktif RW penyelenggara.',
                'is_active' => true,
                'body' => "Halo Bapak/Ibu {nama},\n\n{pembuka}\n\nLomba: {nama_lomba}\nBabak: {babak}\nPertandingan: {pertandingan}\nJadwal: {tanggal}, {waktu}\nLokasi: {lokasi}\n\nMari ajak keluarga dan tetangga untuk meramaikan serta mendukung para peserta!",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
