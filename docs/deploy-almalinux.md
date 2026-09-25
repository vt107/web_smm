# Triển khai lên VPS AlmaLinux 9 (nginx + port 80 + Cloudflare)

Hướng dẫn cài từ đầu trên một VPS **AlmaLinux 9 mới hoàn toàn**. Mô hình:

```
Khách ──HTTPS──▶ Cloudflare ──HTTP :80──▶ nginx ──▶ PHP-FPM (Laravel)
                                              ├── MySQL 8
                                              ├── queue worker (systemd)
                                              └── scheduler (cron, mỗi phút)
```

Cloudflare lo HTTPS cho khách; VPS chỉ mở port 80 và **chỉ cho IP của Cloudflare truy cập**.

Quy ước trong tài liệu (thay bằng giá trị thật của bạn):

| Ký hiệu | Ý nghĩa |
|---|---|
| `example.com` | Tên miền |
| `1.2.3.4` | IP của VPS |
| `/var/www/smm` | Thư mục code |
| `smm` | User Linux chạy ứng dụng (và cũng là tên database) |

Mọi lệnh chạy bằng `root` (hoặc `sudo`), trừ khi ghi rõ `sudo -u smm`.

---

## 1. Chuẩn bị hệ thống

```bash
dnf update -y
dnf install -y epel-release
dnf config-manager --set-enabled crb
dnf install -y git unzip curl tar policycoreutils-python-utils cronie
systemctl enable --now crond

# User chạy ứng dụng (không đăng nhập bằng mật khẩu)
useradd --create-home --shell /bin/bash smm
```

## 2. Cài PHP 8.4 (kho Remi)

```bash
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
dnf module reset php -y
dnf module enable php:remi-8.4 -y
dnf install -y php php-fpm php-cli php-common php-mysqlnd php-bcmath php-intl \
  php-mbstring php-xml php-zip php-gd php-process php-opcache
php -v   # phải là PHP 8.4.x
```

Tinh chỉnh PHP:

```bash
cat > /etc/php.d/99-smm.ini <<'EOF'
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
EOF
```

Cho PHP-FPM chạy bằng user `smm`, socket cho nginx đọc được — sửa `/etc/php-fpm.d/www.conf`:

```bash
sed -i 's/^user = .*/user = smm/; s/^group = .*/group = nginx/' /etc/php-fpm.d/www.conf
sed -i 's/^;\?listen.owner = .*/listen.owner = nginx/; s/^;\?listen.group = .*/listen.group = nginx/; s/^;\?listen.mode = .*/listen.mode = 0660/' /etc/php-fpm.d/www.conf
sed -i 's/^listen.acl_users/;listen.acl_users/' /etc/php-fpm.d/www.conf
grep -E '^(user|group|listen)' /etc/php-fpm.d/www.conf   # kiểm tra: listen = /run/php-fpm/www.sock
```

Composer:

```bash
php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

## 3. Cài Node.js 22 (để build CSS/JS)

```bash
dnf module list nodejs            # kiểm tra có stream 22
dnf module enable nodejs:22 -y
dnf install -y nodejs
node -v                           # v22.x
```

> Nếu kho chưa có `nodejs:22`: `curl -fsSL https://rpm.nodesource.com/setup_22.x | bash - && dnf install -y nodejs`.

## 4. Cài MySQL 8 và tạo database

```bash
dnf install -y mysql-server
systemctl enable --now mysqld
mysql_secure_installation         # đặt mật khẩu root, xoá user ẩn danh, xoá DB test
```

Tạo database + user (thay `MAT_KHAU_DB` bằng mật khẩu mạnh):

