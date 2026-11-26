<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_DEFAULT_BODY = "Halo Bapak/Ibu {nama},\n\nAkun Anda untuk Website Perlombaan 17 Agustus telah berhasil dibuat oleh Admin.\n\nUsername: {username}\n\nDemi keamanan, kata sandi tidak dikirimkan melalui WhatsApp. Silakan hubungi Admin atau Panitia untuk akses masuk pertama, lalu segera ubah kata sandi Anda setelah berhasil login.\n\nTerima kasih.";

    private const NEW_DEFAULT_BODY = "Halo {nama}!\n\nAkun aplikasi 17-an Mobile Anda telah berhasil dibuat oleh Admin.\n\nBerikut informasi login akun Anda:\n\nUsername: {username}\nPassword awal: {password_awal}\n\nSilakan login menggunakan informasi tersebut.\n\nDemi keamanan akun, mohon segera ganti password awal melalui menu Profil setelah berhasil login.\n\nTerima kasih.\nAdmin PAD";

    public function up(): void
    {
        // Hanya perbarui jika template masih berupa default lama dan belum pernah diedit Admin.
        DB::table('whatsapp_templates')
            ->where('key', 'account_created')
            ->whereNull('updated_by')
            ->where('body', self::OLD_DEFAULT_BODY)
            ->update([
                'body' => self::NEW_DEFAULT_BODY,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Hanya rollback jika template masih berupa default baru dan belum pernah diedit Admin.
        DB::table('whatsapp_templates')
            ->where('key', 'account_created')
            ->whereNull('updated_by')
            ->where('body', self::NEW_DEFAULT_BODY)
            ->update([
                'body' => self::OLD_DEFAULT_BODY,
                'updated_at' => now(),
            ]);
    }
};
