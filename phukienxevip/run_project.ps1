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
    default {
        Write-Host "Cách dùng:" -ForegroundColor Yellow
        Write-Host "  .\run_project.ps1 setup : Khởi tạo 10 bảng và nạp 100 đơn hàng mẫu"
        Write-Host "  .\run_project.ps1 test  : Chạy 5 phép thử tự động bắt buộc"
        Write-Host "  .\run_project.ps1 run   : Mở web server tại http://127.0.0.1:$port"
    }
}

