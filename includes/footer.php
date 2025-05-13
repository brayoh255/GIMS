    <?php if (isLoggedIn()): ?>
    <footer class="main-footer">
        <div class="footer-content">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> v<?= APP_VERSION ?></p>
            <p>Developed by Your Company</p>
        </div>
    </footer>
    <?php endif; ?>
    
    <script src="<?= ASSETS_PATH ?>js/main.js"></script>
    <?php if (isset($customJS)): ?>
    <script src="<?= ASSETS_PATH ?>js/<?= $customJS ?>"></script>
    <?php endif; ?>
</body>
</html>