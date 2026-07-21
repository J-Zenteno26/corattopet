        </main>
    </div>
    <footer class="admin-footer">
        <p>&copy; <?= escape(date('Y')) ?> Coratto Pet. Panel administrativo.</p>
    </footer>
    <?php
    require_once __DIR__ . '/admin-flash.php';
    $adminModalFlash = consumirModalAdmin();
    if (!isset($adminModal)) {
        $adminModal = $adminModalFlash;
    }
    require __DIR__ . '/admin-modal.php';
    ?>
    <script src="<?= escape(appUrl('public/js/admin.js')) ?>" defer></script>
</body>
</html>
