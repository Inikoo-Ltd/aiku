---
title: Mencatat kemajuan pesanan sebagai agen
summary: Untuk agen — tanggal yang Anda catat pada setiap pesanan ke pemasok (deposit paid, production started, production done, proposed ready date, handed over) dan nomor pesanan Anda sendiri, kapan dan di mana mencatatnya, apa yang dicatat oleh perusahaan pembeli sebagai gantinya, dan mengapa tanggal yang hilang tampak sebagai keterlambatan.
date: 2026-10-23
source_date: 2026-10-23
tags: procurement, agents, supply-chain
category: procurement
series: PO journey
order: 2
---

<aside class="tldr">
Untuk agen koordinasi. Perusahaan pembeli mengikuti setiap pesanan melalui tujuh tahap: dikirim, deposit dibayar, dalam produksi, siap dan QC lulus, dikirim keluar, tiba dan lolos bea cukai, diterima. Ia bisa melihat sendiri kapan barang dikirim dan kapan barang tiba, tetapi tidak bisa melihat apa yang terjadi di pihak pemasok. Bagian itu berasal dari Anda: pada setiap pesanan ke pemasok, Anda mencatat kapan deposit dibayar, kapan produksi dimulai, kapan produksi selesai, tanggal yang Anda perkirakan barang siap, dan hari Anda menyerahkan barang kepada agen pengiriman. Catat setiap tanggal pada hari kejadiannya. Sisi pembeli dijelaskan di <a href="/docs/reading-the-po-journey-board">reading the PO journey board</a> (bahasa Inggris).
</aside>

## Mengapa tanggal Anda penting

Setiap pesanan yang ditempatkan perusahaan pembeli melalui Anda menampilkan tahapnya di bagian atas halaman pesanan, dan juga sebagai satu baris di papan <b>PO journey</b> (perjalanan PO). Setiap tahap menunjukkan tanggalnya dan siapa yang mencatatnya. Ketika sebuah tahap melewati targetnya, tahap itu berubah merah di papan dan menunjukkan berapa hari keterlambatannya.

Tanpa tanggal dari Anda, perusahaan pembeli hanya melihat dua hal di sisi Anda: hari pesanan dikirim kepada Anda dan hari barang berangkat. Semua yang terjadi di antaranya tidak terlihat, dan sebuah keterlambatan tampak sama saja entah itu terjadi di pemasok, di pemeriksaan mutu, atau di serah terima. Dengan tanggal dari Anda, tahap-tahap itu menunjukkan persis di mana posisi sebuah pesanan, dan pemasok yang terlambat terlihat berminggu-minggu sebelum barang seharusnya dikirim.

Begitu Anda mencatat tanggal pertama pada sebuah pesanan, tanggal-tanggal berikutnya diharapkan. Jika target sebuah tahap terlewati dan Anda belum mencatatnya, tahap itu tampak terlambat, sekalipun pekerjaannya sudah selesai. Tanggal yang hilang tampak persis seperti keterlambatan.

## Tanggal yang Anda catat

| Tanggal | Dicatat saat | Di mana |
|---|---|---|
| <b>Deposit paid</b> (deposit dibayar) | Pemasok sudah menerima deposit. | <b>Pay deposit</b> di panel pembayaran pesanan, dengan jumlah dan rekening koran bank. Pembayaran pertama yang dicatat adalah deposit; yang dibayar sesudahnya adalah pelunasan. |
| <b>Production started</b> (produksi dimulai) | Pemasok mulai membuat barang. | Tombol <b>Production started</b> pada tahap di bagian atas pesanan. |
| <b>Production done</b> (produksi selesai) | Barang selesai dikerjakan di pemasok. Selesai berarti seluruh pesanan, bukan hanya batch pertama. | Tombol <b>Production done</b> pada tahap di bagian atas pesanan. |
| <b>Proposed ready date</b> (tanggal siap yang diusulkan) | Anda mengetahui kapan barang akan siap untuk serah terima. Tanggal yang Anda yakini. Pembeli yang menyetujuinya. | Bagian <b>Clean handover</b> pada halaman <b>Edit</b> pesanan. |
| <b>Handed over</b> (diserahkan) | Barang sampai di agen pengiriman. Seluruh pesanan, bukan hanya bagian pertamanya. | Tombol <b>Handed over</b> pada tahap di bagian atas pesanan. |
| <b>Your order number</b> (nomor pesanan Anda) | Anda memiliki nomor sendiri untuk pesanan. | <b>Agent's order number</b> di halaman <b>Edit</b> pesanan. Nomor ini tampil di bawah PO submitted. |

Tahap berjalan berurutan. Catat setiap tanggal pada hari kejadiannya. Tanggal yang lebih dari satu hari di masa lalu, tahap yang dicatat sebelum tahap sebelumnya, atau tanggal yang Anda hapus akan meminta alasan dari Anda. Alasan itu disimpan di riwayat pesanan beserta nama Anda, sehingga pembeli dapat melihat mengapa.

## Apa yang dicatat oleh perusahaan pembeli

Ada tanggal-tanggal yang tidak bisa Anda masukkan sendiri, dan ini memang disengaja:

