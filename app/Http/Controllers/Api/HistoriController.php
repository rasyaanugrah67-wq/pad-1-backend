<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lomba;
use App\Models\Pemenang;
use App\Models\Periode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HistoriController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:1900,2200'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $data = Periode::withCount('lomba')
            ->when($validated['tahun'] ?? null, fn ($q, $tahun) =>
                $q->where('tahun', $tahun)
            )
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('nama_kegiatan', 'like', "%{$search}%")
                        ->orWhere('deskripsi', 'like', "%{$search}%");

                    if (is_numeric($search)) {
                        $nested->orWhere('tahun', $search);
                    }
                });
            })
            ->latest('tahun')
            ->latest('id_periode')
            ->get()
            ->map(fn ($periode) => $this->periodeData($periode));

        return $this->ok($data, 'Daftar histori berhasil diambil.');
    }

    public function show(int $id_periode, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150']
        ]);

        $periode = Periode::find($id_periode);

        if (!$periode) {
            return $this->error('Histori periode tidak ditemukan.', 404);
        }

        $lomba = $this->lombaQuery(
            $id_periode,
            $validated['search'] ?? null
        )->get();

        return $this->ok(
            array_merge($this->periodeData($periode), [
                'lomba' => $lomba->map(
                    fn ($item) => $this->lombaData($item)
                )->values(),
            ]),
            'Detail histori berhasil diambil.'
        );
    }

    public function update(Request $request, int $id_periode): JsonResponse
    {
        $periode = Periode::find($id_periode);

        if (!$periode) {
            return $this->error('Histori periode tidak ditemukan.', 404);
        }

        // Validasi data periode.
        $validator = Validator::make($request->all(), [
            'tahun' => ['sometimes', 'integer', 'between:1900,2200'],
            'nama_kegiatan' => ['sometimes', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'logo' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['Aktif', 'Selesai'])],
        ]);

        // Memastikan rentang tanggal tetap valid,
        // termasuk jika hanya satu tanggal yang diubah.
        $validator->after(function ($validator) use ($request, $periode) {
            if (
                $validator->errors()->has('tanggal_mulai')
                || $validator->errors()->has('tanggal_selesai')
            ) {
                return;
            }

            $tanggalMulai = $request->input(
                'tanggal_mulai',
                optional($periode->tanggal_mulai)->toDateString()
            );

            $tanggalSelesai = $request->input(
                'tanggal_selesai',
                optional($periode->tanggal_selesai)->toDateString()
            );

            if (
                $tanggalMulai
                && $tanggalSelesai
                && $tanggalSelesai < $tanggalMulai
            ) {
                $validator->errors()->add(
                    'tanggal_selesai',
                    'Tanggal selesai tidak boleh sebelum tanggal mulai.'
                );
            }
        });

        $validated = $validator->validate();

        $periode->update($validated);

        return $this->ok(
            $this->periodeData($periode->fresh()),
            'Informasi histori berhasil diperbarui.'
        );
    }

    public function storeWinner(
        Request $request,
        int $id_periode,
        int $id_lomba
    ): JsonResponse {
        $lomba = Lomba::where('id_periode', $id_periode)
            ->find($id_lomba);

        if (!$lomba) {
            return $this->error('Lomba tidak ditemukan pada periode tersebut.', 404);
        }

        $validated = $this->winnerValidation($request, null, $lomba->id_lomba);

        $winner = $this->saveWinner($lomba, $validated);

        return $this->ok(
            $this->winnerData($winner),
            'Pemenang berhasil ditambahkan.',
            201
        );
    }

    public function updateWinner(Request $request, int $id_pemenang): JsonResponse
    {
        $winner = Pemenang::with('lomba')->find($id_pemenang);

        if (!$winner) {
            return $this->error('Pemenang tidak ditemukan.', 404);
        }

        $validated = $this->winnerValidation(
            $request,
            $winner->id_pemenang,
            $winner->id_lomba
        );

        $winner->update(
            $this->winnerAttributes($winner->lomba, $validated, $winner)
        );

        return $this->ok(
            $this->winnerData(
                $winner->fresh(['lomba', 'pendaftaran.warga', 'tim'])
            ),
            'Pemenang berhasil diperbarui.'
        );
    }

    public function destroyWinner(int $id_pemenang): JsonResponse
    {
        $winner = Pemenang::find($id_pemenang);

        if (!$winner) {
            return $this->error('Pemenang tidak ditemukan.', 404);
        }

        $winner->delete();

        return $this->ok(null, 'Pemenang berhasil dihapus.');
    }

    private function lombaQuery(int $id_periode, ?string $search)
    {
        return Lomba::with([
            'kategori',
            'jadwal',
            'pemenang.pendaftaran.warga',
            'pemenang.tim'
        ])
            ->withCount([
                'pendaftaran as jumlah_peserta' => fn ($q) =>
                    $q->whereNotIn('status', ['Ditolak', 'Dibatalkan']),
                'timLomba as jumlah_regu',
            ])
            ->where('id_periode', $id_periode)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('nama_lomba', 'like', "%{$search}%")
                        ->orWhereHas('kategori', fn ($x) =>
                            $x->where('nama_kategori', 'like', "%{$search}%")
                        )
                        ->orWhereHas('jadwal', fn ($x) =>
                            $x->where('lokasi', 'like', "%{$search}%")
                        )
                        ->orWhereHas('pemenang', fn ($x) =>
                            $x->where('nama_pemenang', 'like', "%{$search}%")
                        );
                });
            })
            ->orderBy('nama_lomba');
    }

    private function winnerValidation(
        Request $request,
        ?int $ignore = null,
        ?int $idLomba = null
    ): array {
        $targetLomba = $idLomba ?? $request->route('id_lomba');
        $isUpdate = $ignore !== null;

        $unique = Rule::unique('pemenang', 'peringkat')
            ->where(fn ($q) => $q->where('id_lomba', $targetLomba));

        if ($ignore) {
            $unique->ignore($ignore, 'id_pemenang');
        }

        $ruleTipe = $isUpdate ? 'sometimes' : 'required';
        $rulePeringkat = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'tipe_pemenang' => [
                $ruleTipe,
                Rule::in(['Individu', 'Beregu'])
            ],
            'id_pendaftaran' => [
                'nullable',
                'integer',
                'exists:pendaftaran,id_pendaftaran'
            ],
            'id_tim' => [
                'nullable',
                'integer',
                'exists:tim_lomba,id_tim'
            ],
            'peringkat' => [
                $rulePeringkat,
                'integer',
                'between:1,255',
                $unique
            ],
            'nama_pemenang' => ['nullable', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string'],
        ]);
    }

    private function saveWinner(Lomba $lomba, array $validated): Pemenang
    {
        return Pemenang::create(
            $this->winnerAttributes($lomba, $validated)
        );
    }

    private function winnerAttributes(Lomba $lomba, array $data, ?Pemenang $existing = null): array
    {
        $tipePemenang = $data['tipe_pemenang'] ?? ($existing ? ($existing->id_tim ? 'Beregu' : 'Individu') : null);
        $isTeam = $tipePemenang === 'Beregu';

        $peringkat = array_key_exists('peringkat', $data)
            ? $data['peringkat']
            : $existing?->peringkat;

        $keterangan = array_key_exists('keterangan', $data)
            ? $data['keterangan']
            : $existing?->keterangan;

        // Pemenang beregu harus berasal dari tim
        // yang terdaftar pada lomba tersebut.
        if ($isTeam) {
            if (!$lomba->isBeregu()) {
                abort(422, 'Lomba ini tidak menggunakan pendaftaran beregu.');
            }

            $idTim = array_key_exists('id_tim', $data) ? $data['id_tim'] : $existing?->id_tim;
            $team = $lomba->timLomba()->find($idTim ?? 0);

            if (!$team) {
                abort(422, 'Regu pemenang tidak terdaftar pada lomba tersebut.');
            }

            $namaPemenang = array_key_exists('nama_pemenang', $data)
                ? ($data['nama_pemenang'] ?: $team->nama_tim)
                : ($existing?->nama_pemenang ?: $team->nama_tim);

            return [
                'id_lomba' => $lomba->id_lomba,
                'id_tim' => $team->id_tim,
                'id_pendaftaran' => null,
                'peringkat' => $peringkat,
                'nama_pemenang' => $namaPemenang,
                'keterangan' => $keterangan,
            ];
        }

        // Pemenang individu harus berasal dari peserta
        // yang terdaftar pada lomba tersebut.
        if (!$lomba->isIndividu()) {
            abort(422, 'Lomba ini tidak menggunakan pendaftaran individu.');
        }

        $idPendaftaran = array_key_exists('id_pendaftaran', $data) ? $data['id_pendaftaran'] : $existing?->id_pendaftaran;
        $registration = $lomba->pendaftaran()
            ->with('warga')
            ->where('status', 'Diterima')
            ->find($idPendaftaran ?? 0);

        if (!$registration) {
            abort(422, 'Peserta pemenang tidak terdaftar pada lomba tersebut.');
        }

        $name = array_key_exists('nama_pemenang', $data)
            ? ($data['nama_pemenang'] ?: ($registration->warga?->nama ?: 'Peserta'))
            : ($existing?->nama_pemenang ?: ($registration->warga?->nama ?: 'Peserta'));

        return [
            'id_lomba' => $lomba->id_lomba,
            'id_tim' => null,
            'id_pendaftaran' => $registration->id_pendaftaran,
            'peringkat' => $peringkat,
            'nama_pemenang' => $name,
            'keterangan' => $keterangan,
        ];
    }

    private function periodeData(Periode $periode): array
    {
        return [
            'id_periode' => $periode->id_periode,
            'tahun' => $periode->tahun,
            'judul' => $periode->nama_kegiatan,
            'nama_kegiatan' => $periode->nama_kegiatan,
            'deskripsi' => $periode->deskripsi,
            'tanggal_mulai' => optional($periode->tanggal_mulai)->toDateString(),
            'tanggal_selesai' => optional($periode->tanggal_selesai)->toDateString(),
            'status' => $periode->status,
            'logo' => $periode->logo,
            'jumlah_lomba' => (int) (
                $periode->lomba_count ?? $periode->lomba()->count()
            ),
        ];
    }

    private function lombaData(Lomba $lomba): array
    {
        return [
            'id_lomba' => $lomba->id_lomba,
            'nama_lomba' => $lomba->nama_lomba,
            'kategori' => $lomba->kategori?->nama_kategori,
            'jenis_pendaftaran' => $lomba->jenis_pendaftaran,
            'jumlah_peserta' => $lomba->isBeregu()
                ? (int) $lomba->jumlah_regu
                : (int) $lomba->jumlah_peserta,
            'jumlah_regu' => (int) $lomba->jumlah_regu,
            'lokasi' => $lomba->jadwal
                ->pluck('lokasi')
                ->filter()
                ->unique()
                ->values(),
            'pemenang' => $lomba->pemenang
                ->map(fn ($winner) => $this->winnerData($winner))
                ->values(),
        ];
    }

    private function winnerData(Pemenang $winner): array
    {
        return [
            'id_pemenang' => $winner->id_pemenang,
            'peringkat' => $winner->peringkat,
            'tipe_pemenang' => $winner->id_tim ? 'Beregu' : 'Individu',
            'nama_pemenang' => $winner->nama_pemenang,
            'id_pendaftaran' => $winner->id_pendaftaran,
            'id_tim' => $winner->id_tim,
            'keterangan' => $winner->keterangan,
        ];
    }

    private function ok($data, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $status);
    }
}