```bash
mysql -uroot -p <<'SQL'
CREATE DATABASE smm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smm'@'127.0.0.1' IDENTIFIED BY 'MAT_KHAU_DB';
GRANT ALL PRIVILEGES ON smm.* TO 'smm'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

## 5. Cài nginx

```bash
dnf module enable nginx:1.24 -y   # bản mới hơn bản mặc định (không bắt buộc)
dnf install -y nginx
systemctl enable nginx
```

## 6. Tải code lên server

**Cách A – Git (khuyên dùng).** Đẩy code lên kho private (GitHub/GitLab), rồi trên VPS:

```bash
mkdir -p /var/www/smm && chown smm:nginx /var/www/smm
sudo -u smm ssh-keygen -t ed25519 -N '' -f /home/smm/.ssh/id_ed25519
cat /home/smm/.ssh/id_ed25519.pub
# → dán key này vào kho code (GitHub: Settings → Deploy keys, chỉ cần quyền đọc)
sudo -u smm git clone git@github.com:TEN_BAN/TEN_REPO.git /var/www/smm
```

**Cách B – Upload trực tiếp** từ máy đang có code (không gửi `vendor`, `node_modules`, `.env`):

```bash
# chạy trên máy của bạn
rsync -az --exclude vendor --exclude node_modules --exclude .env --exclude storage/logs \
  ./ root@1.2.3.4:/var/www/smm/
# rồi trên VPS
chown -R smm:nginx /var/www/smm
```

## 7. Cấu hình ứng dụng (.env)

```bash
cd /var/www/smm
sudo -u smm cp .env.example .env
sudo -u smm nano .env
```

Các giá trị cần sửa:

```dotenv
APP_NAME="SMM Panel"             # tên tạm, đổi sau trong Admin → Website settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com      # bắt buộc https

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smm
DB_USERNAME=smm
DB_PASSWORD=MAT_KHAU_DB

SESSION_SECURE_COOKIE=true

# Tài khoản admin tạo khi seed (xoá ADMIN_PASSWORD khỏi file sau khi cài xong)
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=MatKhauAdminManh!

# Chạy sau Cloudflare, firewall chỉ cho Cloudflare vào port 80 (bước 10)
TRUSTED_PROXIES=*

# Provider giả lập chỉ dùng để test – luôn TẮT khi chạy thật
SANDBOX_PROVIDER_ENABLED=false
```

> Email (SMTP), tên web, logo… **không cần** khai báo trong `.env` – cấu hình trong trang Admin (bước 12).
> Các biến `SEPAY_*`, `USDT_*` là tuỳ chọn; kênh nạp tiền cũng sửa được trong Admin → Payment methods.

Cài đặt và khởi tạo:

```bash
cd /var/www/smm
sudo -u smm composer install --no-dev --optimize-autoloader --no-interaction
sudo -u smm php artisan key:generate
sudo -u smm php artisan migrate --force
sudo -u smm php artisan db:seed --force          # cấp bậc, tiền tệ, kênh nạp, tài khoản admin
sudo -u smm php artisan storage:link
sudo -u smm npm ci --no-audit --no-fund
sudo -u smm npm run build
sudo -u smm php artisan optimize
sudo -u smm php artisan filament:optimize
```

> **Không** chạy `DemoCatalogSeeder` / `SandboxProviderSeeder` trên server thật (dữ liệu test).

Sau khi seed xong, xoá dòng `ADMIN_PASSWORD=` trong `.env` rồi chạy lại `sudo -u smm php artisan optimize`.

## 8. Quyền thư mục và SELinux

AlmaLinux bật SELinux mặc định – bắt buộc làm bước này, nếu không sẽ gặp lỗi 500 / không ghi được file.

```bash
cd /var/www/smm
chown -R smm:nginx /var/www/smm
chmod -R ug+rwX storage bootstrap/cache

semanage fcontext -a -t httpd_sys_content_t    "/var/www/smm(/.*)?"
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/smm/storage(/.*)?"
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/smm/bootstrap/cache(/.*)?"
restorecon -Rv /var/www/smm > /dev/null

