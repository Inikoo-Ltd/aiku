---
title: Mengerjakan antrean help desk
summary: Untuk engineer dan lead engineer - cara mengambil tiket, menjaga agar terus berjalan, mengerjakannya bersama kolaborator, bertanya kepada pelapor, menutupnya dengan catatan, dan meminta QA memeriksa perbaikan Anda.
date: 2026-09-15
source_date: 2026-09-15
tags: help desk, tickets, engineers
category: help-desk
audience: engineers
help_routes: grp.tickets.board
---

<aside class="tldr">
Tiket adalah permintaan bantuan dari rekan kerja atau pelanggan. Tugas Anda adalah mengambil tiket, mengerjakannya, memberi kabar kepada pelapor, lalu menutupnya dengan catatan singkat tentang apa yang Anda lakukan. Semua ini bisa dilakukan dari halaman tiket, dari <b>Board</b>, atau langsung dari <b>List</b>. Semua yang Anda tulis di tiket bisa dibaca oleh orang yang melaporkannya, jadi tulislah seolah Anda sedang berbicara dengan mereka.
</aside>

## Tempat menemukan pekerjaan Anda

Buka <b>Tickets</b> di menu kiri. Ada tiga cara melihat tiket:

- <b>Dashboard</b>: apa yang perlu diperhatikan sekarang. Tiket baru yang belum diambil siapa pun, tiket yang ditugaskan kepada Anda, tiket tempat Anda menjadi kolaborator, tiket yang menunggu balasan, dan tiket yang menunggu QA.
- <b>Board</b>: setiap tiket sebagai kartu, dalam kolom dari kiri ke kanan: <b>Todo</b>, <b>Assigned</b>, <b>In progress</b>, <b>Waiting</b> dan <b>Closed</b>. Ini cara termudah untuk melihat apa yang sedang terjadi dalam sekejap.
- <b>List</b>: setiap tiket sebagai baris dalam tabel, yang terbaru di atas. Pakai ini saat Anda ingin mencari, memfilter, atau mengubah beberapa tiket dengan cepat.

<b>Tip:</b> di List, pakai <b>Mine</b> untuk melihat tiket Anda sendiri saja: yang Anda laporkan, yang ditugaskan kepada Anda, atau yang Anda bantu sebagai kolaborator. aiku mengingat pilihan ini, jadi List akan terbuka dengan cara yang sama lain kali.

## Melihat tiket dengan cepat

Anda tidak perlu membuka setiap tiket di halaman baru. <b>Klik sebuah kartu</b> di Board, atau <b>klik sebuah baris</b> di List, dan tiket akan terbuka di jendela di atas halaman.

Di jendela itu Anda bisa:

- membaca deskripsi dan melihat file yang dilampirkan,
- membaca komentar dan menulis balasan,
- mengubah status, orang yang mengerjakannya, kolaborator, kind, module dan tag di sebelah kanan, jika tiket itu milik Anda.

Perubahan langsung tersimpan begitu Anda membuatnya. Tutup jendela dengan <b>×</b> di pojok atau dengan mengklik di luar jendela.

## Perjalanan sebuah tiket

Setiap tiket punya <b>status</b> yang memberi tahu semua orang posisinya:

- <b>Todo</b>: baru, belum diambil siapa pun.
- <b>Assigned</b>: seseorang sudah memasukkannya ke daftar mereka.
- <b>In progress</b>: seseorang sedang mengerjakannya sekarang.
- <b>Waiting</b>: kita bertanya kepada pelapor dan sedang menunggu jawabannya.
- <b>Reporter replied</b>: pelapor sudah menjawab, jadi giliran kita lagi.
- <b>Waiting for deployment</b>: perbaikan sudah selesai dan tiket akan tertutup sendiri begitu perbaikannya live di aiku.
- <b>Done</b>: sudah diperbaiki atau diselesaikan.
- <b>Cancelled</b>: ditutup tanpa perbaikan.

## Cara mengerjakan tiket

1. <b>Ambil tiketnya.</b> Lead engineer menugaskan tiket kepada Anda. Anda akan melihatnya di <b>Assigned to me</b> pada Dashboard.
2. <b>Mulai.</b> Tekan <b>Start</b> supaya semua orang tahu Anda sedang mengerjakannya.
3. <b>Bertanya kalau buntu.</b> Tekan <b>Ask reporter</b>, tulis pertanyaan Anda, dan pilih berapa lama waktu mereka untuk menjawab (lihat di bawah).
4. <b>Hubungkan perbaikan Anda.</b> Tulis nomor tiket, misalnya <b>HELP-3131</b>, di pesan commit Anda. Saat perbaikan live, aiku menambahkan komentar di tiket yang memberitahukannya.
5. <b>Minta QA memeriksa</b> setelah perbaikan live, jika perubahannya perlu diperiksa.
6. <b>Tutup tiketnya.</b> Tekan <b>Done</b>, tulis catatan singkat tentang apa yang Anda lakukan, lalu konfirmasi.

