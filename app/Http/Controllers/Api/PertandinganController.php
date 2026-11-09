<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PertandinganController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    /**
     * @OA\Get(
     *     path="/api/pertandingan",
     *     operationId="listPertandingan",
     *     tags={"Admin dan Panitia - Pertandingan"},
     *     summary="Melihat daftar pertandingan pada periode aktif",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"Dijadwalkan","Berlangsung","Selesai"})),
     *     @OA\Parameter(name="id_lomba", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tanggal", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Daftar pertandingan berhasil diambil"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['Dijadwalkan', 'Berlangsung', 'Selesai'])],
            'id_lomba' => ['nullable', 'integer', 'exists:lomba,id_lomba'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $periodeAktif = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->orderByDesc('id_periode')
            ->first();

        if (!$periodeAktif) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada periode kegiatan aktif.',
                'data' => [],
            ]);
        }

        $query = DB::table('pertandingan as p')
            ->join('lomba as l', 'l.id_lomba', '=', 'p.id_lomba')
            ->leftJoin('jadwal_lomba as j', 'j.id_jadwal', '=', 'p.id_jadwal')
            ->where('l.id_periode', $periodeAktif->id_periode)
            ->where('l.status', '!=', 'Nonaktif');

        if (!empty($validated['status'])) {
            $query->where('p.status', $validated['status']);
        }

        if (!empty($validated['id_lomba'])) {
            $query->where('p.id_lomba', $validated['id_lomba']);
        }

        if (!empty($validated['tanggal'])) {
            $query->whereDate('p.waktu_mulai', $validated['tanggal']);
        }

        $pertandingan = $query
            ->orderByDesc('p.waktu_mulai')
            ->orderByDesc('p.id_pertandingan')
            ->select(
                'p.id_pertandingan',
                'p.id_lomba',
                'p.id_jadwal',
                'l.nama_lomba',
                'j.lokasi',
                'p.peserta_1',
                'p.peserta_2',
                'p.babak',
                'p.waktu_mulai',
                'p.waktu_selesai',
                'p.status'
            )
            ->paginate(20);

        $pertandingan->getCollection()->transform(function ($item) {
            return $this->formatPertandinganRow($item);
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar pertandingan berhasil diambil.',
            'data' => $pertandingan,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/pertandingan/options",
     *     operationId="getPertandinganFormOptions",
     *     tags={"Admin dan Panitia - Pertandingan"},
     *     summary="Mengambil pilihan lomba dan jadwal untuk form pertandingan",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Pilihan form berhasil diambil"),
     *     @OA\Response(response=401, description="Belum login atau token tidak valid"),
     *     @OA\Response(response=403, description="Hanya Admin dan Panitia yang dapat mengakses endpoint ini")
     * )
     */
    public function options(): JsonResponse
    {
        $periodeAktif = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->orderByDesc('id_periode')
            ->first();

        if (!$periodeAktif) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada periode kegiatan aktif.',
                'data' => [
                    'lomba' => [],
                    'jadwal' => [],
                ],
            ]);
        }

        $lombaIds = DB::table('lomba')
            ->where('id_periode', $periodeAktif->id_periode)
            ->where('status', '!=', 'Nonaktif')
            ->orderBy('nama_lomba')
            ->pluck('id_lomba');

        $lomba = DB::table('lomba')
            ->whereIn('id_lomba', $lombaIds)
            ->select('id_lomba', 'nama_lomba', 'jenis_pendaftaran')
            ->orderBy('nama_lomba')
            ->get();

        $jadwal = DB::table('jadwal_lomba as j')
            ->join('lomba as l', 'l.id_lomba', '=', 'j.id_lomba')
            ->whereIn('j.id_lomba', $lombaIds)
            ->orderBy('l.nama_lomba')
            ->orderBy('j.tanggal')
            ->orderBy('j.waktu_mulai')
            ->select(
                'j.id_jadwal',
                'j.id_lomba',
                'l.nama_lomba',
                'j.tanggal',
                'j.waktu_mulai',
                'j.waktu_selesai',
                'j.lokasi'
            )
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Pilihan form pertandingan berhasil diambil.',
            'data' => [
                'lomba' => $lomba,
                'jadwal' => $jadwal,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/pertandingan",
     *     operationId="createPertandingan",
     *     tags={"Admin dan Panitia - Pertandingan"},
     *     summary="Membuat jadwal pertandingan",
     *     description="Membuat pertandingan pada lomba dalam periode aktif. Jadwal dan lokasi lomba dapat dihubungkan melalui id_jadwal.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id_lomba","peserta_1","peserta_2","tanggal","waktu_mulai"},
     *             @OA\Property(property="id_lomba", type="integer", example=1),
     *             @OA\Property(property="id_jadwal", type="integer", nullable=true, example=4),
     *             @OA\Property(property="peserta_1", type="string", example="Ketoprak Sedap Malam"),
     *             @OA\Property(property="peserta_2", type="string", example="Nasi Padang Murah"),
     *             @OA\Property(property="babak", type="string", nullable=true, example="Penyisihan"),
     *             @OA\Property(property="tanggal", type="string", format="date", example="2026-11-09"),
     *             @OA\Property(property="waktu_mulai", type="string", example="15:00"),
     *             @OA\Property(property="waktu_selesai", type="string", nullable=true, example="16:00"),
     *             @OA\Property(property="status", type="string", enum={"Dijadwalkan","Berlangsung","Selesai"}, example="Dijadwalkan")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Pertandingan berhasil dibuat"),
     *     @OA\Response(response=401, description="Belum login atau token tidak valid"),
     *     @OA\Response(response=403, description="Hanya Admin dan Panitia yang dapat mengakses endpoint ini"),
     *     @OA\Response(response=422, description="Data tidak valid atau lomba bukan bagian dari periode aktif")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_lomba' => ['required', 'integer', 'exists:lomba,id_lomba'],
            'id_jadwal' => ['nullable', 'integer', 'exists:jadwal_lomba,id_jadwal'],
            'peserta_1' => ['required', 'string', 'max:150'],
            'peserta_2' => ['required', 'string', 'max:150', 'different:peserta_1'],
            'babak' => ['nullable', 'string', 'max:50'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['nullable', 'date_format:H:i', 'after:waktu_mulai'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['Dijadwalkan', 'Berlangsung', 'Selesai']),
            ],
        ]);

        $validator->after(function ($validator) use ($request) {
            $periodeAktif = DB::table('periode')
                ->where('status', 'Aktif')
                ->orderByDesc('tahun')
                ->orderByDesc('id_periode')
                ->first();

            if (!$periodeAktif) {
                $validator->errors()->add('id_lomba', 'Belum ada periode kegiatan yang aktif.');
                return;
            }

            if ($request->filled('id_lomba')) {
                $lombaAktif = DB::table('lomba')
                    ->where('id_lomba', $request->input('id_lomba'))
                    ->where('id_periode', $periodeAktif->id_periode)
                    ->where('status', '!=', 'Nonaktif')
                    ->exists();

                if (!$lombaAktif) {
                    $validator->errors()->add(
                        'id_lomba',
                        'Lomba harus berasal dari periode aktif dan tidak berstatus Nonaktif.'
                    );
                }
            }

            if ($request->filled('id_jadwal') && $request->filled('id_lomba')) {
                $jadwalMilikLomba = DB::table('jadwal_lomba')
                    ->where('id_jadwal', $request->input('id_jadwal'))
                    ->where('id_lomba', $request->input('id_lomba'))
                    ->exists();

                if (!$jadwalMilikLomba) {
                    $validator->errors()->add(
                        'id_jadwal',
                        'Jadwal yang dipilih harus terkait dengan lomba yang dipilih.'
                    );
                }
            }
        });

        $validated = $validator->validate();

        $now = CarbonImmutable::now(self::TIMEZONE);
        $waktuMulai = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $validated['tanggal'].' '.$validated['waktu_mulai'],
            self::TIMEZONE
        );
        $waktuSelesai = !empty($validated['waktu_selesai'])
            ? CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                $validated['tanggal'].' '.$validated['waktu_selesai'],
                self::TIMEZONE
            )
            : null;

        $idPertandingan = DB::transaction(function () use (
            $validated,
            $waktuMulai,
            $waktuSelesai,
            $now
        ) {
            return DB::table('pertandingan')->insertGetId([
                'id_lomba' => $validated['id_lomba'],
                'id_jadwal' => $validated['id_jadwal'] ?? null,
                'peserta_1' => trim($validated['peserta_1']),
                'peserta_2' => trim($validated['peserta_2']),
                'babak' => $validated['babak'] ?? null,
                'waktu_mulai' => $waktuMulai->format('Y-m-d H:i:s'),
                'waktu_selesai' => $waktuSelesai?->format('Y-m-d H:i:s'),
                'status' => $validated['status'] ?? 'Dijadwalkan',
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);
        });

        $pertandingan = DB::table('pertandingan as p')
            ->join('lomba as l', 'l.id_lomba', '=', 'p.id_lomba')
            ->leftJoin('jadwal_lomba as j', 'j.id_jadwal', '=', 'p.id_jadwal')
            ->where('p.id_pertandingan', $idPertandingan)
            ->select(
                'p.id_pertandingan',
                'p.id_lomba',
                'p.id_jadwal',
                'l.nama_lomba',
                'j.lokasi',
                'p.peserta_1',
                'p.peserta_2',
                'p.babak',
                'p.waktu_mulai',
                'p.waktu_selesai',
                'p.status'
            )
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil dibuat.',
            'data' => [
                'id_pertandingan' => $pertandingan->id_pertandingan,
                'id_lomba' => $pertandingan->id_lomba,
                'id_jadwal' => $pertandingan->id_jadwal,
                'nama_lomba' => $pertandingan->nama_lomba,
                'lokasi' => $pertandingan->lokasi,
                'peserta_1' => $pertandingan->peserta_1,
                'peserta_2' => $pertandingan->peserta_2,
                'babak' => $pertandingan->babak,
                'tanggal' => CarbonImmutable::parse($pertandingan->waktu_mulai, self::TIMEZONE)->format('Y-m-d'),
                'waktu_mulai' => CarbonImmutable::parse($pertandingan->waktu_mulai, self::TIMEZONE)->format('H:i:s'),
                'waktu_selesai' => $pertandingan->waktu_selesai
                    ? CarbonImmutable::parse($pertandingan->waktu_selesai, self::TIMEZONE)->format('H:i:s')
                    : null,
                'status' => $pertandingan->status,
            ],
        ], 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/pertandingan/{id}",
     *     operationId="updatePertandingan",
     *     tags={"Admin dan Panitia - Pertandingan"},
     *     summary="Mengubah data pertandingan",
     *     description="Hanya dapat diakses Admin dan Panitia. Semua field body bersifat opsional; kirim field yang ingin diubah.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_lomba", type="integer", example=1),
     *             @OA\Property(property="id_jadwal", type="integer", nullable=true, example=4),
     *             @OA\Property(property="peserta_1", type="string", example="Ketoprak Sedap Malam"),
     *             @OA\Property(property="peserta_2", type="string", example="Nasi Padang Murah"),
     *             @OA\Property(property="babak", type="string", nullable=true, example="Semifinal"),
     *             @OA\Property(property="tanggal", type="string", format="date", example="2026-11-09"),
     *             @OA\Property(property="waktu_mulai", type="string", example="15:00"),
     *             @OA\Property(property="waktu_selesai", type="string", nullable=true, example="16:00"),
     *             @OA\Property(property="status", type="string", enum={"Dijadwalkan","Berlangsung","Selesai"}, example="Selesai")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Pertandingan berhasil diperbarui"),
     *     @OA\Response(response=401, description="Belum login atau token tidak valid"),
     *     @OA\Response(response=403, description="Hanya Admin dan Panitia yang dapat mengakses endpoint ini"),
     *     @OA\Response(response=404, description="Pertandingan tidak ditemukan"),
     *     @OA\Response(response=422, description="Data tidak valid")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $existing = DB::table('pertandingan')
            ->where('id_pertandingan', $id)
            ->first();

        if (!$existing) {
            return response()->json([
                'success' => false,
                'message' => 'Pertandingan tidak ditemukan.',
            ], 404);
        }

        $waktuMulaiLama = CarbonImmutable::parse($existing->waktu_mulai, self::TIMEZONE);
        $waktuSelesaiLama = $existing->waktu_selesai
            ? CarbonImmutable::parse($existing->waktu_selesai, self::TIMEZONE)
            : null;

        $validator = Validator::make($request->all(), [
            'id_lomba' => ['sometimes', 'required', 'integer', 'exists:lomba,id_lomba'],
            'id_jadwal' => ['sometimes', 'nullable', 'integer', 'exists:jadwal_lomba,id_jadwal'],
            'peserta_1' => ['sometimes', 'required', 'string', 'max:150'],
            'peserta_2' => ['sometimes', 'required', 'string', 'max:150', 'different:peserta_1'],
            'babak' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tanggal' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'waktu_mulai' => ['sometimes', 'required', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'nullable', 'date_format:H:i'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['Dijadwalkan', 'Berlangsung', 'Selesai']),
            ],
        ]);

        $idLombaAkhir = $request->input('id_lomba', $existing->id_lomba);
        $idJadwalAkhir = $request->exists('id_jadwal')
            ? $request->input('id_jadwal')
            : $existing->id_jadwal;
        $periodeAktif = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->orderByDesc('id_periode')
            ->first();

        $validator->after(function ($validator) use (
            $idLombaAkhir,
            $idJadwalAkhir,
            $periodeAktif,
            $request,
            $waktuMulaiLama,
            $waktuSelesaiLama,
            $existing
        ) {
            if (!$periodeAktif) {
                $validator->errors()->add('id_lomba', 'Belum ada periode kegiatan yang aktif.');
                return;
            }

            $lombaAktif = DB::table('lomba')
                ->where('id_lomba', $idLombaAkhir)
                ->where('id_periode', $periodeAktif->id_periode)
                ->where('status', '!=', 'Nonaktif')
                ->exists();

            if (!$lombaAktif) {
                $validator->errors()->add(
                    'id_lomba',
                    'Lomba harus berasal dari periode aktif dan tidak berstatus Nonaktif.'
                );
            }

            if ($idJadwalAkhir !== null) {
                $jadwalMilikLomba = DB::table('jadwal_lomba')
                    ->where('id_jadwal', $idJadwalAkhir)
                    ->where('id_lomba', $idLombaAkhir)
                    ->exists();

                if (!$jadwalMilikLomba) {
                    $validator->errors()->add(
                        'id_jadwal',
                        'Jadwal yang dipilih harus terkait dengan lomba yang dipilih.'
                    );
                }
            }

            $waktuMulai = $request->input('waktu_mulai', $waktuMulaiLama->format('H:i'));
            $waktuSelesai = $request->exists('waktu_selesai')
                ? $request->input('waktu_selesai')
                : $waktuSelesaiLama?->format('H:i');

            if (
                $waktuSelesai
                && preg_match('/^\d{2}:\d{2}$/', $waktuMulai)
                && preg_match('/^\d{2}:\d{2}$/', $waktuSelesai)
                && $waktuSelesai <= $waktuMulai
            ) {
                $validator->errors()->add('waktu_selesai', 'Waktu selesai harus setelah waktu mulai pada tanggal yang sama.');
            }

            $peserta1 = trim($request->input('peserta_1', $existing->peserta_1));
            $peserta2 = trim($request->input('peserta_2', $existing->peserta_2));
            if ($peserta1 === $peserta2) {
                $validator->errors()->add('peserta_2', 'Peserta 1 dan peserta 2 harus berbeda.');
            }
        });

        $validated = $validator->validate();

        $tanggal = $validated['tanggal'] ?? $waktuMulaiLama->format('Y-m-d');
        $waktuMulai = $validated['waktu_mulai'] ?? $waktuMulaiLama->format('H:i');
        $mulaiBaru = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $tanggal.' '.$waktuMulai,
            self::TIMEZONE
        );

        if (array_key_exists('waktu_selesai', $validated)) {
            $selesaiInput = $validated['waktu_selesai'];
        } elseif (array_key_exists('tanggal', $validated) && $waktuSelesaiLama) {
            $selesaiInput = $waktuSelesaiLama->format('H:i');
        } else {
            $selesaiInput = $waktuSelesaiLama?->format('H:i');
        }

        $selesaiBaru = $selesaiInput
            ? CarbonImmutable::createFromFormat('Y-m-d H:i', $tanggal.' '.$selesaiInput, self::TIMEZONE)
            : null;

        DB::table('pertandingan')
            ->where('id_pertandingan', $id)
            ->update([
                'id_lomba' => $validated['id_lomba'] ?? $existing->id_lomba,
                'id_jadwal' => array_key_exists('id_jadwal', $validated)
                    ? $validated['id_jadwal']
                    : $existing->id_jadwal,
                'peserta_1' => isset($validated['peserta_1']) ? trim($validated['peserta_1']) : $existing->peserta_1,
                'peserta_2' => isset($validated['peserta_2']) ? trim($validated['peserta_2']) : $existing->peserta_2,
                'babak' => array_key_exists('babak', $validated) ? $validated['babak'] : $existing->babak,
                'waktu_mulai' => $mulaiBaru->format('Y-m-d H:i:s'),
                'waktu_selesai' => $selesaiBaru?->format('Y-m-d H:i:s'),
                'status' => $validated['status'] ?? $existing->status,
                'updated_at' => CarbonImmutable::now(self::TIMEZONE)->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil diperbarui.',
            'data' => $this->formatPertandingan($id),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/pertandingan/{id}",
     *     operationId="deletePertandingan",
     *     tags={"Admin dan Panitia - Pertandingan"},
     *     summary="Menghapus pertandingan",
     *     description="Hanya dapat diakses Admin dan Panitia. Penghapusan bersifat permanen.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Pertandingan berhasil dihapus"),
     *     @OA\Response(response=401, description="Belum login atau token tidak valid"),
     *     @OA\Response(response=403, description="Hanya Admin dan Panitia yang dapat mengakses endpoint ini"),
     *     @OA\Response(response=404, description="Pertandingan tidak ditemukan")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::table('pertandingan')
            ->where('id_pertandingan', $id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Pertandingan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil dihapus.',
        ]);
    }

    private function formatPertandingan(int $id): array
    {
        $pertandingan = DB::table('pertandingan as p')
            ->join('lomba as l', 'l.id_lomba', '=', 'p.id_lomba')
            ->leftJoin('jadwal_lomba as j', 'j.id_jadwal', '=', 'p.id_jadwal')
            ->where('p.id_pertandingan', $id)
            ->select(
                'p.id_pertandingan',
                'p.id_lomba',
                'p.id_jadwal',
                'l.nama_lomba',
                'j.lokasi',
                'p.peserta_1',
                'p.peserta_2',
                'p.babak',
                'p.waktu_mulai',
                'p.waktu_selesai',
                'p.status'
            )
            ->first();

        return $this->formatPertandinganRow($pertandingan);
    }

    private function formatPertandinganRow(object $pertandingan): array
    {
        return [
            'id_pertandingan' => $pertandingan->id_pertandingan,
            'id_lomba' => $pertandingan->id_lomba,
            'id_jadwal' => $pertandingan->id_jadwal,
            'nama_lomba' => $pertandingan->nama_lomba,
            'lokasi' => $pertandingan->lokasi,
            'peserta_1' => $pertandingan->peserta_1,
            'peserta_2' => $pertandingan->peserta_2,
            'babak' => $pertandingan->babak,
            'tanggal' => CarbonImmutable::parse($pertandingan->waktu_mulai, self::TIMEZONE)->format('Y-m-d'),
            'waktu_mulai' => CarbonImmutable::parse($pertandingan->waktu_mulai, self::TIMEZONE)->format('H:i:s'),
            'waktu_selesai' => $pertandingan->waktu_selesai
                ? CarbonImmutable::parse($pertandingan->waktu_selesai, self::TIMEZONE)->format('H:i:s')
                : null,
            'status' => $pertandingan->status,
        ];
    }
}