# PHP cần gọi ra ngoài: API provider, SMTP, TronGrid/Etherscan, và kết nối MySQL qua TCP
setsebool -P httpd_can_network_connect 1
setsebool -P httpd_can_network_connect_db 1

# nginx phải đọc được thư mục home của user smm? Không – code nằm ở /var/www nên không cần.
chmod 755 /var/www
```

## 9. Cấu hình nginx

Lấy IP thật của khách từ Cloudflare (danh sách IP của Cloudflare):

```bash
{
  for ip in $(curl -s https://www.cloudflare.com/ips-v4) $(curl -s https://www.cloudflare.com/ips-v6); do
    echo "set_real_ip_from $ip;"
  done
  echo "real_ip_header CF-Connecting-IP;"
} > /etc/nginx/conf.d/cloudflare-realip.conf
```

Virtual host cho website (thay `example.com`):

```bash
cat > /etc/nginx/conf.d/smm.conf <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;
    root /var/www/smm/public;
    index index.php;
    charset utf-8;
    client_max_body_size 12m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml text/plain application/xml;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # CSS/JS đã build có hash trong tên file → cache lâu
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60;
    }

    # Chặn file ẩn (.env, .git…) và mọi file .php khác index.php
    location ~ /\.(?!well-known).* { deny all; }
    location ~ \.php$ { return 404; }
}
EOF

nginx -t && systemctl restart php-fpm nginx
systemctl enable php-fpm
```

## 10. Firewall – chỉ cho Cloudflare vào port 80

```bash
systemctl enable --now firewalld

