# BDU Media — Quản lý và điều phối truyền thông nội bộ

Ứng dụng Laravel + MySQL, giao diện Blade/CSS/JavaScript tiếng Việt. Mã nguồn Laravel nằm ngay ở thư mục gốc, cùng cấp với `artisan` và `composer.json`. Tài nguyên giao diện phục vụ trực tiếp từ `public/`, không cần npm/Vite để chạy ứng dụng.

## Đưa lên hosting: cấu hình .env rồi chạy lệnh

Mã nguồn đã được gom về thư mục gốc. Hosting cần PHP 8.3+, Composer, PHP CLI, MySQL 8.0+ và các extension PHP zip, gd, mbstring, dom, xml, pdo_mysql.

1. Tải mã nguồn lên host, bỏ qua `.runtime`, `.composer-cache`, `.env` local, `node_modules` và `vendor` local. Tệp người dùng tải lên trong `storage/app/private/requests` cần chuyển nếu giữ dữ liệu cũ.
2. Tạo database và tài khoản MySQL trên hosting. Nếu giữ dữ liệu hiện tại, nhập file SQL trước.
3. Sao chép `.env.example` thành `.env`, đặt APP_ENV=production, APP_DEBUG=false, APP_URL và các biến DB_*. Đặt SESSION_SECURE_COOKIE=true khi dùng HTTPS.
4. Nếu cài mới, đặt ADMIN_EMAIL và ADMIN_PASSWORD (ít nhất 10 ký tự). Nếu chuyển hệ thống cũ, giữ nguyên APP_KEY và dữ liệu tài khoản.
5. Chạy tại thư mục gốc:

```sh
composer install --no-dev --optimize-autoloader
composer run deploy
```

Lệnh deploy chỉ tạo APP_KEY khi chưa có, tạo/cập nhật bảng bằng migration, khởi tạo admin tổng khi chưa có và làm mới cache. Chạy lại không xóa dữ liệu hay đổi APP_KEY hiện có. Không tự tạo database trên hosting: database phải được tạo trước trong bảng quản trị host.

Document root phải trỏ tới **public**. Không phục vụ toàn bộ thư mục dự án ra web. Nếu hosting chỉ có public_html và không đổi được document root, cần bố trí mã nguồn bên ngoài public_html và cấu hình riêng.

Cron mỗi phút: `php /duong-dan-du-an/artisan schedule:run`. Cho phép ghi `storage` và `bootstrap/cache`. Khi host chặn proc_open, dùng các lệnh Artisan thủ công ở phần triển khai; chỉ chạy key:generate khi APP_KEY chưa có.

## Chạy trên Laragon

1. Mở Laragon, bấm **Start All**.
2. Mở **http://bdu-media.localhost** hoặc nhấp đúp **start-web.cmd**.

Đã cấu hình Apache trỏ tới `D:/code/BDU.CM_Media_Management/public` và MySQL Laragon tại `127.0.0.1:3306`, database `bdu_cm_media`. Tài khoản database riêng và mật khẩu lưu trong `.env`. Dữ liệu hiện tại đã được chuyển từ MySQL thử nghiệm sang MySQL của Laragon.

Virtual host: `C:/laragon/etc/apache2/sites-enabled/bdu-media.test.conf`. Địa chỉ `.localhost` hoạt động trong Chrome/Edge mà không cần quyền sửa file hosts. Khi di chuyển thư mục dự án, cập nhật DocumentRoot và Directory trong virtual host rồi Reload Laragon.

Tài khoản demo cũ đã được lưu trữ và khóa. Dùng admin tổng được cấp để tạo phòng ban và tài khoản mới; mật khẩu không lưu trong README.

Nhắc việc tự động: chạy `php artisan schedule:work` ở thư mục dự án, hoặc cấu hình Task Scheduler. Dừng dịch vụ bằng Laragon; Apache/MySQL dùng chung với các website Laragon khác.

Các bản sao lưu `.env` và SQL trước khi chuyển nằm trong `.runtime`. Không tải thư mục này hoặc `.env` local lên hosting. Chỉ chuyển database bằng xuất/nhập SQL và giữ nguyên APP_KEY khi mang dữ liệu cũ lên host.

## Cài đặt với MySQL của bạn

1. Tạo database `bdu_media` và tài khoản MySQL có quyền trên database này.
2. Trong thư mục gốc, chạy `composer install`, sao chép `.env.example` thành `.env`.
3. Đặt `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` theo MySQL thực tế.
4. Đặt `ADMIN_EMAIL` và `ADMIN_PASSWORD` (ít nhất 10 ký tự) để khởi tạo admin tổng.
5. Chạy:

```powershell
php artisan key:generate
php artisan migrate --seed
composer dev
```

Không cần dữ liệu demo khi vận hành. `DemoSeeder` chỉ được phép chạy trong local/testing.

## Quy trình sự kiện và phân quyền

Phòng ban tạo sự kiện, thêm tài liệu và gửi trực tiếp VP BGĐ. VP BGĐ kiểm tra, yêu cầu bổ sung hoặc duyệt; khi duyệt chọn **Tự chủ** hoặc **Cần hỗ trợ** và các phòng hỗ trợ. Một sự kiện được chia sẻ trên bảng và lịch của phòng chủ trì/các phòng hỗ trợ, không tạo bản sao. Các bên cập nhật tiến độ và sản phẩm; VP BGĐ xác nhận đóng kèm kết quả. Báo cáo cập nhật theo dữ liệu đóng thực tế.

