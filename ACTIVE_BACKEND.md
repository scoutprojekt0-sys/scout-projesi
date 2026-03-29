# Active Backend

Bu workspace icin tek aktif backend ve tek aktif veritabani:

- Backend: `C:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean`
- Veritabani: `C:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean\database\database.sqlite`

Kurallar:

- Sadece bu klasorden `php artisan serve` calistirin.
- Sadece bu klasordeki `.env` ve `database/database.sqlite` aktif kabul edilir.
- `archive_legacy_backend` ve diger kopyalar sadece arsiv/referans icindir.
- Legacy kullanicilari aktif DB'ye toplamak icin `php artisan users:import-legacy-sqlite` kullanin.
