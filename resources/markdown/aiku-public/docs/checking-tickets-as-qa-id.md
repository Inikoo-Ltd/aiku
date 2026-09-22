---
title: Memeriksa tiket sebagai QA
summary: Untuk QA - temukan tiket yang menunggu pemeriksaan, uji perbaikannya di halaman live, beri keputusan lolos atau gagal yang jelas, dan pahami apa yang terjadi selanjutnya.
date: 2026-09-14
source_date: 2026-09-21
tags: help desk, tickets, qa
category: help-desk
audience: qa
---

<aside class="tldr">
Saat perbaikan dari engineer sudah live, mereka menekan <b>Ask QA to check</b>, biasanya meninggalkan catatan pada tiket tentang apa yang perlu diperiksa, dan tiket muncul di <b>QA queue</b> Anda - ditujukan kepada Anda dengan nama, atau kepada siapa pun di QA. Buka tiketnya, periksa perbaikan di tempat pelapor mengalami masalah, lalu beri keputusan: <b>Pass</b> dengan catatan apa yang Anda periksa, atau <b>Fail</b> dengan catatan apa yang masih salah. Engineer langsung diberi tahu. Anda yang memeriksa; engineer yang memutuskan kapan tiket <b>Done</b>.
</aside>

## Mencari tiket untuk diperiksa

Buka <b>Tickets → Dashboard</b>. <b>QA queue</b> menampilkan semua tiket dengan <b>QA check requested</b>, dari permintaan paling lama.

## Apa yang diperiksa

- Baca subjek, detail dan komentar supaya Anda tahu apa yang dilihat pelapor dan apa yang diubah.
- Buka <b>page where it happens</b> dari tiket dan ulangi apa yang dilakukan pelapor.
- Cari komentar "Deployed to production (versi) …". Kalau tidak ada, perbaikannya mungkin belum live.
- Periksa kasus dari tiket, lalu kasus sekitar yang jelas: toko lain, pelanggan lain, nilai kosong.

## Memberi keputusan

- <b>Pass</b>: tulis apa yang Anda periksa, lalu konfirmasi. Tiket menampilkan <b>QA passed</b>.
- <b>Fail</b>: tulis apa yang masih salah dan cara melihatnya. Tombol <b>Fail</b> tetap nonaktif sampai Anda mengisi catatan. Tiket menampilkan <b>QA failed</b>.

Setiap keputusan menambahkan komentar di tiket ("QA passed", atau "QA failed:" dengan catatan Anda), mengirim pesan ke thread Slack tiket, dan memberi tahu assignee. Setelah gagal, engineer memperbaiki lalu meminta lagi, dan tiket kembali ke antrean Anda.

Komentar bersifat publik, jadi pelapor juga bisa membaca catatan Anda. Tulis sesuai fakta.

## Yang tidak dilakukan QA

QA tidak meminta pemeriksaan, tidak mengubah status tiket, dan tidak menutup tiket. Itu tetap tugas engineer, yang menandai tiket <b>Done</b> saat sudah yakin. Anda bisa berkomentar di tiket dan membuat tiket baru jika menemukan masalah baru saat menguji.

## Memakai asisten AI

Alat tiket di asisten tersedia untuk QA. Asisten bisa menampilkan dan membaca tiket yang bisa Anda lihat, dan membuat tiket baru untuk Anda. Asisten hanya bisa berkomentar pada tiket yang ditugaskan kepada Anda, jadi berikan keputusan QA di aiku.

<aside class="wayfinder"><strong>Tempat klik di aiku</strong>
<ul>
<li><b>Melihat yang perlu diperiksa:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>QA queue</b>.</li>
<li><b>Membuka halaman yang bermasalah:</b> buka tiket → tautan <b>page where it happens</b>.</li>
<li><b>Memberi keputusan:</b> buka tiket → <b>QA passed</b> atau <b>QA failed</b> → tulis catatan → <b>Pass</b> atau <b>Fail</b>.</li>
<li><b>Melaporkan masalah baru yang ditemukan saat menguji:</b> tombol merah <b>Bug</b>, atau <b>Tickets</b> → <b>New ticket</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Izin yang Anda perlukan</strong>
Peran <b>QA</b> memungkinkan Anda melihat antrean QA dan memberi keputusan. Lead engineer juga bisa memberi keputusan. Peran berasal dari posisi kerja Anda.
</aside>