firewall-cmd --permanent --new-ipset=cloudflare4 --type=hash:net
firewall-cmd --permanent --new-ipset=cloudflare6 --type=hash:net --option=family=inet6
for ip in $(curl -s https://www.cloudflare.com/ips-v4); do firewall-cmd --permanent --ipset=cloudflare4 --add-entry=$ip; done
for ip in $(curl -s https://www.cloudflare.com/ips-v6); do firewall-cmd --permanent --ipset=cloudflare6 --add-entry=$ip; done

firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source ipset="cloudflare4" port port="80" protocol="tcp" accept'
firewall-cmd --permanent --add-rich-rule='rule family="ipv6" source ipset="cloudflare6" port port="80" protocol="tcp" accept'
firewall-cmd --permanent --remove-service=http 2>/dev/null || true
firewall-cmd --permanent --add-service=ssh
firewall-cmd --reload
firewall-cmd --list-all
```

> Vì sao: `.env` đặt `TRUSTED_PROXIES=*` (tin header từ proxy). Nếu để ai cũng vào thẳng port 80, kẻ xấu có thể giả IP / giả HTTPS.
> Bạn sẽ **không** mở được `http://1.2.3.4` trực tiếp nữa – đó là đúng.

## 11. Queue worker và scheduler

Queue worker (gửi đơn sang provider, huỷ, refill) chạy nền bằng systemd:

```bash
cat > /etc/systemd/system/smm-queue.service <<'EOF'
[Unit]
Description=SMM panel queue worker
After=network.target mysqld.service

[Service]
User=smm
Group=nginx
WorkingDirectory=/var/www/smm
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=1 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now smm-queue
systemctl status smm-queue --no-pager
```

Scheduler (đồng bộ đơn 5 phút/lần, giá dịch vụ mỗi giờ, quét ví, tỉ giá…) – cron mỗi phút:

```bash
echo '* * * * * cd /var/www/smm && php artisan schedule:run >> /dev/null 2>&1' | crontab -u smm -
crontab -u smm -l
sudo -u smm php /var/www/smm/artisan schedule:list
```

## 12. Cloudflare

Trong dashboard Cloudflare của tên miền:

1. **DNS → Records**
   - `A` `@` → `1.2.3.4`, **Proxied** (đám mây cam)
   - `CNAME` `www` → `example.com`, **Proxied**
2. **SSL/TLS → Overview**: chọn **Flexible** (vì VPS chỉ có port 80).
   > Flexible: khách ↔ Cloudflare được mã hoá, Cloudflare ↔ VPS thì không. Muốn mã hoá cả đoạn này, xem [Phụ lục A](#phụ-lục-a--nâng-lên-ssl-full-strict).
3. **SSL/TLS → Edge Certificates**: bật **Always Use HTTPS**, **Automatic HTTPS Rewrites**; Minimum TLS Version **1.2**.
4. **Security → Bots**: **tắt Bot Fight Mode**. Chế độ này chặn các panel đại lý gọi API (`/api/v2`) và webhook SePay vì chúng là "bot".
5. **Rules → Configuration Rules** → tạo rule: *URI Path starts with `/api/`* → **Browser Integrity Check: Off**, **Security Level: Essentially Off**.
6. **Caching**: để mặc định. **Không** bật "Cache Everything" (sẽ cache trang đã đăng nhập).

Đợi DNS cập nhật vài phút, rồi mở `https://example.com`.

## 13. Cấu hình lần đầu trong trang Admin

Đăng nhập `https://example.com/admin` bằng `ADMIN_EMAIL` / `ADMIN_PASSWORD`, rồi:

1. **System → Website settings**
   - *General*: tên web, khẩu hiệu, logo, favicon.
   - *SEO*: mô tả, ảnh chia sẻ; bật "Allow search engines" khi sẵn sàng.
   - *Email*: nhập SMTP → **Save** → bấm **Send test email** (cần cho chức năng quên mật khẩu).
   - *Legal pages*, *Landing page*, *Contact & footer*.
2. **Services → Providers** → thêm provider (API URL + key) → **Import new services**.
3. **Services → Categories / Services**: sắp xếp, sửa tên, giá.
4. **Finance → Payment methods**: nhập tài khoản SePay / ví USDT, bật kênh.
   - SePay: webhook URL hiển thị ngay trong form, dán vào SePay (chứng thực kiểu *API Key*).
5. **Customers → Levels**: chỉnh % giảm giá theo cấp.
6. Đổi mật khẩu admin (Customers → Customers) nếu vẫn dùng mật khẩu lúc cài.

## 14. Kiểm tra sau khi cài

```bash
curl -I -H "Host: example.com" http://127.0.0.1          # 200 hoặc 302 (chạy trên VPS)
systemctl is-active php-fpm nginx mysqld smm-queue crond # tất cả "active"
sudo -u smm php /var/www/smm/artisan about --only=environment
tail -n 50 /var/www/smm/storage/logs/laravel.log         # không có lỗi mới
```

Trên trình duyệt: trang chủ, `/pricing`, đăng ký tài khoản, quên mật khẩu (nhận được email), `/admin`.

## 15. Cập nhật phiên bản mới

```bash
cd /var/www/smm
sudo -u smm ./scripts/deploy.sh
```

Script sẽ: bật bảo trì → `git pull` → cài thư viện → build CSS/JS → migrate → tối ưu cache → khởi động lại queue → tắt bảo trì.
(Nếu upload bằng rsync thay vì git: bỏ dòng `git pull` trong script hoặc chạy các lệnh còn lại bằng tay.)

## 16. Sao lưu tự động

```bash
cat > /root/.my.cnf <<'EOF'
[mysqldump]
user=root
password=MAT_KHAU_ROOT_MYSQL
EOF
chmod 600 /root/.my.cnf

cat > /usr/local/bin/smm-backup.sh <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
DIR=/var/backups/smm
mkdir -p "$DIR"
mysqldump --single-transaction --routines smm | gzip > "$DIR/db-$(date +%F).sql.gz"
tar czf "$DIR/uploads-$(date +%F).tar.gz" -C /var/www/smm/storage/app public
find "$DIR" -type f -mtime +14 -delete
EOF
chmod +x /usr/local/bin/smm-backup.sh

echo '30 3 * * * /usr/local/bin/smm-backup.sh' | crontab -
```

> Nên chép thư mục `/var/backups/smm` ra nơi khác (Google Drive, S3, VPS khác) định kỳ – backup nằm cùng VPS sẽ mất nếu VPS hỏng.
> Giữ bản sao `.env` (đặc biệt `APP_KEY`) ở nơi an toàn: mất `APP_KEY` thì không giải mã được API key provider và mật khẩu SMTP đã lưu.

## 17. Xử lý sự cố

| Hiện tượng | Nguyên nhân thường gặp | Cách xử lý |
|---|---|---|
| 502 Bad Gateway | PHP-FPM không chạy / sai quyền socket | `systemctl status php-fpm`; kiểm tra `listen.owner/group` ở bước 2 |
| 500 Server Error | Lỗi ứng dụng | Xem `storage/logs/laravel.log` |
| 500 + "Permission denied" trong log | SELinux / quyền thư mục | Làm lại bước 8; xem `ausearch -m avc -ts recent` |
| Không gọi được provider / không gửi được mail | SELinux chặn kết nối ra ngoài | `setsebool -P httpd_can_network_connect 1` |
| Trang bị lỗi CSS, cảnh báo "mixed content" | Thiếu `TRUSTED_PROXIES` / `APP_URL` không phải https | Sửa `.env` rồi `php artisan optimize` |
| Đơn đứng mãi ở Pending | Queue worker không chạy | `systemctl status smm-queue`, `journalctl -u smm-queue -n 50` |
| Trạng thái đơn không cập nhật | Cron scheduler không chạy | `crontab -u smm -l`, `systemctl status crond` |
| Đại lý báo gọi API bị chặn (403 / trang Cloudflare) | Bot Fight Mode / Browser Integrity Check | Làm lại bước 12.4 – 12.5 |
| Sửa `.env` nhưng không có tác dụng | Config đang được cache | `sudo -u smm php artisan optimize` rồi `systemctl restart php-fpm smm-queue` |

## 18. Checklist bảo mật

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Đã xoá `ADMIN_PASSWORD` khỏi `.env`, mật khẩu admin mạnh
- [ ] `SANDBOX_PROVIDER_ENABLED=false`
- [ ] Firewall chỉ mở SSH + port 80 cho Cloudflare (bước 10)
- [ ] Đăng nhập SSH bằng key, tắt đăng nhập root bằng mật khẩu (`PasswordAuthentication no` trong `/etc/ssh/sshd_config`)
- [ ] Bật cập nhật bảo mật tự động: `dnf install -y dnf-automatic && systemctl enable --now dnf-automatic-install.timer`
- [ ] Backup tự động đã chạy (bước 16) và có bản sao ở nơi khác

---

## Phụ lục A – Nâng lên SSL Full (strict)

Mã hoá cả đoạn Cloudflare ↔ VPS bằng chứng chỉ miễn phí của Cloudflare:

1. Cloudflare → **SSL/TLS → Origin Server → Create Certificate** (mặc định 15 năm). Lưu 2 khối nội dung vào:
   - `/etc/pki/nginx/example.com.pem` (Origin Certificate)
   - `/etc/pki/nginx/private/example.com.key` (Private Key), `chmod 600`
2. Trong `/etc/nginx/conf.d/smm.conf` thêm vào khối `server`:
   ```nginx
   listen 443 ssl;
   listen [::]:443 ssl;
   http2 on;
   ssl_certificate     /etc/pki/nginx/example.com.pem;
   ssl_certificate_key /etc/pki/nginx/private/example.com.key;
   ```
3. Firewall: lặp lại 2 lệnh `--add-rich-rule` ở bước 10 với `port="443"`, rồi `firewall-cmd --reload`.
4. `nginx -t && systemctl reload nginx`, sau đó đổi Cloudflare **SSL/TLS → Overview** sang **Full (strict)**.
