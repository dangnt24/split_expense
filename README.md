# Split Money App — lưu JSON, không Firebase

## Cách chạy trên hosting

1. Chọn hosting **có chạy PHP 8.0 trở lên**, tốt nhất dùng Apache và HTTPS. Hosting chỉ phục vụ file tĩnh như GitHub Pages / Netlify không chạy được `api.php`.
2. Upload **tất cả** file `index.html`, `api.php`, `config.php`, `data.json`, `.htaccess` vào **cùng một thư mục** website (ví dụ `public_html/`).
3. Mở `config.php`, đổi `APP_EDIT_PASSWORD` thành mật khẩu riêng đủ mạnh.
4. Đảm bảo PHP có quyền ghi file `data.json` (thông thường quyền `664` hoặc `644` nếu PHP chạy cùng chủ sở hữu file). **Không đặt quyền `777` nếu không cần**.
5. Truy cập `https://ten-mien-cua-ban/.../index.html`, nhập tên trip và thêm thành viên.
6. Dùng nút Copy để gửi link cho bạn bè. Trang tự cập nhật dữ liệu sau khoảng 4 giây.

## Về bảo mật / giới hạn

- `.htaccess` chặn truy cập trực tiếp `data.json` và `config.php` **chỉ trên Apache có bật AllowOverride**. Nếu dùng Nginx hoặc hosting bỏ qua `.htaccess`, cần thiết lập quy tắc chặn tương đương hoặc chuyển file dữ liệu/cấu hình ra ngoài thư mục công khai.
- Trong bản tối giản này, bất kỳ ai biết tên trip có thể mở trip. Thành viên có link được thêm chi tiêu; chỉ sửa/xóa **khoản chi** mới cần mật khẩu. Việc ẩn nút thành viên ở giao diện link chia sẻ **không phải** cơ chế phân quyền. Tránh lưu dữ liệu nhạy cảm. Nếu cần bảo vệ chặt, hãy thêm xác thực máy chủ cho mọi thao tác ghi.
- `data.json` là dữ liệu chung của toàn bộ trip trong website. Nên sao lưu file này định kỳ. Không nên sử dụng cho số lượng truy cập rất lớn.
- Bản này dùng tiền VNĐ **số nguyên** và tự chia phần dư theo đơn vị 1₫, nên các khoản thanh toán khớp đúng tổng.
- Xóa thành viên sẽ xóa luôn những khoản chi mà người đó đã trả, như ứng dụng gốc. Sửa tên thành viên không làm mất chi tiêu.
- `data.json` phải ở cùng thư mục với `api.php` theo yêu cầu; có thể đổi `$path` trong `api.php` để lưu an toàn hơn ở thư mục không công khai.

## Chạy thử ở máy cá nhân

Trong thư mục chứa file, chạy:

```bash
php -S localhost:8000
```

Mở `http://localhost:8000/`.

**Chú ý:** Web server có sẵn trong PHP không áp dụng `.htaccess` và không dùng để public lên Internet. Muốn triển khai thực tế nên dùng máy chủ cấu hình chặn truy cập file JSON và config.
