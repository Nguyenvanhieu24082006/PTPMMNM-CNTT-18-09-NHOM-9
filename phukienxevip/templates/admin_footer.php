        </div><!-- /.content-body -->
    </div><!-- /.content-wrapper -->

    <!-- ==============================================================================
         4. MAIN FOOTER (CHÂN TRANG QUẢN TRỊ NỘI BỘ)
         ============================================================================== -->
    <footer class="main-footer">
        <div>
            <strong>Copyright &copy; 2026 <a href="/admin/index.php">PhuKienXeVIP Pro</a>.</strong>
            Hệ thống Quản trị Bán hàng & Đổi trả (Đề tài 19).
        </div>
        <div>
            <b>Phiên bản</b> 3.2 Pro
        </div>
    </footer>

</div><!-- /.wrapper -->

<script>
// Toggle thu gọn/mở rộng sidebar khi click nút Hamburger
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sidebar-collapse');
        });
    }

    // Toggle chế độ toàn màn hình
    const fsBtn = document.getElementById('fullscreenToggleBtn');
    if (fsBtn) {
        fsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        });
    }
});
</script>
</body>
</html>
