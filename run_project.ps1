# PowerShell helper script cho Đề tài 19 (Windows)
param (
    [string]$Action = "help"
)

$phpPath = "C:\xampp\php\php.exe"
if (-not (Test-Path $phpPath)) {
    $phpPath = (Get-Command php -ErrorAction SilentlyContinue).Source
}

if (-not $phpPath) {
    Write-Host "LỖI: Không tìm thấy php.exe trong C:\xampp\php\ hoặc PATH." -ForegroundColor Red
    exit 1
}

$port = 8105

switch ($Action.ToLower()) {
    "setup" {
        Write-Host "-> Khởi tạo Database và nạp 100 đơn hàng seed..." -ForegroundColor Cyan
        & $phpPath "tools/setup-project.php"
    }
    "test" {
        Write-Host "-> Chạy bộ kiểm thử tự động 5 phép thử..." -ForegroundColor Cyan
        & $phpPath "tests/test_suite.php"
    }
    "run" {
        Write-Host "-> Khởi động máy chủ Web PHP tại http://127.0.0.1:$port" -ForegroundColor Green
        Write-Host "Nhấn Ctrl+C để dừng máy chủ." -ForegroundColor Yellow
        if (-not (Test-Path ".local\sessions")) {
            New-Item -ItemType Directory -Force -Path ".local\sessions" | Out-Null
        }
        & $phpPath -d "session.name=COMMUNITYSHOP" -d "session.save_path=.local/sessions" -S "127.0.0.1:$port" -t public
    }
    "tunnel" {
        Write-Host "-> Đang kiểm tra công cụ tạo đường link công khai (Public URL)..." -ForegroundColor Cyan
        $cfPath = "C:\Program Files (x86)\cloudflared\cloudflared.exe"
        if (-not (Test-Path $cfPath)) {
            $cfPath = "C:\Program Files\cloudflared\cloudflared.exe"
        }
        if (-not (Test-Path $cfPath)) {
            $cfCmd = Get-Command cloudflared -ErrorAction SilentlyContinue
            if ($cfCmd) { $cfPath = $cfCmd.Source }
        }

        if (Test-Path $cfPath) {
            Write-Host "-> Khởi chạy Cloudflare Quick Tunnel cho cổng $port..." -ForegroundColor Green
            Write-Host "Sao chép đường link https://...trycloudflare.com xuất hiện bên dưới để gửi:" -ForegroundColor Yellow
            & $cfPath tunnel --url "http://127.0.0.1:$port"
        } else {
            $ssh = Get-Command ssh -ErrorAction SilentlyContinue
            if ($ssh) {
                Write-Host "-> Chưa cài cloudflared, sử dụng đường hầm SSH tích hợp sẵn của Windows (Pinggy)..." -ForegroundColor Yellow
                Write-Host "Mẹo: Bạn có thể cài Cloudflare Tunnel xịn hơn bằng lệnh: winget install --id Cloudflare.cloudflared" -ForegroundColor Cyan
                Write-Host "Đang tạo đường link HTTPS công khai..." -ForegroundColor Green
                & ssh -o StrictHostKeyChecking=accept-new -p 443 -R0:localhost:$port a.pinggy.io
            } else {
                Write-Host "LỖI: Vui lòng cài đặt Cloudflare Tunnel bằng lệnh: winget install --id Cloudflare.cloudflared" -ForegroundColor Red
            }
        }
    }
    default {
        Write-Host "Cách dùng:" -ForegroundColor Yellow
        Write-Host "  .\run_project.ps1 setup  : Khởi tạo 10 bảng và nạp 100 đơn hàng mẫu"
        Write-Host "  .\run_project.ps1 test   : Chạy 5 phép thử tự động bắt buộc"
        Write-Host "  .\run_project.ps1 run    : Mở web server tại http://127.0.0.1:$port"
        Write-Host "  .\run_project.ps1 tunnel : Tạo đường link HTTPS công khai cho người khác test"
    }
}