Bảng công việc dạng Kanban gồm Nháp → Chờ VP duyệt → Đã duyệt → Đang thực hiện → Đã đóng. Cần bổ sung nằm trong cột chờ duyệt. Font chữ, cỡ chữ 14–20 px, tên và màu cột, nền bảng, màu giao diện tùy chỉnh theo tài khoản; màu thẻ lưu theo sự kiện. Đổi tên cột không thay đổi quy trình.

- Admin tổng quản lý toàn hệ thống, tạo đơn vị và tài khoản, cấp quyền chức năng.
- BGĐ xem trong phạm vi được admin cấp để giám sát.
- VP BGĐ duyệt, điều phối, đóng/hủy sự kiện khi có quyền duyệt; có thể được cấp quyền xem toàn đơn vị.
- Trưởng đơn vị tạo/gửi và sửa sự kiện đơn vị còn nháp/cần bổ sung.
- Nhân viên tạo/gửi và sửa sự kiện của mình còn nháp/cần bổ sung.
- Các tài khoản thuộc đơn vị chủ trì/hỗ trợ cập nhật tiến độ và sản phẩm của sự kiện đã duyệt; nhân sự truyền thông còn xem nhiệm vụ cũ được giao.

Lịch ngày/tuần/bảng dùng thời gian diễn ra sự kiện, hỗ trợ nhiều ngày. Báo cáo tháng/quý/toàn bộ tách vai trò chủ trì và hỗ trợ; tổng hệ thống đếm sự kiện một lần. Có xuất Excel/PDF/CSV. Tệp và liên kết có kiểm tra quyền; lịch sử lưu các thao tác nghiệp vụ. Tệp tối đa 20 MB: đặt PHP upload_max_filesize=20M, post_max_size=25M.

Migration quy trình mới giữ sự kiện/tài khoản/tài liệu hiện có, quy đổi trạng thái cũ và thêm quan hệ hỗ trợ. Các sự kiện đang triển khai mặc định tự chủ để VP BGĐ rà soát lại. Sao lưu database trước khi cập nhật.

## Kiểm thử

```powershell
cd D:\code\BDU.CM_Media_Management
php artisan test
php vendor/bin/pint --test app routes database tests
```

Bộ kiểm thử tính năng bao gồm phân quyền giữa đơn vị, truy cập tệp, luồng xử lý đầy đủ, phối hợp nhiều đơn vị, đóng sự kiện và khóa sửa sau đóng, nhắc hạn không lặp, màu giao diện và quản trị tài khoản.

Đã chạy kiểm thử trên SQLite in-memory và database MySQL riêng `bdu_media_test`; không chạy RefreshDatabase trên database vận hành. Chrome được dùng kiểm tra 11 màn hình ở các chiều rộng 1440, 820 và 390 px, đổi màu, menu mobile và quy trình qua năm vai trò.

Ảnh giao diện trong `docs/screenshots/`.

## Triển khai

- Document root trỏ tới `public`.
- Cấu hình `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` đúng tên miền và `SESSION_SECURE_COOKIE=true` khi dùng HTTPS.
- Dùng MySQL có tài khoản/mật khẩu riêng; không đưa `.env`, `.runtime`, log hoặc tài khoản demo lên máy chủ.
- Chạy `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
- Cho phép ghi `storage/` và `bootstrap/cache/`.
- Cấu hình cron mỗi phút chạy `php artisan schedule:run`, hoặc Task Scheduler tương đương trên Windows.
- Sao lưu cả MySQL và `storage/app/private/requests/`; giữ APP_KEY khi khôi phục.
- Múi giờ hệ thống: Asia/Ho_Chi_Minh (UTC+7).

## Hướng dẫn trong ứng dụng

Trang /help mô tả quy trình mới, phân quyền, bảng công việc, lịch, báo cáo và cách dùng modal. Modal dành cho thao tác ngắn; tạo/sửa và chi tiết sự kiện dùng trang riêng. Không mở modal lồng nhau.

Nhắc đăng ký tuần kế tiếp: thứ Năm 15:00; nhắc hạn hằng ngày 08:00. Thông báo gửi trong ứng dụng; cần cron/Task Scheduler. Chưa tích hợp email/SMS hoặc tự đăng lên mạng xã hội.

Tài khoản được admin tổng tạo và cấp trực tiếp trong trang quản trị; hệ thống không mở đăng ký cá nhân.

Admin cấp các quyền Bảng công việc, Sự kiện, Lịch, Duyệt, Báo cáo và Kho tài liệu riêng từng tài khoản. URL trực tiếp và xuất báo cáo cũng kiểm tra quyền. Tài khoản chưa có quyền vào trang cài đặt cá nhân; không được xem dữ liệu nghiệp vụ. Quyền duyệt chỉ dành cho VP BGĐ và cần quyền Sự kiện.

Lệnh `php artisan media:create-admin` tạo admin khi chưa có. Chỉ dùng `--reset-accounts` khi chủ động đặt lại tài khoản: lưu trữ tài khoản cũ, khóa đăng nhập, giữ lịch sử và giải phóng email để cấp lại; không xóa sự kiện hay phòng ban.
