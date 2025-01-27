<?php
require __DIR__ . '/includes/header.php';
?>

<div class="container text-center mt-5">
    <div>
        <img src="<?= base_url('image/logo.png') ?>" alt="App Logo" class="img-fluid mb-4" style="max-width: 200px;">
    </div>
    <!-- Buttons Section -->
    <div>
        <a href="<?= base_url('login') ?>" class="btn btn-primary btn-lg me-3">Login</a>
        <a href="<?= base_url('register') ?>" class="btn btn-success btn-lg">Register</a>
    </div>
</div>

<?php
require __DIR__ . '/includes/footer.php';
?>