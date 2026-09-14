---
title: Mengerjakan antrean help desk
summary: Untuk engineer dan lead engineer - ambil tiket, pindahkan statusnya, tanya pelapor, oper pekerjaan, hubungkan perbaikan ke deploy, minta QA memeriksa, dan pakai asisten AI sesuai aturannya.
date: 2026-09-14
source_date: 2026-09-14
tags: help desk, tickets, engineers
category: help-desk
audience: engineers
help_routes: grp.tickets.board
---

<aside class="tldr">
Engineer mengerjakan tiket dari <b>Dashboard</b> dan <b>Board</b> di <b>Tickets</b>. Ambil tiket, tekan <b>Start</b>, tanya pelapor kalau buntu, tulis nomor tiket di commit supaya deploy-nya terhubung, minta QA memeriksa, lalu tekan <b>Done</b>. Semua komentar bersifat publik: pelapor, dan pelanggan pada tiket AD, bisa membacanya. Lead engineer juga membagi pekerjaan, melihat laporan, menangani tiket rahasia, dan memutuskan catatan internal lama mana yang tetap disembunyikan.
</aside>

## Dashboard dan board

<b>Tickets → Dashboard</b> menampilkan <b>queue</b> berisi tiket terbuka yang belum ditugaskan (paling mendesak dan paling lama di atas), tiket yang ditugaskan kepada Anda, tiket waiting yang jatuh tempo dalam sehari, dan antrean QA.

<b>Tickets → Board</b> punya satu kolom per tahap: <b>Todo</b>, <b>Assigned</b>, <b>In progress</b>, <b>Waiting</b> dan <b>Closed</b>, tempat Done dan Cancelled berbagi kolom. Setiap kolom punya filter periode sendiri; tiket tertutup secara bawaan hanya dari 24 jam terakhir.

## Memindahkan tiket

- <b>Start</b> memindahkannya ke <b>In progress</b>.
- <b>Stop, back to assigned</b> menundanya; <b>Resume</b> melanjutkannya lagi.
- <b>Ask reporter</b> memindahkannya ke <b>Waiting</b> (lihat di bawah).
- <b>Done</b> menutupnya sebagai selesai dan memberi tahu pelapor; <b>Cancel</b> menutupnya tanpa perbaikan.
- <b>Reopen</b> membuka kembali tiket yang sudah ditutup.

Isi <b>priority</b>, <b>kind</b> (bug atau feature), <b>module</b> dan <b>tags</b> seiring Anda tahu lebih banyak. Tag bawaan menjelaskan kenapa sebuah tiket tidak butuh kode: <b>not a bug</b>, <b>lack of training</b>, <b>not enough info</b>, <b>duplicate</b>, <b>user error</b>, <b>data fix</b>, <b>wont fix</b>.

## Bertanya kepada pelapor

<b>Ask reporter</b> mengirim pertanyaan Anda dan menaruh tiket di <b>Waiting</b>. Pilih berapa lama waktu mereka: 2 jam, 1 hari, 2 hari, 3 hari atau 14 hari. Kalau tidak dipilih, staf mendapat 72 jam dan pelanggan 14 hari. Jika tidak ada yang menjawab, tiket dibatalkan otomatis dengan komentar "No reply for … days". Balasan apa pun dari pelapor mengembalikannya ke <b>Todo</b>.

## Menugaskan dan mengoper

Tiket yang belum ditugaskan dibagikan oleh lead engineer. Setelah sebuah tiket menjadi milik Anda, Anda bisa mengopernya ke rekan, tetapi tidak bisa melepas penugasannya atau mengambil tiket yang bukan milik Anda. Setiap perubahan meninggalkan komentar seperti "Assigned to …" atau "Passed from … to …", dan orang yang baru ditugaskan ikut masuk ke <b>Staff chat</b> tiket tersebut.

## Komentar

Semua komentar bersifat publik. Tulislah untuk pelapor dan, pada tiket AD, untuk pelanggan. Komentar disalin ke thread Slack tiket, dan balasan di thread kembali sebagai komentar. Anda bisa mengedit atau menghapus komentar Anda sendiri.

Catatan internal lama dari sebelum komentar menjadi publik tetap tersembunyi bagi semua orang kecuali lead engineer.

