<?php include __DIR__ . '/../layouts/header.php'; ?>
<?php include __DIR__ . '/../layouts/nav.php'; ?>

<div class="container py-4">
    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success text-center"><h1>✅</h1><h3>¡Reserva Confirmada!</h3><a href="index.php" class="btn btn-primary mt-3">Volver</a></div>
    <?php else: ?>
        <div class="card shadow border-0 p-4 mx-auto" style="max-width:800px;">
            <h3 class="mb-4">Nueva Reserva</h3>
            <?php if(isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST" action="index.php?route=process_booking">
                <!-- Sucursal -->
                <label class="fw-bold mb-2">Sucursal</label>
                <div class="row g-2 mb-3">
                    <?php foreach($branches as $b): ?>
                    <div class="col-6"><input type="radio" class="btn-check" name="branch_id" id="b<?php echo $b['id']; ?>" value="<?php echo $b['id']; ?>" required>
                    <label class="btn btn-outline-dark w-100" for="b<?php echo $b['id']; ?>"><?php echo $b['name']; ?></label></div>
                    <?php endforeach; ?>
                </div>

                <!-- Datos -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label>Vehículo</label><select name="vehicle_type" class="form-select"><option value="Utilitario">Utilitario</option><option value="Chasis">Chasis</option><option value="Balancin">Balancín</option><option value="Semi_Acoplado">Semi</option></select></div>
                    <div class="col-md-6"><label>Bultos</label><input type="number" name="quantity" class="form-control" required></div>
                </div>

                <!-- Chofer -->
                <div class="row g-3 mb-3 bg-light p-2 rounded">
                    <div class="col-md-6"><input type="text" name="driver_name" class="form-control" placeholder="Nombre Chofer" required></div>
                    <div class="col-md-6"><input type="text" name="driver_dni" class="form-control" placeholder="DNI Chofer" required></div>
                </div>

                <!-- Fecha -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><input type="date" name="date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="col-md-6"><select name="time" class="form-select"><?php for($i=8;$i<=17;$i++) echo "<option>".sprintf("%02d:00",$i)."</option>"; ?></select></div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="request_recurring" class="form-check-input" id="rec">
                    <label class="form-check-label" for="rec">Solicitar como Fijo Semanal</label>
                </div>

                <button class="btn btn-primary w-100 py-3 fw-bold">CONFIRMAR</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>