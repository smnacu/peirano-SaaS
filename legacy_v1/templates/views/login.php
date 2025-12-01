<?php include __DIR__ . '/../layouts/header.php'; ?>
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow p-4" style="max-width:400px; width:100%;">
        <div class="text-center mb-4">
            <h4 class="fw-bold">Peirano Logística</h4>
            <p class="text-muted">Acceso al Sistema</p>
        </div>
        <?php if (isset($error)): ?><div class="alert alert-danger py-2"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST" action="index.php?route=do_login">
            <div class="mb-3"><label>Usuario/CUIT</label><input type="text" name="cuit" class="form-control" required autofocus></div>
            <div class="mb-3"><label>Contraseña</label><input type="password" name="password" class="form-control" required></div>
            <button class="btn btn-primary w-100 fw-bold">INGRESAR</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>