## Menghubungkan perbaikan ke deploy

Tulis nomor tiket di subjek commit, misalnya <b>HELP-3131</b> atau <b>AD-45</b>. Saat commit itu sampai ke production, aiku menambahkan komentar "Deployed to production (versi): hash subjek" dan mencantumkan commit di tiket, sehingga pelapor dan QA tahu kapan perbaikannya live.

## Meminta QA memeriksa

Setelah perbaikan live, tekan <b>Ask QA to check</b>. Tiket masuk ke antrean QA dan QA memberi keputusan: <b>QA passed</b>, atau <b>QA failed</b> dengan catatan apa yang masih salah. Anda diberi tahu untuk keduanya. Setelah gagal, perbaiki lalu tekan <b>Ask QA to check again</b>; <b>Withdraw QA request</b> membatalkan permintaan. Menandai tiket <b>Done</b> tetap keputusan Anda.

## Tiket pelanggan dan eskalasi

Tiket AD datang dari pelanggan lewat halaman dukungan toko mereka. Jika perlu pekerjaan engineering, tekan <b>Escalate to help desk</b>: ini membuat tiket HELP terhubung dengan kind escalation, dengan subjek, deskripsi dan prioritas yang sama.

## Tiket rahasia

Tiket rahasia hanya bisa dilihat oleh orang yang membuatnya dan oleh lead engineer. Ditugaskan pada tiket itu tidak memberi Anda akses.

## Memakai asisten AI

Alat tiket di asisten hanya tersedia untuk engineer, lead engineer dan QA. Asisten bertindak sebagai Anda, dengan izin Anda:

- Bisa menampilkan dan membaca tiket yang bisa Anda lihat, dan membuat tiket baru.
- Bisa mengubah tiket dengan cara yang sama seperti Anda di aiku.
- Hanya bisa berkomentar pada tiket yang sudah ditugaskan, dan hanya jika ditugaskan kepada Anda. Tugaskan tiketnya dulu.

## Untuk lead engineer

- <b>Tickets → Reports</b>: tiket dibuat dan selesai, median waktu penyelesaian, tiket terbuka paling lama, rating pelanggan dan tabel per orang, untuk 7, 30 atau 90 hari.
- Menugaskan tiket mana pun kepada siapa pun.
- Mencentang <b>Confidential</b> pada tiket yang harus tetap privat.
- Menghapus tiket; tiket dan komentarnya ikut terhapus.
- Setiap komentar punya <b>Hide</b> atau <b>Make public</b>. Catatan internal lama mulai dalam keadaan tersembunyi; baca dan jadikan publik yang aman untuk dibagikan. Sembunyikan komentar apa pun yang tidak boleh terlihat.

<aside class="wayfinder"><strong>Tempat klik di aiku</strong>
<ul>
<li><b>Mencari pekerjaan:</b> <b>Tickets</b> → <b>Dashboard</b>, atau <b>Tickets</b> → <b>Board</b>.</li>
<li><b>Mulai, tanya pelapor, selesai:</b> buka tiket → <b>Start</b>, <b>Ask reporter</b>, <b>Done</b>.</li>
<li><b>Mengoper tiket:</b> buka tiket Anda → ganti assignee.</li>
<li><b>Meminta QA:</b> buka tiket → <b>Ask QA to check</b>.</li>
<li><b>Eskalasi tiket pelanggan:</b> buka tiket AD → <b>Escalate to help desk</b>.</li>
<li><b>Laporan (lead engineer):</b> <b>Tickets</b> → <b>Reports</b>.</li>
<li><b>Menyembunyikan atau memublikasikan komentar (lead engineer):</b> buka tiket → <b>Hide</b> atau <b>Make public</b> pada komentar.</li>
</ul>
</aside>

<aside class="permissions"><strong>Izin yang Anda perlukan</strong>
Peran <b>Engineer</b> memungkinkan Anda mengerjakan dan menyelesaikan tiket, mengoper tiket milik sendiri, dan meminta QA memeriksa. Peran <b>Lead engineer</b> menambah kemampuan menugaskan tiket apa pun, tiket rahasia, laporan, menghapus tiket, serta menyembunyikan atau memublikasikan komentar. Peran berasal dari posisi kerja Anda.
</aside>
