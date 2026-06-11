1. kita konteksnya integrasi aplikasi enterprise terus dikasih tema smart hospitality. nah di dalamnya ada 3 service yaitu ada katalog & kamar, reservasi/booking, Layanan tamu (guest). Nah coba berikan rekomendasi probisnya yang scopenya kecil hanya  1 tujuan bukan multitujuan, tapi juga list aktivitasnya walaupun ga sebanyak dengan endpoint which is 1 service minimal 3 endpoint (total 9) juga menyesuaikan mau <9 gapapa asal aktivitas tersebut bisa mencakup endpoint tersebut
2. proses bisnisnya cuma 1 dan realcase tapi di dalamnya harus ada ketiga servise tersebut.
3. Q: Skenario real case mana yang paling relevan?
   A: ketiga saran tersebut masih bisa dipecah jadi beberapa probis, ini hanya 1 probis yang tujuannya ya 1 bukan multi
4. itu juga masih bisa dipecah jadi 2 probis
5. gak sesuai realcase ga logic
6. gimana cara mendaftarkan custom middleware alias di Laravel 11 menggunakan bootstrap/app.php tanpa Kernel.php seperti di versi sebelumnya?
7. Kenapa docker-php-ext-install pdo_mysql tidak cukup untuk koneksi MySQL di PHP 8.3 dan apa yang perlu ditambahkan agar PDO bisa berjalan dengan benar?
8. gimana struktur Swagger OA annotation yang benar di Laravel agar schema definition bisa di-reference oleh endpoint lain menggunakan $ref tanpa menyebabkan error "schema not found"?
9. Apa perbedaan pendekatan routing API di Laravel 11 dibanding Laravel 10, dan bagaimana cara mendaftarkan file api.php agar route prefix /api/v1 bisa dikenali?
10. gimana cara membuat GraphQL schema dengan Lighthouse yang bisa memfilter data berdasarkan enum value seperti status kamar dan tipe kamar secara bersamaan dalam satu query?
11. Kenapa composer.json yang di-generate dari Laravel 13 tidak kompatibel dengan Docker image PHP 8.2 dan bagaimana strategi downgrade yang benar tanpa merusak dependency lain?
12. gimana cara menulis docker-entrypoint.sh yang bisa menunggu MySQL siap sebelum menjalankan artisan migrate, lalu otomatis seed jika database masih kosong?
13. Apa yang menyebabkan Laravel membaca file database.php dengan namespace Pdo\Mysql dan kenapa ini hanya terjadi ketika project di-generate dari versi Laravel yang lebih baru?
14. gimana best practice struktur response wrapper API agar konsisten di semua endpoint termasuk saat error validasi, resource not found, dan unauthorized access?
15. JWT token dari SSO dosen perlu diverifikasi signature-nya dulu sebelum digunakan untuk hit SOAP dan RabbitMQ? Atau cukup langsung dipakai sebagai Bearer token? Apa bedanya secara security?
16. SOAP sudah dapat response SUCCESS tapi ReceiptNumber kosong. LogContent saya isi dengan JSON string biasa tanpa CDATA. Apakah ini penyebabnya? Kenapa CDATA harus dipakai?
17. Kalau saya taro SSO → SOAP → RabbitMQ secara sequential di dalam method assign(), dan salah satunya gagal di tengah jalan, apa yang terjadi ke transaksi DB lokalnya? Harus pakai database transaction atau gimana?