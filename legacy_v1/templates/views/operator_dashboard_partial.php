<!-- templates/views/operator_dashboard_partial.php -->
<!-- Este archivo se usa tanto en el dashboard del operario como en el del admin -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Horario</th>
                    <th>Transporte / Chofer</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">No hay turnos para esta fecha.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $appt): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="fs-5 fw-bold font-monospace"><?php echo date('H:i', strtotime($appt['start_time'])); ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($appt['company_name']); ?></div>
                            <div class="small text-muted">
                                <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($appt['driver_name']); ?> 
                                • <?php echo $appt['vehicle_type']; ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                $st = $appt['status'];
                                $badge = match($st) {
                                    'reserved' => ['bg-primary-subtle text-primary border-primary', 'En Camino'],
                                    'arrived' => ['bg-warning-subtle text-warning border-warning', 'En Puerta'],
                                    'in_progress' => ['bg-success text-white', 'En Dársena'],
                                    'completed' => ['bg-secondary text-white', 'Finalizado'],
                                    default => ['bg-light text-muted', $st]
                                };
                            ?>
                            <span class="badge rounded-pill border <?php echo $badge[0]; ?> px-3 py-2">
                                <?php echo $badge[1]; ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <?php if (!isset($readOnly)): ?>
                                <form method="POST" action="index.php?route=operator_action">
                                    <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                                    
                                    <?php if ($st === 'reserved'): ?>
                                        <button name="action" value="arrive" class="btn btn-sm btn-outline-dark fw-bold">
                                            <i class="bi bi-geo-alt-fill me-1"></i> LLEGÓ
                                        </button>
                                    <?php elseif ($st === 'arrived'): ?>
                                        <button name="action" value="enter" class="btn btn-sm btn-success fw-bold">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> INGRESAR
                                        </button>
                                    <?php elseif ($st === 'in_progress'): ?>
                                        <button name="action" value="complete" class="btn btn-sm btn-secondary">
                                            <i class="bi bi-check-lg me-1"></i> SALIDA
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>