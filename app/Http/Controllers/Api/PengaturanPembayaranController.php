<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengaturanPembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PengaturanPembayaranController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = PengaturanPembayaran::query()->first();

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan pembayaran berhasil diambil.',
            'data' => $this->data($settings),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_bank' => ['nullable', 'string', 'max:100'],
            'nomor_rekening' => ['nullable', 'string', 'max:50', 'regex:/^[0-9 .-]+$/'],
            'atas_nama' => ['nullable', 'string', 'max:150'],
            'qris_image' => ['sometimes', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $settings = PengaturanPembayaran::query()->first();
        $oldQris = $settings?->qris_image;
        $newQris = null;

        if ($request->hasFile('qris_image')) {
            $newQris = $request->file('qris_image')->store('pembayaran/qris', 'public');
            $validated['qris_image'] = $newQris;
        }

        try {
            if ($settings) {
                $settings->update($validated);
            } else {
                $settings = PengaturanPembayaran::create($validated);
            }
        } catch (\Throwable $e) {
            if ($newQris) Storage::disk('public')->delete($newQris);
            throw $e;
        }

        if ($newQris && $oldQris) {
            Storage::disk('public')->delete($oldQris);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan pembayaran berhasil diperbarui.',
            'data' => $this->data($settings->fresh()),
        ]);
    }

    private function data(?PengaturanPembayaran $settings): ?array
    {
        if (!$settings) return null;

        return [
            'id_pengaturan' => $settings->id_pengaturan,
            'nama_bank' => $settings->nama_bank,
            'nomor_rekening' => $settings->nomor_rekening,
            'atas_nama' => $settings->atas_nama,
            'qris_image' => $settings->qris_image
                ? Storage::disk('public')->url($settings->qris_image)
                : null,
        ];
    }
}
