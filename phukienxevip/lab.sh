#!/usr/bin/env bash
# Chỉ dùng cho học tập cục bộ. Script không cài gói, không xóa dữ liệu.
set -u
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)" || exit 2
cd "$root" || exit 2
lesson="5"
port="8105"
usage() {
  printf 'Cách dùng: bash lab.sh check | run | test | setup | db-config | sql\n'
  printf 'check: kiểm tra môi trường; run: chạy web server; test: chạy 5 phép thử bắt buộc.\n'
  printf 'setup: tạo schema và nạp 100 đơn hàng seed; db-config: cấu hình MySQL.\n'
}
need_php() {
  command -v php >/dev/null 2>&1 || { echo 'THIEU_PHP: xem mục Công cụ cần có trong Huong_dan.docx của bài lab'; return 2; }
  php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || { echo 'CAN_PHP_8_1_TRO_LEN'; return 2; }
}
check_env() {
  need_php || return 2
  command -v git >/dev/null 2>&1 || { echo 'THIEU_GIT'; return 2; }
  if [[ "$lesson" == 5 ]]; then
    command -v mysql >/dev/null 2>&1 || { echo 'THIEU_MYSQL: xem tài liệu chuẩn bị Lab 05'; return 2; }
    php -r 'exit(extension_loaded("mysqli") ? 0 : 1);' || { echo 'THIEU_MYSQLI: cài phần mở rộng đúng phiên bản PHP'; return 2; }
    php tools/db-check.php || return 2
  fi
  printf 'MOI_TRUONG=SAN_SANG | Lab %s\n' "$lesson"
  echo 'Đây không phải kết luận bài tập đã hoàn thành.'
}
case "${1:-help}" in
  check) check_env ;;
  run)
    check_env || exit 2
    if [[ "$lesson" == 2 ]]; then
      php demo.php
    else
      echo "THU_MUC=$root"
      echo "DIA_CHI=http://127.0.0.1:$port"
      echo 'Giữ terminal này mở. Nhấn Ctrl+C để dừng; không có tiến trình chạy nền.'
      mkdir -p .local/sessions || exit 2
      php -d session.name="OSSLAB$lesson" -d session.save_path="$root/.local/sessions" \
        -S "127.0.0.1:$port" -t public
    fi ;;
  test)
    check_env || exit 2
    php tests/test_suite.php ;;
  setup|sql)
    need_php || exit 2
    php tools/setup-project.php ;;
  db-config)
    need_php || exit 2
    php tools/db-config.php ;;
  help|-h|--help) usage ;;
  *) usage; exit 2 ;;
esac