Kalau perlu jeda, tekan <b>Stop, back to assigned</b>. Tekan <b>Resume</b> untuk melanjutkan nanti. Jika tiket yang sudah ditutup masih perlu dikerjakan, tekan <b>Reopen</b>.

## Mengubah tiket dari List

Untuk tiket yang ditugaskan kepada Anda, Anda bisa mengubah beberapa hal langsung di List tanpa membuka tiketnya. Klik nilai yang ingin diubah di baris itu, lalu pilih yang baru.

Anda bisa mengubah:

- <b>Status</b>: mulai bekerja, bertanya kepada pelapor, melanjutkan setelah ada balasan, atau menutupnya dengan <b>Done</b> atau <b>Cancel</b>.
- <b>Priority</b>: seberapa mendesak tiket itu.
- <b>Assignee</b>: mengopernya ke rekan kerja.
- <b>Kind</b>: bug atau permintaan fitur.
- <b>Module</b>: bagian aiku mana yang dibahas.

Baris untuk tiket yang ditugaskan kepada orang lain, dan tiket yang belum diambil siapa pun, tidak bisa diubah. Lead engineer bisa mengubah semua baris.

<b>Tip:</b> saat perubahan sedang disimpan, Anda akan melihat ikon kecil yang berputar. Tunggu sampai selesai sebelum mengubah tiket yang sama lagi.

## Mengerjakan tiket bersama

Beberapa tiket butuh lebih dari satu orang. Orang yang ditugaskan pada tiket adalah <b>pemimpin</b> tiket tersebut, dan bisa menambahkan rekan sebagai <b>kolaborator</b>.

1. Buka tiketnya.
2. Di bawah <b>Collaborators</b>, tekan tombol <b>+</b>.
3. Centang engineer atau rekan QA yang ingin Anda tambahkan. Anda bisa mencentang beberapa orang berturut-turut, aiku menyimpannya sekaligus sesaat setelah klik terakhir Anda.

Setiap kolaborator diberi tahu bahwa mereka ditambahkan, dan perubahannya tercatat di <b>History</b> tiket. Untuk mengeluarkan seseorang, tekan <b>+</b> lagi dan hapus centangnya.

Yang bisa dilakukan kolaborator:

- melihat tiket, meskipun tiket itu rahasia,
- membaca dan menulis komentar,
- menambah dan menghapus tag,
- menulis catatan internal,
- meminta QA memeriksa, atau menarik kembali permintaan itu.

Yang tetap menjadi hak pemimpin:

- mengubah status, misalnya memulai, menjeda atau menutup tiket, dan memindahkannya di Board,
- mengubah priority, kind dan module,
- mengoper tiket dan memilih kolaborator.

Jika Anda mengoper tiket ke salah satu kolaboratornya, orang itu menjadi pemimpin dan dikeluarkan dari daftar kolaborator.

<b>Tip:</b> tiket yang Anda bantu sebagai kolaborator muncul di <b>Collaborating on</b> pada Dashboard, dan sebagai foto kecil di samping assignee pada Board.

## Bertanya kepada pelapor

1. Tekan <b>Ask reporter</b>.
2. Tulis apa yang perlu Anda ketahui, misalnya "Mohon kirimkan nomor pesanan dan screenshot pesan errornya".
3. Pilih berapa lama waktu mereka untuk menjawab: 2 jam, 1 hari, 2 hari, 3 hari atau 14 hari.
4. Tekan <b>Send and wait</b>.

Tiket pindah ke <b>Waiting</b> dan pelapor menerima pertanyaan Anda. Saat mereka menjawab, tiket menampilkan <b>Reporter replied</b>. Jika tidak ada yang menjawab tepat waktu, tiket dibatalkan otomatis dengan catatan yang menjelaskannya.

## Menutup tiket

Saat Anda menekan <b>Done</b> atau <b>Cancel</b>, aiku meminta catatan singkat sebelum menutup tiket:

- Untuk <b>Done</b>: apa yang Anda lakukan? Misalnya "Memperbaiki pembulatan pada total invoice".
- Untuk <b>Cancel</b>: kenapa tiket ini ditutup? Misalnya "Duplikat dari HELP-12, dilanjutkan di sana".

Catatan itu ditambahkan ke tiket sebagai komentar. Catatan ini wajib supaya pelapor selalu tahu apa yang terjadi dengan permintaannya, dan tidak ada yang perlu bertanya belakangan kenapa tiket ditutup.

### Saat perbaikan belum live

