<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewWhatsappTemplateRequest;
use App\Http\Requests\UpdateWhatsappTemplateRequest;
use App\Services\WhatsappTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class WhatsappTemplateController extends Controller
{
    public function __construct(
        private readonly WhatsappTemplateService $templates
    ) {
    }

    /**
     * Menampilkan seluruh template notifikasi WhatsApp.
     *
     * @OA\Get(
     *     path="/api/admin/whatsapp-templates",
     *     operationId="getDaftarWhatsappTemplates",
     *     tags={"Manajemen - WhatsApp"},
     *     summary="Melihat seluruh template notifikasi WhatsApp",
     *     description="Mengambil 5 template notifikasi WhatsApp resmi PAD (account_created, iuran_reminder, payment_confirmed, lomba_reminder, match_invitation) beserta daftar placeholder yang diperbolehkan, contoh data, dan preview hasil render. Hanya dapat diakses oleh Admin.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Daftar template WhatsApp berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar template WhatsApp berhasil diambil."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="key", type="string", example="account_created"),
     *                     @OA\Property(property="title", type="string", example="Informasi Akun Warga Baru"),
     *                     @OA\Property(property="body", type="string", example="Halo Bapak/Ibu {nama}, akun Anda ({username}) telah dibuat."),
     *                     @OA\Property(property="description", type="string", example="Dikirim otomatis setelah Admin menambahkan data warga dan akun login baru."),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="allowed_placeholders", type="array", @OA\Items(type="string"), example={"{nama}", "{username}", "{password_awal}"}),
     *                     @OA\Property(property="sample_data", type="object"),
     *                     @OA\Property(property="preview", type="string"),
     *                     @OA\Property(property="updated_by", type="string", nullable=true, example="admin"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login (Unauthenticated)"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)")
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Daftar template WhatsApp berhasil diambil.',
            'data' => $this->templates->allTemplates()->values(),
        ]);
    }

    /**
     * Menampilkan detail satu template notifikasi WhatsApp.
     *
     * @OA\Get(
     *     path="/api/admin/whatsapp-templates/{key}",
     *     operationId="getDetailWhatsappTemplate",
     *     tags={"Manajemen - WhatsApp"},
     *     summary="Melihat detail template notifikasi WhatsApp",
     *     description="Mengambil detail satu template berdasarkan key beserta daftar placeholder yang diperbolehkan: account_created ({nama}, {username}, {password_awal}); iuran_reminder ({nama}, {nama_iuran}, {nominal}, {tanggal}, {keterangan}); payment_confirmed ({nama}, {nama_iuran}, {nominal}, {tanggal}, {waktu}); lomba_reminder ({nama}, {nama_lomba}, {tanggal}, {waktu}, {lokasi}, {keterangan}); match_invitation ({nama}, {nama_lomba}, {babak}, {pertandingan}, {tanggal}, {waktu}, {lokasi}, {wilayah}, {pembuka}). Hanya dapat diakses oleh Admin.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Kunci unik template WhatsApp",
     *         @OA\Schema(
     *             type="string",
     *             enum={"account_created", "iuran_reminder", "payment_confirmed", "lomba_reminder", "match_invitation"},
     *             example="iuran_reminder"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail template WhatsApp berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail template WhatsApp berhasil diambil."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="key", type="string", example="iuran_reminder"),
     *                 @OA\Property(property="title", type="string", example="Pengingat Iuran Belum Lunas"),
     *                 @OA\Property(property="body", type="string", example="Halo Bapak/Ibu {nama}, tagihan {nama_iuran} Rp{nominal} jatuh tempo {tanggal}. {keterangan}"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="allowed_placeholders", type="array", @OA\Items(type="string"), example={"{nama}", "{nama_iuran}", "{nominal}", "{tanggal}", "{keterangan}"}),
     *                 @OA\Property(property="sample_data", type="object"),
     *                 @OA\Property(property="preview", type="string"),
     *                 @OA\Property(property="updated_by", type="string", nullable=true, example="admin"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login (Unauthenticated)"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Template WhatsApp tidak ditemukan")
     * )
     */
    public function show(string $key): JsonResponse
    {
        $template = $this->templates->findByKey($key);
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template WhatsApp tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail template WhatsApp berhasil diambil.',
            'data' => $this->templates->formatTemplate($template),
        ]);
    }

    /**
     * Mengubah judul, isi pesan, dan status aktif template WhatsApp.
     *
     * @OA\Put(
     *     path="/api/admin/whatsapp-templates/{key}",
     *     operationId="updateWhatsappTemplate",
     *     tags={"Manajemen - WhatsApp"},
     *     summary="Mengedit template notifikasi WhatsApp",
     *     description="Memperbarui judul, isi pesan, dan opsional status aktif template WhatsApp. Placeholder divalidasi ketat sesuai key template: account_created ({nama}, {username}, {password_awal}); iuran_reminder ({nama}, {nama_iuran}, {nominal}, {tanggal}, {keterangan}); payment_confirmed ({nama}, {nama_iuran}, {nominal}, {tanggal}, {waktu}); lomba_reminder ({nama}, {nama_lomba}, {tanggal}, {waktu}, {lokasi}, {keterangan}); match_invitation ({nama}, {nama_lomba}, {babak}, {pertandingan}, {tanggal}, {waktu}, {lokasi}, {wilayah}, {pembuka}). Placeholder di luar daftar akan ditolak dengan 422.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Kunci unik template WhatsApp",
     *         @OA\Schema(
     *             type="string",
     *             enum={"account_created", "iuran_reminder", "payment_confirmed", "lomba_reminder", "match_invitation"},
     *             example="iuran_reminder"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "body"},
     *             @OA\Property(property="title", type="string", maxLength=150, example="Pengingat Iuran Belum Lunas"),
     *             @OA\Property(property="body", type="string", maxLength=2000, example="Halo Bapak/Ibu {nama},\n\nTagihan {nama_iuran} sebesar Rp{nominal} jatuh tempo pada {tanggal}.\nKeterangan: {keterangan}"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template WhatsApp berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Template WhatsApp berhasil diperbarui."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login (Unauthenticated)"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Template WhatsApp tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal (termasuk placeholder tidak diperbolehkan)")
     * )
     */
    public function update(UpdateWhatsappTemplateRequest $request, string $key): JsonResponse
    {
        $template = $this->templates->findByKey($key);
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template WhatsApp tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validated();

        $template->title = $validated['title'];
        $template->body = $validated['body'];
        if (array_key_exists('is_active', $validated)) {
            $template->is_active = (bool) $validated['is_active'];
        }
        $template->updated_by = $request->user()?->id_akun;
        $template->save();

        $template->refresh()->load('updater:id_akun,username');

        return response()->json([
            'success' => true,
            'message' => 'Template WhatsApp berhasil diperbarui.',
            'data' => $this->templates->formatTemplate($template),
        ]);
    }

    /**
     * Mengaktifkan atau menonaktifkan template notifikasi WhatsApp.
     *
     * @OA\Patch(
     *     path="/api/admin/whatsapp-templates/{key}/status",
     *     operationId="updateStatusWhatsappTemplate",
     *     tags={"Manajemen - WhatsApp"},
     *     summary="Mengaktifkan atau menonaktifkan template WhatsApp",
     *     description="Mengubah status aktif template. Ketika is_active bernilai false, pengiriman otomatis WhatsApp untuk trigger terkait akan dihentikan sementara tanpa mengganggu transaksi utama.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Kunci unik template WhatsApp",
     *         @OA\Schema(
     *             type="string",
     *             enum={"account_created", "iuran_reminder", "payment_confirmed", "lomba_reminder", "match_invitation"},
     *             example="account_created"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_active"},
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status template WhatsApp berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Template WhatsApp berhasil dinonaktifkan."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login (Unauthenticated)"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Template WhatsApp tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal (is_active wajib boolean)")
     * )
     */
    public function updateStatus(Request $request, string $key): JsonResponse
    {
        $template = $this->templates->findByKey($key);
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template WhatsApp tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $template->is_active = (bool) $validated['is_active'];
        $template->updated_by = $request->user()?->id_akun;
        $template->save();

        $template->refresh()->load('updater:id_akun,username');

        return response()->json([
            'success' => true,
            'message' => $template->is_active
                ? 'Template WhatsApp berhasil diaktifkan.'
                : 'Template WhatsApp berhasil dinonaktifkan.',
            'data' => $this->templates->formatTemplate($template),
        ]);
    }

    /**
     * Membuat preview pesan template WhatsApp menggunakan contoh data.
     *
     * @OA\Post(
     *     path="/api/admin/whatsapp-templates/{key}/preview",
     *     operationId="previewWhatsappTemplate",
     *     tags={"Manajemen - WhatsApp"},
     *     summary="Melihat preview template WhatsApp dengan contoh data",
     *     description="Merender pratinjau pesan menggunakan contoh data realistis tanpa mengirim WhatsApp sungguhan. Admin dapat mengirim draft title/body opsional untuk melihat hasil render sebelum disimpan. Placeholder yang tidak diizinkan untuk key terkait akan ditolak dengan 422.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Kunci unik template WhatsApp",
     *         @OA\Schema(
     *             type="string",
     *             enum={"account_created", "iuran_reminder", "payment_confirmed", "lomba_reminder", "match_invitation"},
     *             example="lomba_reminder"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", nullable=true, maxLength=150, example="Pengingat Peserta Lomba"),
     *             @OA\Property(property="body", type="string", nullable=true, maxLength=2000, example="Halo {nama}, jangan lupa lomba {nama_lomba} pada {tanggal} pukul {waktu} di {lokasi}.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preview template WhatsApp berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Preview template WhatsApp berhasil dibuat."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="key", type="string", example="lomba_reminder"),
     *                 @OA\Property(property="title", type="string", example="Pengingat Peserta Lomba"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="allowed_placeholders", type="array", @OA\Items(type="string"), example={"{nama}", "{nama_lomba}", "{tanggal}", "{waktu}", "{lokasi}", "{keterangan}"}),
     *                 @OA\Property(property="sample_data", type="object"),
     *                 @OA\Property(property="rendered_message", type="string", example="Halo Andi Pratama, jangan lupa lomba Tarik Tambang pada 17-08-2026 pukul 09:00 - 11:00 WIB di Lapangan RW 01.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login (Unauthenticated)"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Template WhatsApp tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal (placeholder tidak valid)")
     * )
     */
    public function preview(PreviewWhatsappTemplateRequest $request, string $key): JsonResponse
    {
        $validated = $request->validated();
        $preview = $this->templates->preview(
            $key,
            $validated['title'] ?? null,
            $validated['body'] ?? null
        );

        if (!$preview) {
            return response()->json([
                'success' => false,
                'message' => 'Template WhatsApp tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preview template WhatsApp berhasil dibuat.',
            'data' => $preview,
        ]);
    }
}
