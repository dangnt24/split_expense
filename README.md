# Split Money — GitHub Pages + Firestore

Bản này chỉ cần `index.html` trên GitHub Pages, không dùng PHP, `data.json`, XAMPP hay `.htaccess`. Firestore là nơi lưu dữ liệu thật, còn `firestore.rules` cần **dán vào Firebase Console**, không upload để mong tự áp dụng.

## Thiết lập

1. Mở https://console.firebase.google.com/ → chọn project `split-money-app-c6ac2` (project cấu hình sẵn trong `index.html`). Nếu là project khác, thay `firebaseConfig` bằng cấu hình Web App của project đó.
2. Authentication → Sign-in method → bật **Anonymous**.
3. Firestore Database → tạo database nếu chưa có, chọn **Standard edition**, một database mặc định. Vào **Rules**, dán **toàn bộ** nội dung file `firestore.rules` và **Publish**. Đừng dùng `allow read, write: if true`.
4. Authentication → Settings → Authorized domains: thêm domain GitHub Pages `TEN_USER.github.io` nếu đăng nhập báo lỗi domain không được phép.
5. GitHub → repository → đưa file `index.html` lên nhánh `main` (ở root). Settings → Pages → Deploy from a branch → `main` → `/ (root)` → Save. Mở `https://TEN_USER.github.io/TEN_REPO/`.
6. Tạo trip và gửi link đầy đủ `?trip=...` cho mọi người. Dùng cửa sổ ẩn danh hoặc thiết bị khác để thử đồng bộ.

## Quyền và an toàn

- Người có link có thể **xem và thêm** dữ liệu. **Người đã thêm khoản chi** (theo tài khoản ẩn danh Firebase của trình duyệt) mới được sửa/xóa khoản đó.
- Thành viên chỉ được **thêm**, không có nút sửa/xóa để tránh phá vỡ lịch sử. Link là mã ngẫu nhiên khó đoán nhưng **không phải hệ thống phân quyền nhóm kín**: người biết mã truy cập có thể đọc dữ liệu nếu đăng nhập ẩn danh. Không dùng cho thông tin nhạy cảm.
- Nếu trình duyệt xóa dữ liệu/đăng nhập ẩn danh, UID có thể thay đổi, khiến người dùng không thể sửa khoản chi cũ. Cần tài khoản đăng nhập thực sự hoặc admin backend nếu muốn quản lý nâng cao.
- Firestore rules kiểm tra cấu trúc dữ liệu, không ngăn hoàn toàn spam/hết quota. Có thể thêm App Check và quản lý người tham gia trong bản nâng cao.
- Firebase Web API key nằm trong HTML không phải mật khẩu quản trị. **Đừng đặt mật khẩu bí mật hoặc token GitHub trong client.**
- Project Firebase ban đầu có thể còn rules hoặc dữ liệu cũ. Nên sao lưu, rà soát rules trước khi dán vì rules mới chỉ dành cho cấu trúc app này.
- Chi phí: Firestore có mức miễn phí giới hạn; vượt mức quota có thể bị giới hạn hoặc phát sinh phí nếu chuyển sang gói trả phí. Xem https://firebase.google.com/docs/firestore/pricing

## Kiểm thử local

Có thể dùng VS Code Live Server hoặc `python -m http.server 8000`, rồi truy cập `http://localhost:8000` (tùy chọn, không cần PHP). Đảm bảo domain được Firebase cho phép nếu gặp lỗi đăng nhập.
