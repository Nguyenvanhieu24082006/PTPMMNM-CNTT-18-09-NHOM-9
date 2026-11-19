        </div>
    </div>

    <footer class="main-footer">
        <div>
            <strong>Copyright &copy; 2026 <a href="/admin/index.php">PhuKienXeVIP Pro</a>.</strong>
            Hệ thống Quản trị Bán hàng & Đổi trả (Đề tài 19).
        </div>
        <div>
            <b>Phiên bản</b> 3.2 Pro
        </div>
    </footer>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sidebar-collapse');
        });
    }

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
