<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Auth::user()->isMahasiswa()) {
            abort(403, 'Akses ditolak. Pengumpulan tugas hanya dapat dilakukan oleh Mahasiswa.');
        }

        $user = Auth::user();

        $enrollment = $assignment->course->students()->where('users.id', $user->id)->first();
        if (!$enrollment || $enrollment->pivot->status !== 'approved') {
            abort(403, 'Akses ditolak. Anda belum disetujui oleh Dosen untuk mengikuti mata kuliah tugas ini.');
        }

        if ($assignment->isOverdue()) {
            return back()->with('error', 'Gagal mengumpulkan: Batas waktu pengumpulan (deadline) tugas telah berakhir.');
        }

        $validated = $request->validate([
            'jawaban' => ['required', 'string', 'min:10', 'max:20000'],
        ], [
            'jawaban.required' => 'Teks jawaban tugas wajib diisi.',
            'jawaban.min' => 'Jawaban terlalu pendek. Tuliskan jawaban minimal 10 karakter.',
            'jawaban.max' => 'Jawaban melebihi batas maksimal 20.000 karakter.',
        ]);

        Submission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
            ],
            [
                'jawaban' => $validated['jawaban'],
                'submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Jawaban tugas Anda berhasil disimpan ke sistem.');
    }

    public function grade(Request $request, Submission $submission): RedirectResponse
    {
        // Hanya dosen pengampu mata kuliah terkait yang boleh memberi nilai
        if ($submission->assignment->course->dosen_id !== Auth::id()) {
            abort(403, 'Akses ditolak. Anda bukan dosen pengampu mata kuliah tugas ini.');
        }

        $validated = $request->validate([
            'nilai' => ['required', 'integer', 'min:0', 'max:100'],
            'catatan_dosen' => ['nullable', 'string', 'max:1000'],
        ], [
            'nilai.required' => 'Nilai wajib diisi.',
            'nilai.integer' => 'Nilai harus berupa angka bulat.',
            'nilai.min' => 'Nilai minimal 0.',
            'nilai.max' => 'Nilai maksimal 100.',
            'catatan_dosen.max' => 'Catatan dosen maksimal 1000 karakter.',
        ]);

        $submission->update($validated);

        return back()->with('success', "Nilai untuk {$submission->user->name} berhasil disimpan.");
    }

    public function exportCsv(Assignment $assignment): StreamedResponse
    {
        // Hanya dosen pengampu mata kuliah terkait yang boleh mengekspor nilai
        if ($assignment->course->dosen_id !== Auth::id()) {
            abort(403, 'Akses ditolak. Anda bukan dosen pengampu mata kuliah tugas ini.');
        }

        $assignment->load(['course.approvedStudents', 'submissions']);
        $students = $assignment->course->approvedStudents;
        $submissions = $assignment->submissions->keyBy('user_id');

        $filename = 'rekap_nilai_' . Str::slug($assignment->judul) . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($assignment, $students, $submissions) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membaca file sebagai UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['No', 'NIM', 'Nama Mahasiswa', 'Email', 'Status Pengumpulan', 'Waktu Pengumpulan', 'Nilai', 'Status Nilai', 'Catatan Dosen']);

            foreach ($students as $i => $student) {
                $sub = $submissions->get($student->id);

                fputcsv($out, [
                    $i + 1,
                    $student->nomor_induk ?? '-',
                    $student->name,
                    $student->email,
                    $sub ? 'Sudah Mengumpulkan' : 'Belum Mengumpulkan',
                    $sub && $sub->submitted_at ? $sub->submitted_at->format('Y-m-d H:i') : '-',
                    $sub && $sub->nilai !== null ? $sub->nilai : '-',
                    $sub ? $sub->status_nilai : '-',
                    $sub->catatan_dosen ?? '-',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
