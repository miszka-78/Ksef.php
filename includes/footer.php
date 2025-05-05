    </main>
    
    <footer class="bg-light text-muted py-3 mt-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?= date('Y') ?> KSeF Invoice Manager</p>
                    <p class="mb-0">Version <?= APP_VERSION ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <?php if (isAuthenticated()): ?>
                    <p class="mb-0">Logged in as: <?= sanitize($_SESSION['user_name']) ?> (<?= sanitize($_SESSION['username']) ?>)</p>
                    <?php else: ?>
                    <p class="mb-0">Not logged in</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/script.js"></script>
</body>
</html>