Kadang perbaikan Anda sudah selesai, tetapi baru sampai ke aiku pada update berikutnya. Anda tentu tidak ingin bilang "sudah diperbaiki" kepada pelapor sebelum mereka bisa melihatnya.

1. Tekan <b>Done</b> dan tulis catatan Anda seperti biasa.
2. Alih-alih <b>Done</b>, tekan <b>Set as Done on Next Deployment</b>.

Tiket pindah ke <b>Waiting for deployment</b> dan catatan Anda disimpan dulu. Begitu update aiku berikutnya live, catatan Anda dipasang dan tiket tertutup dengan sendirinya.

## Mengoper tiket ke rekan kerja

Buka tiketnya, klik nama orang yang ditugaskan, lalu pilih rekan Anda. Anda hanya bisa mengoper tiket yang ditugaskan kepada Anda. Hanya lead engineer yang bisa melepas tiket dari seseorang tanpa memberikannya kepada orang lain.

Kalau Anda hanya butuh bantuan dan tetap ingin memegang tiketnya, tambahkan rekan Anda sebagai kolaborator saja.

aiku tidak menambahkan komentar saat tiket berpindah tangan. Anda selalu bisa melihat siapa yang memegangnya dan kapan di <b>History</b> tiket.

## Kind dan module

<b>Kind</b> menunjukkan apakah tiket itu bug atau permintaan fitur. <b>Module</b> menunjukkan bagian aiku mana yang dibahas. Hanya orang yang ditugaskan pada tiket, atau lead engineer, yang bisa mengubahnya, karena keduanya menentukan bagaimana tiket dihitung dalam laporan.

Tiket yang berasal dari pelanggan dan sudah dieskalasi tetap memakai kind <b>Escalated customer ticket</b>. Kind ini tidak bisa diubah, supaya hubungan dengan pelanggan tidak pernah hilang.

## Komentar dan file

Setiap komentar bisa dibaca oleh pelapor, dan oleh pelanggan pada tiket pelanggan. Tulislah dengan jelas dan sopan.

Jika sebuah komentar hanya untuk orang yang mengerjakan tiket, centang <b>Internal note</b> di bawah kotak komentar sebelum mengirim. Catatan internal ditampilkan berwarna kuning. Semua engineer dan lead engineer bisa membacanya, begitu juga rekan QA yang mengerjakan tiket itu, tetapi pelapor tidak bisa membacanya dan tidak diberi tahu. Hanya assignee, kolaborator dan lead engineer yang bisa menulisnya. Anda bisa mengedit atau menghapus komentar Anda sendiri.

Komentar dan <b>History</b> masing-masing punya tombol untuk menampilkan <b>yang terbaru dulu</b> atau <b>yang terlama dulu</b>. aiku mengingat pilihan Anda.

Anda bisa menambahkan file ke komentar dengan menempel, menyeret, atau menekan <b>Attach</b>:

- screenshot dan gambar, file PDF, Word, Excel dan CSV, masing-masing sampai 10 MB,
- video pendek (MP4, WebM atau MOV), masing-masing sampai 50 MB,
- file ZIP, RAR dan 7z, masing-masing sampai 10 MB, misalnya folder berisi file log.

Semua file di sebuah tiket ditampilkan bersama di <b>Attachments</b>. Klik salah satu untuk melihatnya tanpa mengunduh, dan pakai tanda panah untuk pindah ke file berikutnya. Mengklik file ZIP, RAR atau 7z menampilkan daftar file dan folder di dalamnya, dengan tombol <b>Download</b>. Jika sebuah file tidak bisa ditampilkan, aiku memberi tahu alasannya, misalnya karena file sudah dihapus atau Anda tidak punya izin. Pakai menu di samping judul untuk menampilkan satu jenis saja, misalnya hanya PDF.

Engineer, QA, lead engineer dan orang yang melaporkan tiket bisa membuka file-filenya. Rekan lain yang bisa melihat tiket akan melihat catatan kuning di atas file yang menjelaskan bahwa mereka tidak bisa melihat pratinjaunya.

## Penghitung tiket di bilah kanan

Penghitung tiket hijau di sisi kanan layar menunjukkan berapa tiket terbuka yang sedang Anda kerjakan: yang ditugaskan kepada Anda ditambah yang Anda bantu sebagai kolaborator. Klik untuk melihat keduanya dihitung terpisah. Angkanya diperbarui sendiri saat tiket diberikan kepada Anda, saat Anda ditambahkan sebagai kolaborator, atau saat salah satunya dicabut, jadi Anda tidak perlu memuat ulang halaman.

## Meminta QA memeriksa

