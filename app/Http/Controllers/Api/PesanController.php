<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

class PesanController extends Controller
{
    /** @OA\Get(path="/api/manajemen/pesan", tags={"Manajemen Pesan"}, summary="Daftar pesan", security={{"sanctum":{}}}, @OA\Response(response=200, description="Berhasil")) */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['nullable', Rule::in(['Draft', 'Dipublikasi'])], 'search' => ['nullable', 'string', 'max:150'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $data = Pengumuman::with(['akun:id_akun,username', 'rt:id_rt,nomor_rt', 'rw:id_rw,nomor_rw'])
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['search'] ?? null, fn ($q, $v) => $q->where(fn ($x) => $x->where('judul', 'like', "%{$v}%")->orWhere('isi', 'like', "%{$v}%")))
            ->latest('id_pengumuman')->paginate($validated['per_page'] ?? 15);
        return response()->json(['success' => true, 'message' => 'Daftar pesan berhasil diambil.', 'data' => $data]);
    }

    /** @OA\Get(path="/api/manajemen/pesan/{id}", tags={"Manajemen Pesan"}, summary="Detail pesan", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Berhasil")) */
    public function show(int $id): JsonResponse
    {
        $pesan = Pengumuman::with(['akun:id_akun,username', 'periode', 'rt', 'rw'])->find($id);
        if (!$pesan) return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan.'], 404);
        return response()->json(['success' => true, 'message' => 'Detail pesan berhasil diambil.', 'data' => $pesan]);
    }

    /** @OA\Post(path="/api/manajemen/pesan", tags={"Manajemen Pesan"}, summary="Membuat pesan", security={{"sanctum":{}}}, @OA\Response(response=201, description="Berhasil")) */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['id_akun'] = $request->user()->id_akun;
        $data['id_periode'] = $data['id_periode'] ?? DB::table('periode')->where('status', 'Aktif')->value('id_periode') ?? DB::table('periode')->latest('tahun')->value('id_periode');
        if (!$data['id_periode']) return response()->json(['success' => false, 'message' => 'Periode belum tersedia.'], 422);
        $data['tanggal_publikasi'] = ($data['status'] ?? 'Draft') === 'Dipublikasi' ? now() : null;
        $pesan = Pengumuman::create($data);
        if ($pesan->status === 'Dipublikasi') $this->publish($pesan);
        return response()->json(['success' => true, 'message' => $pesan->status === 'Draft' ? 'Pesan berhasil disimpan sebagai draft.' : 'Pesan berhasil dikirim.', 'data' => $pesan->fresh()], 201);
    }

    /** @OA\Put(path="/api/manajemen/pesan/{id}", tags={"Manajemen Pesan"}, summary="Mengedit draft", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Berhasil")) */
    public function update(Request $request, int $id): JsonResponse
    {
        $pesan = Pengumuman::find($id);
        if (!$pesan) return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan.'], 404);
        if ($pesan->status !== 'Draft') return response()->json(['success' => false, 'message' => 'Pesan yang sudah terkirim tidak dapat diedit.'], 422);
        $pesan->update($this->validated($request, true));
        return response()->json(['success' => true, 'message' => 'Draft berhasil diperbarui.', 'data' => $pesan->fresh()]);
    }

    /** @OA\Delete(path="/api/manajemen/pesan/{id}", tags={"Manajemen Pesan"}, summary="Menghapus draft", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Berhasil")) */
    public function destroy(int $id): JsonResponse
    {
        $pesan = Pengumuman::find($id);
        if (!$pesan) return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan.'], 404);
        if ($pesan->status !== 'Draft') return response()->json(['success' => false, 'message' => 'Pesan yang sudah terkirim tidak dapat dihapus.'], 422);
        $pesan->delete();
        return response()->json(['success' => true, 'message' => 'Draft berhasil dihapus.']);
    }

    /** @OA\Patch(path="/api/manajemen/pesan/{id}/kirim", tags={"Manajemen Pesan"}, summary="Mengirim pesan", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Berhasil")) */
    public function kirim(int $id): JsonResponse
    {
        $pesan = Pengumuman::find($id);
        if (!$pesan) return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan.'], 404);
        if ($pesan->status === 'Dipublikasi') return response()->json(['success' => false, 'message' => 'Pesan sudah terkirim.'], 422);
        DB::transaction(function () use ($pesan) { $pesan->update(['status' => 'Dipublikasi', 'tanggal_publikasi' => now()]); $this->publish($pesan->fresh()); });
        return response()->json(['success' => true, 'message' => 'Pesan berhasil dikirim.', 'data' => $pesan->fresh()]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rules = ['judul' => [$partial ? 'sometimes' : 'required', 'string', 'max:150'], 'isi' => [$partial ? 'sometimes' : 'required', 'string'], 'status' => ['sometimes', Rule::in(['Draft', 'Dipublikasi'])], 'id_periode' => ['nullable', 'integer', 'exists:periode,id_periode'], 'target_penerima' => ['sometimes', Rule::in(['Semua', 'RT', 'RW'])], 'id_rt' => ['nullable', 'integer', 'exists:rt,id_rt'], 'id_rw' => ['nullable', 'integer', 'exists:rw,id_rw']];
        $data = $request->validate($rules);
        $target = $data['target_penerima'] ?? 'Semua';
        if ($target === 'RT' && empty($data['id_rt'])) abort(response()->json(['success' => false, 'message' => 'id_rt wajib diisi untuk target RT.'], 422));
        if ($target === 'RW' && empty($data['id_rw'])) abort(response()->json(['success' => false, 'message' => 'id_rw wajib diisi untuk target RW.'], 422));
        return $data;
    }

    private function publish(Pengumuman $pesan): void
    {
        $warga = DB::table('warga')->where('status', 'Aktif')
            ->when($pesan->target_penerima === 'RT', fn ($q) => $q->where('id_rt', $pesan->id_rt))
            ->when($pesan->target_penerima === 'RW', fn ($q) => $q->whereIn('id_rt', DB::table('rt')->where('id_rw', $pesan->id_rw)->pluck('id_rt')))
            ->pluck('id_warga');
        foreach ($warga as $idWarga) {
            Notifikasi::firstOrCreate(['id_pengumuman' => $pesan->id_pengumuman, 'id_warga' => $idWarga], ['judul' => $pesan->judul, 'pesan' => $pesan->isi, 'tipe' => 'Umum']);
        }
    }
}
