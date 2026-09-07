---
title: Menambah staf ke organisasi agen Anda
summary: Untuk administrator agen — cara memberi rekan kerja Anda akses aiku sendiri, memilih apa yang boleh mereka lakukan, dan menutup akun ketika seseorang berhenti.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 2
---

<aside class="tldr">
Untuk administrator organisasi agen. Setelah Anda bisa masuk, Anda tidak lagi memerlukan perusahaan pembeli untuk menambahkan orang: Anda membuat sendiri rekan kerja Anda di <b>HR → Employees</b> (SDM → Karyawan), memberi mereka jabatan (position) yang sesuai dengan pekerjaannya, serta username dan password. Sisi perusahaan pembeli, membuat akun pertama Anda sendiri, ada di <a href="/docs/giving-an-agent-their-first-login">giving an agent their first login</a>.
</aside>

## Apa yang akan dilihat rekan kerja Anda

Semua orang di organisasi Anda melihat aiku yang sama seperti Anda, dipersempit sesuai jabatan mereka: menu **Procurement** (Pengadaan) dengan pesanan pembelian ke pemasok, pengiriman stok, dan papan daftar belanja, dan, untuk administrator, menu **HR** (SDM). Tidak ada yang di organisasi Anda dapat melihat toko atau pelanggan milik perusahaan pembeli, atau agen lain.

## Menambah rekan kerja

Buka **HR → Employees** (SDM → Karyawan) dan tekan **Create Employee** (Buat Karyawan). Formulirnya satu halaman; bagian yang penting bagi Anda adalah:

- **Employment** (Kepegawaian): sebuah **worker number** (nomor pekerja) dan sebuah **alias**, keduanya unik dalam organisasi Anda (nama depan sudah cukup), dan status **Working** (Bekerja).
- **Job → Position** (Pekerjaan → Jabatan): pilih apa yang boleh dilakukan orang tersebut. **Buyer** (Pembeli) sudah cukup untuk seseorang yang menangani pesanan pembelian dan pengiriman. Berikan **Organisation Administrator** (Administrator Organisasi) hanya kepada orang yang memang harus bisa menambah dan menghapus rekan kerja, karena jabatan ini memberikan akses penuh ke seluruh organisasi.
- **User credentials** (Kredensial pengguna): biarkan kosong untuk orang yang tidak perlu masuk. Isi **username** dan **password**, dan mereka bisa langsung masuk; aiku akan meminta mereka memilih password sendiri pada saat pertama masuk.

Simpan, lalu sampaikan username dan password awal kepada mereka.

## Mengubah apa yang boleh dilakukan seseorang

Buka karyawan tersebut dari **HR → Employees** (SDM → Karyawan), tekan **Edit** dan ubah **Position** (Jabatan) mereka. Perubahan berlaku pada saat mereka memuat halaman berikutnya.

## Ketika seseorang berhenti

Buka data karyawan mereka, tekan **Edit** dan ubah status menjadi **Left** (Berhenti). Kemudian buka pengguna (user) mereka dari halaman karyawan, tekan **Edit** dan matikan **Can login** (Dapat masuk). Mengubah status saja tetap membiarkan pintu terbuka.

<aside class="wayfinder"><strong>Di mana mengekliknya di aiku</strong>
<ul>
<li><b>Menambah rekan kerja:</b> <b>HR → Employees</b> (SDM → Karyawan) → <b>Create Employee</b> (Buat Karyawan).</li>
<li><b>Mengubah apa yang boleh dilakukan seseorang:</b> buka karyawan → <b>Edit</b> → <b>Position</b> (Jabatan).</li>
<li><b>Seseorang berhenti:</b> buka karyawan → <b>Edit</b> → State (Status) <b>Left</b> (Berhenti), lalu pengguna karyawan tersebut → <b>Edit</b> → <b>Can login</b> (Dapat masuk) dimatikan.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Izin yang Anda perlukan</strong>
<ul>
<li>Jabatan <b>Organisation Administrator</b> (Administrator Organisasi) membawa hak edit HR dalam organisasi Anda, yaitu semua hal di atas. Buyer tidak dapat menambah atau mengedit orang.</li>
</ul>
</aside>
