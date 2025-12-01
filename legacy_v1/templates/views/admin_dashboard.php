<?php include __DIR__ . '/../layouts/header.php'; ?>
<?php include __DIR__ . '/../layouts/nav.php'; ?>

<div class="container py-4">
    <div class="row mb-4"><div class="col"><h2 class="fw-bold">Panel Maestro</h2></div></div>
    
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aprob">Aprobaciones</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#live">Agenda en Vivo</button></li>
    </ul>

    <div class="tab-content">
        <!-- Aprobaciones -->
        <div class="tab-pane fade show active" id="aprob">
            <div class="card shadow-sm border-0"><div class="card-body">
                <?php if(empty($pending)): ?><p class="text-muted">No hay pendientes.</p><?php else: ?>
                <table class="table">
                    <?php foreach($pending as $p): ?>
                    <tr>
                        <td><?php echo date('d/m H:i', strtotime($p['start_time'])); ?></td>
                        <td><?php echo $p['company_name']; ?></td>
                        <td><form method="POST" action="index.php?route=admin_approve">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button class="btn btn-sm btn-success">Aprobar</button>
                        </form></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div></div>
        </div>
        <!-- Agenda -->
        <div class="tab-pane fade" id="live">
            <div class="btn-group mb-3">
                <a href="index.php?route=admin&branch=1" class="btn btn-outline-dark">Rivadavia</a>
                <a href="index.php?route=admin&branch=2" class="btn btn-outline-dark">Monte de Oca</a>
            </div>
            <?php $appointments = $agenda; $readOnly = true; include __DIR__ . '/operator_dashboard_partial.php'; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>