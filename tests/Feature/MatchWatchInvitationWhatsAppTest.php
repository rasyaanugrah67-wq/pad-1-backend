<?php

namespace Tests\Feature;

use App\Models\JadwalLomba;
use App\Models\KategoriLomba;
use App\Models\Lomba;
use App\Models\Periode;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use App\Models\WhatsappNotificationLog;
use App\Services\MatchWatchInvitationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchWatchInvitationWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    private Rw $hostRw;
    private Rt $hostRt1;
    private Rt $hostRt2;
    private Rw $otherRw;
    private Rt $otherRt;
    private Periode $periode;
    private KategoriLomba $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostRw = Rw::firstOrCreate(['nomor_rw' => '95'], ['nama_rw' => 'RW 95']);
        $this->hostRt1 = Rt::firstOrCreate(['id_rw' => $this->hostRw->id_rw, 'nomor_rt' => '01'], ['nama_rt' => 'RT 01']);
        $this->hostRt2 = Rt::firstOrCreate(['id_rw' => $this->hostRw->id_rw, 'nomor_rt' => '02'], ['nama_rt' => 'RT 02']);

        $this->otherRw = Rw::firstOrCreate(['nomor_rw' => '96'], ['nama_rw' => 'RW 96']);
        $this->otherRt = Rt::firstOrCreate(['id_rw' => $this->otherRw->id_rw, 'nomor_rt' => '01'], ['nama_rt' => 'RT 01']);

        $this->periode = Periode::firstOrCreate(
            ['tahun' => 2094],
            ['nama_kegiatan' => 'HUT RI Testing 2094', 'status' => 'Aktif']
        );
        $this->kategori = KategoriLomba::firstOrCreate(['nama_kategori' => 'Kategori Test Match WA']);
    }

    private function configureWhatsApp(array $overrides = []): void
    {
        Config::set('services.whatsapp', array_merge([
            'enabled' => true,
            'provider' => 'fonnte',
            'api_url' => 'https://api.fonnte.com/send',
            'token' => 'fake-fonnte-token',
            'timeout' => 15,
            'test_mode' => true,
            'test_number' => '6289900005555',
            'match_invitation' => [
                'enabled' => true,
                'rw_number' => '95',
                'lead_minutes' => 30,
                'window_minutes' => 5,
                'max_schedule_updates' => 1,
                'timezone' => 'Asia/Jakarta',
            ],
        ], $overrides));
    }

    private function fakeFonnteSuccess(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);
    }

    private function createWarga(Rt $rt, array $overrides = []): Warga
    {
        static $seq = 1;
        $nik = '950000000000' . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);

        return Warga::create(array_merge([
            'id_rt' => $rt->id_rt,
            'nik' => $nik,
            'nama' => 'Warga Match ' . $seq,
            'no_hp' => '08125555' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ], $overrides));
    }

    private function createLomba(string $nama = 'Futsal RW', bool $isArsip = false): Lomba
    {
        $lomba = Lomba::create([
            'id_periode' => $this->periode->id_periode,
            'id_kategori' => $this->kategori->id_kategori,
            'nama_lomba' => $nama,
            'jenis_pendaftaran' => 'Beregu',
            'status' => 'Dibuka',
        ]);

        if ($isArsip) {
            $lomba->is_arsip = true;
            $lomba->save();
        }

        return $lomba;
    }

    private function createMatch(Lomba $lomba, array $overrides = []): int
    {
        return (int) DB::table('pertandingan')->insertGetId(array_merge([
            'id_lomba' => $lomba->id_lomba,
            'id_jadwal' => null,
            'peserta_1' => 'Tim RT 01',
            'peserta_2' => 'Tim RT 02',
            'babak' => 'Final',
            'waktu_mulai' => '2026-08-17 15:30:00',
            'waktu_selesai' => '2026-08-17 16:30:00',
            'status' => 'Dijadwalkan',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_sends_invitation_to_all_active_warga_across_rts_in_host_rw_and_excludes_ineligible(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        // 2 warga aktif valid di RT 01 dan RT 02 dalam RW penyelenggara (bukan peserta terdaftar).
        $wargaRt1 = $this->createWarga($this->hostRt1, ['nama' => 'Warga RT Satu']);
        $wargaRt2 = $this->createWarga($this->hostRt2, ['nama' => 'Warga RT Dua']);

        // Harus dikecualikan: nonaktif, no_hp kosong, no_hp invalid, dan RW lain.
        $this->createWarga($this->hostRt1, ['status' => 'Nonaktif']);
        $this->createWarga($this->hostRt1, ['no_hp' => '   ']);
        $this->createWarga($this->hostRt2, ['no_hp' => '12345']);
        $this->createWarga($this->otherRt, ['nama' => 'Warga RW Lain']);

        $lomba = $this->createLomba('Tarik Tambang');
        $jadwal = JadwalLomba::create([
            'id_lomba' => $lomba->id_lomba,
            'tanggal' => '2026-08-17',
            'waktu_mulai' => '15:00:00',
            'waktu_selesai' => '17:00:00',
            'lokasi' => 'Lapangan Utama RW 95',
        ]);

        $matchId = $this->createMatch($lomba, [
            'id_jadwal' => $jadwal->id_jadwal,
            'waktu_mulai' => '2026-08-17 15:30:00',
        ]);

        $summary = app(MatchWatchInvitationService::class)->sendInvitations(
            Carbon::parse('2026-08-17 15:00:00', 'Asia/Jakarta'),
            false,
            $matchId
        );

        $this->assertSame(1, $summary['matches_eligible']);
        $this->assertSame(2, $summary['recipients_eligible']);
        $this->assertSame(2, $summary['queued']);

        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req['target'] === '6289900005555'
            && str_contains($req['message'], 'Warga RT Satu')
            && str_contains($req['message'], 'Tarik Tambang')
            && str_contains($req['message'], 'Lapangan Utama RW 95')
            && str_contains($req['message'], 'Final'));
        Http::assertSent(fn ($req) => $req['target'] === '6289900005555'
            && str_contains($req['message'], 'Warga RT Dua'));

        $this->assertSame(2, WhatsappNotificationLog::where('trigger_type', MatchWatchInvitationService::TRIGGER_TYPE)
            ->where('reference_id', $matchId)
            ->where('delivery_state', WhatsappNotificationLog::STATE_QUEUED)
            ->count());
    }

    public function test_excludes_ongoing_finished_deleted_matches_archived_lomba_and_outside_30_min_window(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $this->createWarga($this->hostRt1);

        $lombaAktif = $this->createLomba('Volly');
        $lombaArsip = $this->createLomba('Lomba Arsip', true);

        $mBerlangsung = $this->createMatch($lombaAktif, ['status' => 'Berlangsung', 'waktu_mulai' => '2026-08-17 15:30:00']);
        $mSelesai = $this->createMatch($lombaAktif, ['status' => 'Selesai', 'waktu_mulai' => '2026-08-17 15:30:00']);
        $mDihapus = $this->createMatch($lombaAktif, ['status' => 'Dijadwalkan', 'waktu_mulai' => '2026-08-17 15:30:00']);
        DB::table('pertandingan')->where('id_pertandingan', $mDihapus)->delete();

        $mArsip = $this->createMatch($lombaArsip, ['status' => 'Dijadwalkan', 'waktu_mulai' => '2026-08-17 15:30:00']);
        $mTerlaluJauh = $this->createMatch($lombaAktif, ['status' => 'Dijadwalkan', 'waktu_mulai' => '2026-08-17 16:30:00']);
        $mTerlaluDekat = $this->createMatch($lombaAktif, ['status' => 'Dijadwalkan', 'waktu_mulai' => '2026-08-17 15:10:00']);

        $service = app(MatchWatchInvitationService::class);
        $now = Carbon::parse('2026-08-17 15:00:00', 'Asia/Jakarta');

        foreach ([$mBerlangsung, $mSelesai, $mDihapus, $mArsip, $mTerlaluJauh, $mTerlaluDekat] as $id) {
            $res = $service->sendInvitations($now, false, $id);
            $this->assertSame(0, $res['matches_eligible']);
            $this->assertSame(0, $res['queued']);
        }

        Http::assertNothingSent();
    }

    public function test_deduplication_prevents_duplicates_on_repeated_runs(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $warga = $this->createWarga($this->hostRt1);
        $lomba = $this->createLomba();
        $matchId = $this->createMatch($lomba, ['waktu_mulai' => '2026-08-17 15:30:00']);

        $service = app(MatchWatchInvitationService::class);

        // Run pertama pada 15:00 (30 menit sebelum) -> terkirim.
        $first = $service->sendInvitations(Carbon::parse('2026-08-17 15:00:00', 'Asia/Jakarta'), false, $matchId, null, $warga->id_warga);
        // Run kedua pada 15:05 (25 menit sebelum, masih di dalam jendela) -> tidak boleh kirim ulang.
        $second = $service->sendInvitations(Carbon::parse('2026-08-17 15:05:00', 'Asia/Jakarta'), false, $matchId, null, $warga->id_warga);

        $this->assertSame(1, $first['queued']);
        $this->assertSame(0, $second['queued']);
        $this->assertSame(1, $second['already_sent']);
        Http::assertSentCount(1);
    }

    public function test_schedule_change_before_and_after_sending_is_controlled(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $warga = $this->createWarga($this->hostRt1);
        $lomba = $this->createLomba('Badminton');
        $matchId = $this->createMatch($lomba, ['waktu_mulai' => '2026-08-17 15:30:00']);

        $service = app(MatchWatchInvitationService::class);

        // 1. Jadwal diubah SEBELUM pengiriman (dari 15:30 menjadi 16:00).
        DB::table('pertandingan')->where('id_pertandingan', $matchId)->update(['waktu_mulai' => '2026-08-17 16:00:00']);

        // Pada 15:00 tidak mengirim (karena jadwal baru 16:00).
        $this->assertSame(0, $service->sendInvitations(Carbon::parse('2026-08-17 15:00:00', 'Asia/Jakarta'), false, $matchId, null, $warga->id_warga)['queued']);

        // Pada 15:30 mengirim undangan awal dengan jadwal terbaru (16:00).
        $initial = $service->sendInvitations(Carbon::parse('2026-08-17 15:30:00', 'Asia/Jakarta'), false, $matchId, null, $warga->id_warga);
        $this->assertSame(1, $initial['queued']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req['message'], '16:00 WIB'));

        // 2. Jadwal diubah 1 kali SETELAH pengiriman (menjadi 17:00) -> diizinkan 1x pembaruan terkendali.
        DB::table('pertandingan')->where('id_pertandingan', $matchId)->update(['waktu_mulai' => '2026-08-17 17:00:00']);
        $firstUpdate = $service->sendInvitations(Carbon::parse('2026-08-17 16:30:00', 'Asia/Jakarta'), false, $matchId, null, $warga->id_warga);
        $this->assertSame(1, $firstUpdate['queued']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => str_contains($req['message'], 'Pembaruan jadwal') && str_contains($req['message'], '17:00 WIB'));

        // 3. Jadwal diubah LAGI (menjadi 18:00) -> ditahan oleh batas max_schedule_updates=1 agar tidak spam.
        DB::table('pertandingan')->where('id_pertandingan', $matchId)->update(['waktu_mulai' => '2026-08-17 18:00:00']);
        $secondUpdate = $service->sendInvitations(Carbon::parse('2026-08-17 17:30:00', 'Asia/Jakarta'), false, $matchId, null, $warga->id_warga);
        $this->assertSame(0, $secondUpdate['queued']);
        $this->assertSame(1, $secondUpdate['max_updates_blocked']);
        Http::assertSentCount(2);
    }

    public function test_provider_failure_is_isolated_per_warga_and_failed_log_can_be_retried(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::sequence()
                ->push(['status' => false, 'reason' => 'temporary error'], 500)
                ->push(['status' => true, 'detail' => 'success! message in queue', 'process' => 'pending'], 200)
                ->push(['status' => true, 'detail' => 'success! message in queue', 'process' => 'pending'], 200),
        ]);

        $warga1 = $this->createWarga($this->hostRt1, ['nama' => 'Warga Gagal Dulu']);
        $warga2 = $this->createWarga($this->hostRt2, ['nama' => 'Warga Sukses Langsung']);
        $lomba = $this->createLomba();
        $matchId = $this->createMatch($lomba, ['waktu_mulai' => '2026-08-17 15:30:00']);

        $service = app(MatchWatchInvitationService::class);
        $now = Carbon::parse('2026-08-17 15:00:00', 'Asia/Jakarta');

        // Run pertama: warga1 gagal, warga2 tetap berhasil terkirim (terisolasi).
        $first = $service->sendInvitations($now, false, $matchId);
        $this->assertSame(1, $first['failed']);
        $this->assertSame(1, $first['queued']);

        // Run kedua: warga1 (failed) dicoba ulang dan berhasil, warga2 (queued) di-skip.
        $retry = $service->sendInvitations($now, false, $matchId);
        $this->assertSame(1, $retry['queued']);
        $this->assertSame(1, $retry['already_sent']);

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'trigger_type' => MatchWatchInvitationService::TRIGGER_TYPE,
            'reference_id' => $matchId,
            'id_warga' => $warga1->id_warga,
            'delivery_state' => WhatsappNotificationLog::STATE_QUEUED,
            'attempts' => 2,
        ]);
        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'trigger_type' => MatchWatchInvitationService::TRIGGER_TYPE,
            'reference_id' => $matchId,
            'id_warga' => $warga2->id_warga,
            'delivery_state' => WhatsappNotificationLog::STATE_QUEUED,
            'attempts' => 1,
        ]);
    }

    public function test_command_dry_run_and_targeted_warga_option_respect_rw_and_status_validation(): void
    {
        $this->configureWhatsApp();
        Http::fake();

        $wargaValid = $this->createWarga($this->hostRt1);
        $wargaOtherRw = $this->createWarga($this->otherRt);
        $wargaInactive = $this->createWarga($this->hostRt1, ['status' => 'Nonaktif']);

        $lomba = $this->createLomba();
        $matchId = $this->createMatch($lomba, ['waktu_mulai' => '2026-08-17 15:30:00']);

        // Dry-run untuk warga valid di RW penyelenggara -> Would Send: 1, tanpa HTTP & tanpa log.
        $this->artisan('whatsapp:send-match-invitations', [
            '--dry-run' => true,
            '--pertandingan' => $matchId,
            '--warga' => $wargaValid->id_warga,
            '--now' => '2026-08-17 15:00',
        ])
            ->expectsOutputToContain('Would Send: 1')
            ->assertExitCode(0);

        // --warga dari RW lain dan --warga Nonaktif harus ditolak (Recipients: 0).
        $this->artisan('whatsapp:send-match-invitations', [
            '--dry-run' => true,
            '--pertandingan' => $matchId,
            '--warga' => $wargaOtherRw->id_warga,
            '--now' => '2026-08-17 15:00',
        ])
            ->expectsOutputToContain('Recipients: 0')
            ->assertExitCode(0);

        $this->artisan('whatsapp:send-match-invitations', [
            '--dry-run' => true,
            '--pertandingan' => $matchId,
            '--warga' => $wargaInactive->id_warga,
            '--now' => '2026-08-17 15:00',
        ])
            ->expectsOutputToContain('Recipients: 0')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('whatsapp_notification_logs', [
            'trigger_type' => MatchWatchInvitationService::TRIGGER_TYPE,
            'reference_id' => $matchId,
        ]);
    }
}
