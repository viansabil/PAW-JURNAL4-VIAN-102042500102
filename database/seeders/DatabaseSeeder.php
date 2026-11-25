<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\MataKuliah;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Dosen Pengampu Default
        $dosen = User::updateOrCreate(
            ['email' => 'dosen@celoe.test'],
            [
                'name' => 'Dr. Ir. Budi Santoso, M.T.',
                'password' => Hash::make('dosen123'),
                'role' => 'dosen',
                'nomor_induk' => '198501012015041001',
            ]
        );

        // 2. Akun Mahasiswa Default
        $mahasiswa1 = User::updateOrCreate(
            ['email' => 'mahasiswa@celoe.test'],
            [
                'name' => 'Ahmad Pratama',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210001',
            ]
        );

        // 3. Mata Kuliah Dasar (Modul 3)
        $course1 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF301'],
            [
                'nama_mk' => 'Pengembangan Aplikasi Website',
                'deskripsi' => 'Mata kuliah yang membahas arsitektur aplikasi web modern menggunakan framework Laravel, MVC, Eloquent ORM, dan keamanan web.',
                'sks' => 4,
                'semester' => 'Ganjil 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        // 4. Daftarkan Mahasiswa ke Mata Kuliah Pertama (status approved)
        $course1->students()->syncWithoutDetaching([
            $mahasiswa1->id => ['status' => 'approved'],
        ]);

        // 5. Tambahkan Materi Contoh
        Material::updateOrCreate(
            ['course_id' => $course1->id, 'minggu_ke' => 1, 'urutan' => 1],
            [
                'judul' => 'Pengenalan Arsitektur MVC & Routing di Laravel 12',
                'konten' => 'Mempelajari alur HTTP Request, Route Middleware, Controller, dan pembuatan View menggunakan Blade Template.',
            ]
        );

        // 6. Mahasiswa Tambahan
        $mahasiswa2 = User::updateOrCreate(
            ['email' => 'siti@celoe.test'],
            [
                'name' => 'Siti Rahmawati',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210002',
            ]
        );

        // 7. Mata Kuliah Tambahan dengan Semester Berbeda
        $course2 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF204'],
            [
                'nama_mk' => 'Basis Data',
                'deskripsi' => 'Konsep basis data relasional, perancangan ERD, normalisasi, dan penggunaan SQL.',
                'sks' => 3,
                'semester' => 'Ganjil 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        $course3 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF402'],
            [
                'nama_mk' => 'Rekayasa Perangkat Lunak',
                'deskripsi' => 'Siklus hidup pengembangan perangkat lunak, analisis kebutuhan, dan pemodelan sistem.',
                'sks' => 2,
                'semester' => 'Genap 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        // 8. Daftarkan mahasiswa ke mata kuliah (status approved)
        $course1->students()->syncWithoutDetaching([
            $mahasiswa2->id => ['status' => 'approved'],
        ]);
        $course2->students()->syncWithoutDetaching([
            $mahasiswa1->id => ['status' => 'approved'],
            $mahasiswa2->id => ['status' => 'approved'],
        ]);

        // 9. Tugas Contoh
        $tugas1 = Assignment::updateOrCreate(
            ['course_id' => $course1->id, 'judul' => 'Tugas 1: Routing dan Middleware Laravel'],
            [
                'deskripsi' => 'Buatlah route terproteksi menggunakan middleware auth dan role, lalu jelaskan alur kerjanya.',
                'deadline' => now()->addDays(14),
            ]
        );

        Assignment::updateOrCreate(
            ['course_id' => $course2->id, 'judul' => 'Tugas 1: Perancangan ERD'],
            [
                'deskripsi' => 'Rancang ERD untuk sistem perpustakaan lengkap dengan relasi dan kardinalitasnya.',
                'deadline' => now()->addDays(10),
            ]
        );

        // 10. Contoh Pengumpulan Tugas (1 sudah dinilai, 1 belum dinilai)
        Submission::updateOrCreate(
            ['assignment_id' => $tugas1->id, 'user_id' => $mahasiswa1->id],
            [
                'jawaban' => 'Route dibungkus middleware auth agar hanya pengguna login yang bisa mengakses, lalu middleware role membatasi akses berdasarkan peran dosen atau mahasiswa.',
                'nilai' => 88,
                'catatan_dosen' => 'Penjelasan sudah jelas dan tepat. Tambahkan contoh kode pada tugas berikutnya.',
                'submitted_at' => now()->subDay(),
            ]
        );

        Submission::updateOrCreate(
            ['assignment_id' => $tugas1->id, 'user_id' => $mahasiswa2->id],
            [
                'jawaban' => 'Middleware berfungsi sebagai filter request sebelum masuk ke controller, misalnya mengecek status login pengguna.',
                'nilai' => null,
                'catatan_dosen' => null,
                'submitted_at' => now()->subHours(3),
            ]
        );
    }
}