Setelah perbaikan Anda live, tekan <b>Ask QA to check</b>. Tiket dikirim ke tim QA. Mereka mengujinya dan menjawab <b>QA passed</b> atau <b>QA failed</b> dengan catatan. Anda diberi tahu untuk keduanya. Jika gagal, perbaiki lalu tekan <b>Ask QA to check again</b>. Menutup tiket dengan <b>Done</b> tetap keputusan Anda.

## Tiket pelanggan

Tiket yang diawali <b>AD</b> berasal dari pelanggan. Jika salah satunya perlu dikerjakan oleh engineer, buka tiketnya dan tekan <b>Escalate to help desk</b>. aiku membuat tiket <b>HELP</b> yang terhubung dengan detail yang sama.

## Tiket rahasia

Tiket rahasia hanya bisa dilihat oleh orang yang membuatnya, orang yang ditugaskan, kolaboratornya, dan lead engineer.

## Memakai asisten AI

Engineer, lead engineer dan QA bisa meminta bantuan asisten AI untuk tiket. Asisten bekerja dengan izin Anda sendiri: ia bisa mencari dan membaca tiket yang bisa Anda lihat, membuat tiket baru, dan mengubah tiket dengan cara yang sama seperti Anda. Asisten hanya bisa berkomentar pada tiket yang ditugaskan kepada Anda.

## Untuk lead engineer

Sebagai lead engineer, Anda juga bisa:

- memberikan tiket kepada siapa pun, dan melepas tiket dari seseorang,
- mengubah tiket mana pun dari List, tidak hanya tiket Anda sendiri,
- menandai tiket sebagai <b>Confidential</b>, dari menu di bagian atas tiket,
- menghapus tiket,
- menyembunyikan komentar: komentar itu ditampilkan berwarna merah dengan <b>Lead engineers only</b>, dan hanya lead engineer yang bisa melihatnya sampai Anda menekan <b>Unhide</b>,
- mengubah tiket apa pun, termasuk tiket yang belum diambil siapa pun,
- menambah dan mengeluarkan kolaborator di tiket mana pun,
- melihat <b>Tickets → Reports</b>: berapa tiket yang dibuat dan ditutup, berapa lama waktunya, dan bagaimana kinerja setiap engineer. Pakai <b>Filter by</b> di kanan atas untuk melihat angka satu engineer saja, atau <b>All assignees</b> untuk melihat semua orang lagi.

<aside class="wayfinder"><strong>Tempat klik di aiku</strong>
<ul>
<li><b>Melihat tiket saya:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>Assigned to me</b>.</li>
<li><b>Melihat tiket dengan cepat:</b> <b>Tickets</b> → <b>Board</b> atau <b>List</b> → klik kartu atau barisnya.</li>
<li><b>Mengubah tiket tanpa membukanya:</b> <b>Tickets</b> → <b>List</b> → klik status, priority, assignee, kind atau module di baris itu.</li>
<li><b>Bertanya kepada pelapor:</b> buka tiket → <b>Ask reporter</b>.</li>
<li><b>Menutup tiket:</b> buka tiket → <b>Done</b> atau <b>Cancel</b> → tulis catatan.</li>
<li><b>Menutup saat update berikutnya live:</b> buka tiket → <b>Done</b> → <b>Set as Done on Next Deployment</b>.</li>
<li><b>Mengoper tiket:</b> buka tiket → klik assignee → pilih rekan kerja.</li>
<li><b>Menambah kolaborator:</b> buka tiket → <b>Collaborators</b> → <b>+</b> → centang rekan Anda.</li>
<li><b>Melihat tiket yang saya bantu:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>Collaborating on</b>.</li>
<li><b>Meminta QA:</b> buka tiket → <b>Ask QA to check</b>.</li>
<li><b>Laporan (lead engineer):</b> <b>Tickets</b> → <b>Reports</b> → <b>Filter by</b> untuk memilih satu engineer.</li>
</ul>
</aside>

<aside class="permissions"><strong>Izin yang Anda perlukan</strong>
Peran <b>Engineer</b> memungkinkan Anda mengerjakan dan menutup tiket yang ditugaskan kepada Anda, mengopernya, menambahkan kolaborator, mengubah kind dan module-nya, mengubahnya dari List, dan meminta QA memeriksa. Pada tiket tempat Anda menjadi kolaborator, Anda bisa berkomentar, mengubah tag, dan meminta QA memeriksa. Anda tidak bisa mengubah tiket yang ditugaskan kepada orang lain, atau tiket yang belum diambil siapa pun. Peran <b>Lead engineer</b> juga memungkinkan Anda memberikan dan menarik kembali tiket apa pun, mengubah tiket apa pun, menambahkan kolaborator ke tiket apa pun, menandai tiket sebagai rahasia, melihat laporan, menghapus tiket, serta menyembunyikan atau menampilkan komentar. Peran Anda berasal dari posisi kerja Anda.
</aside>