- **Sample approved** (sampel disetujui). Pembeli menyetujui sampel. Hanya untuk produk baru; pesanan ulang tidak memiliki sampel.
- **Tanggal siap yang disetujui.** Pembeli menyetujui tanggal yang Anda usulkan. Sejak saat itu tanggalnya terkunci, dan tanggal itu menetapkan target: pemeriksaan mutu jatuh tempo pada hari itu, dan serah terima bersih dalam tujuh hari sesudahnya.
- **QC lulus.** Pembeli mengonfirmasinya, biasanya berdasarkan bukti mutu dari Anda. Persetujuan QC melepaskan pembayaran pelunasan: sebelum itu, membayar pelunasan akan meminta alasan.
- **Paket kepatuhan lengkap.**

<a href="/docs/your-clean-handover-score-id">Skor Serah Terima Bersih</a> Anda dihitung dari tanggal-tanggal ini dan dari tanggal serah terima yang Anda catat. Tanggal serah terima adalah bagian yang Anda catat sendiri, jadi catatlah hari barang benar-benar sampai di agen pengiriman. Pembeli melihat siapa yang mencatatnya dan kapan, dapat memeriksanya terhadap tanda terima agen pengiriman, dan dapat mengoreksinya; koreksi disimpan di riwayat pesanan beserta alasannya.

## Saat pemasok mengubah harga

Jika pemasok meminta harga baru sebelum Anda mengonfirmasi pesanan, jangan konfirmasi dengan harga lama. Buka pesanan, cari produknya di <b>Items</b> (daftar barang), lalu klik <b>Supplier changed the price</b> (pemasok mengubah harga) pada barisnya. Isi harga yang diminta pemasok, per karton seperti yang tampil, dan catatan singkat jika perlu, lalu klik <b>Ask the organisation</b> (tanya perusahaan pembeli).

Perusahaan pembeli mendapat pemberitahuan dan menjawab di baris itu: menerima harga baru, mempertahankan harga lama, atau menetapkan harga lain. Anda juga mendapat pemberitahuan tentang jawabannya. Selama belum semua pertanyaan harga di pesanan dijawab, tombol <b>Confirm</b> (konfirmasi) tetap abu-abu. Jika perusahaan pembeli mempertahankan harga lama dan pemasok tidak mau menjual dengan harga itu, batalkan baris tersebut sebelum mengonfirmasi.

## Saat pemasok mengubah jumlah

Jika pemasok tidak dapat memasok jumlah yang dipesan, atau hanya menjual dalam lot yang lebih besar, tanyakan dulu kepada perusahaan pembeli sebelum Anda mengonfirmasi. Buka pesanan, cari produknya di <b>Items</b> (daftar barang), lalu klik <b>Ask for another quantity</b> (minta jumlah lain) di bawah jumlahnya. Isi jumlahnya, pada tingkat yang ditampilkan tab Items (karton, SKO atau unit), dan catatan singkat jika perlu, lalu klik <b>Ask the organisation</b> (tanya perusahaan pembeli).

Perusahaan pembeli mendapat pemberitahuan dan menjawab di baris itu: menerima jumlah baru atau mempertahankan jumlah lama. Anda juga mendapat pemberitahuan tentang jawabannya. Selama belum semua pertanyaan jumlah di pesanan dijawab, pesanan tidak dapat dikonfirmasi.

## Kebiasaan baik

- **Catat pada hari itu juga.** Lima detik pada hari kejadian menghindarkan perdebatan soal tahap merah di kemudian hari, dan alasan yang harus diketik.
- **Usulkan tanggal siap yang Anda yakini.** Tanggal realistis yang Anda penuhi lebih baik daripada tanggal optimistis yang Anda lewatkan.
- **Beri tahu pembeli lebih awal** saat sebuah tanggal akan mundur. Tanggal baru yang disepakati tepat waktu membuat pesanan tetap pada jalurnya; tanggal yang mundur tanpa persetujuan siapa pun akan tampak sebagai keterlambatan.

<aside class="wayfinder">
<b>Di mana mengekliknya di aiku</b><br>
Masuk → <b>Purchase Orders</b> (Pesanan Pembelian) → buka pesanan. Setiap pesanan pembelian ditujukan kepada salah satu pemasok Anda. Tujuh tahap ada di bagian atas halaman: gunakan tombol <b>Production started</b>, <b>Production done</b>, dan <b>Handed over</b> di sana. Deposit dicatat dengan <b>Pay deposit</b> di panel pembayaran. Lalu buka <b>Edit</b>: <b>Proposed ready date</b> dan <b>Agent's order number</b> ada di bagian <b>Clean handover</b>.
</aside>

<aside class="wayfinder">
<b>Izin yang Anda perlukan</b><br>
Akun masuk untuk organisasi agen Anda. Anda hanya dapat menandai tahap milik Anda sendiri, pada pesanan Anda sendiri: produksi dimulai, produksi selesai, dan diserahkan pada tahap, deposit sebagai pembayaran, serta tanggal siap yang diusulkan dan nomor pesanan Anda di halaman edit. Persetujuan sampel, tanggal siap yang disetujui, QC, dan kepatuhan tidak terbuka bagi Anda, karena dicatat oleh perusahaan pembeli.
</aside>
