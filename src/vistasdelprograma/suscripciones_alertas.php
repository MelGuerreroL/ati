<?php
require_once __DIR__ . '/../config/errorlogs.php';
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();
session_start();
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

require_permission('suscripciones', PERM_READ);
require 'con_db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alertas de Suscripciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <style>
        :root {
            --blue-primary: #1E88E5;   /* Azul elegante principal */
            --blue-dark: #1565C0;      /* Azul profundo para hover */
            --blue-light: #90CAF9;     /* Azul claro para acentos */
            --text-dark: #2E2E2E;
            --text-light: #fff;
            --background-light: #ffffff;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--background-light);
            margin: 0;
            padding: 20px;
            color: var(--text-dark);
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: var(--text-dark);
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        
        .back-button {
            background: var(--blue-primary);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }
        
        .back-button:hover {
            background: var(--blue-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card h5 {
            color: var(--blue-primary);
            border-bottom: 2px solid var(--blue-primary);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background-color: var(--blue-primary);
            border-color: var(--blue-primary);
            color: white;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--blue-dark);
            border-color: var(--blue-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        }
        
        .btn-warning {
            background-color: var(--blue-light);
            border-color: var(--blue-light);
            color: var(--text-dark);
            font-weight: 600;
        }
        
        .btn-warning:hover {
            background-color: var(--blue-primary);
            border-color: var(--blue-primary);
            color: var(--text-light);
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        }
        
        .btn-info {
            background-color: var(--blue-light);
            border-color: var(--blue-light);
            color: var(--text-dark);
            font-weight: 600;
        }
        
        .btn-info:hover {
            background-color: var(--blue-primary);
            border-color: var(--blue-primary);
            color: var(--text-light);
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        }
        
        .btn-success {
            background-color: var(--blue-primary);
            border-color: var(--blue-primary);
            color: white;
            font-weight: 600;
        }
        
        .btn-success:hover {
            background-color: var(--blue-dark);
            border-color: var(--blue-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        }
        
        .btn-outline-primary {
            border-color: var(--blue-primary);
            color: var(--blue-primary);
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--blue-primary);
            border-color: var(--blue-primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .btn-sm:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }
        
        .alert-item {
            border-left: 4px solid var(--blue-primary);
            margin-bottom: 10px;
        }
        
        .alert-vencida {
            border-left-color: #dc3545;
        }
        
        .alert-proxima {
            border-left-color: #ffc107;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--blue-primary);
        }
        
        .stat-card .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .list-group-item {
            background: transparent;
            border: none;
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
        }
</style>

</head>
<body>

<div class="main-container">
    <div class="page-header">
        <h1><i class="fas fa-exclamation-triangle"></i> Alertas de Suscripciones</h1>
        <a href="pre_suscripciones.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Volver a Gestión de Suscripciones
        </a>
    </div>

    <!-- Estadísticas generales -->
    <div class="stats-grid">
        <div class="stat-card">
            <div id="statVencidas" class="stat-number">0</div>
            <div class="stat-label">Vencidas</div>
        </div>
        <div class="stat-card">
            <div id="statProximas" class="stat-number">0</div>
            <div class="stat-label">Por Vencer (7 días)</div>
        </div>
        <div class="stat-card">
            <div id="statActivas" class="stat-number">0</div>
            <div class="stat-label">Activas</div>
        </div>
        <div class="stat-card">
            <div id="statTotal" class="stat-number">0</div>
            <div class="stat-label">Total Suscripciones</div>
        </div>
    </div>

    <div class="row">
        <!-- Gráfico de resumen -->
        <div class="col-lg-6">
            <div class="card p-3">
                <h5><i class="fas fa-chart-bar"></i> Resumen Gráfico</h5>
                <div class="d-flex mb-3 align-items-center gap-3">
                    <select id="periodoSelect" class="form-select w-auto">
                        <option value="month" selected>Este mes</option>
                        <option value="3months">Últimos 3 meses</option>
                        <option value="year">Último año</option>
                        <option value="all">Todo</option>
                    </select>
                    <small class="text-muted">Suscriptores por plan</small>
                    <button id="btnRefreshChart" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="planesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Alertas próximas -->
        <div class="col-lg-6">
            <div class="card p-3">
                <h5><i class="fas fa-bell"></i> Alertas Próximas</h5>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small class="text-muted">Suscripciones próximas a vencer o ya vencidas</small>
                    <button id="btnRefreshAlerts" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <ul id="alertsList" class="list-group">
                        <li class="list-group-item text-center">
                            <i class="fas fa-spinner fa-spin"></i> Cargando alertas...
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="card p-3 mt-4">
        <h5><i class="fas fa-tools"></i> Acciones Rápidas</h5>
        <div class="d-flex gap-2 flex-wrap">
            <button id="btnGenerateReminders" class="btn btn-warning">
                <i class="fas fa-envelope"></i> Generar Recordatorios
            </button>
            <button id="btnUpdateStatuses" class="btn btn-info">
                <i class="fas fa-refresh"></i> Actualizar Estados
            </button>
            <a href="suscripciones_registradas.php" class="btn btn-primary">
                <i class="fas fa-table"></i> Ver Todas las Suscripciones
            </a>
            <a href="suscripciones_agregar.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Agregar Nueva Suscripción
            </a>
        </div>
    </div>

    <!-- Tabla de alertas detallada -->
    <div class="card p-3 mt-4">
        <h5><i class="fas fa-list-alt"></i> Detalle de Alertas</h5>
        <div class="table-responsive">
            <table id="alertsTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID Suscripción</th>
                        <th>Cliente</th>
                        <th>Plan</th>
                        <th>Fecha Vencimiento</th>
                        <th>Estado</th>
                        <th>Días Restantes</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Se cargará dinámicamente -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let planesChart;

$(document).ready(function() {
    // Cargar datos iniciales
    loadStats();
    drawChart();
    loadAlerts();
    loadAlertsTable();
    
    // Configurar event handlers
    setupEventHandlers();
    
    // Auto-refresh cada 5 minutos
    setInterval(function() {
        loadStats();
        loadAlerts();
        loadAlertsTable();
    }, 300000); // 5 minutos
});

function setupEventHandlers() {
    // Cambio de período en gráfico
    $('#periodoSelect').on('change', drawChart);
    
    // Botones de actualización
    $('#btnRefreshChart').on('click', drawChart);
    $('#btnRefreshAlerts').on('click', function() {
        loadAlerts();
        loadAlertsTable();
    });
    
    // Generar recordatorios
    $('#btnGenerateReminders').on('click', function() {
        if (confirm('¿Generar recordatorios automáticos para suscripciones próximas a vencer?')) {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generando...');
            
            $.post('suscripciones_actions.php?action=generate_reminders', {})
                .done(function(response) {
                    if (response.success) {
                        alert('Recordatorios generados: ' + response.inserted);
                        loadStats();
                        loadAlerts();
                        loadAlertsTable();
                    } else {
                        alert('Error: ' + response.error);
                    }
                })
                .fail(function() {
                    alert('Error de conexión al servidor');
                })
                .always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-envelope"></i> Generar Recordatorios');
                });
        }
    });
    
    // Actualizar estados
    $('#btnUpdateStatuses').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Actualizando...');
        
        $.post('suscripciones_actions.php?action=update_auto_status', {})
            .done(function(response) {
                alert('Estados actualizados exitosamente');
                loadStats();
                loadAlerts();
                loadAlertsTable();
            })
            .fail(function() {
                alert('Error al actualizar estados');
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="fas fa-refresh"></i> Actualizar Estados');
            });
    });
    
    // Acciones de tabla
    $(document).on('click', '.btn-activate-alert', function() {
        const id = $(this).data('id');
        activateSubscription(id);
    });
    
    $(document).on('click', '.btn-cancel-alert', function() {
        const id = $(this).data('id');
        cancelSubscription(id);
    });
}

function loadStats() {
    $.getJSON('suscripciones_actions.php?action=stats', function(stats) {
        $('#statVencidas').text(stats.vencidas || 0);
        $('#statProximas').text(stats.proximas || 0);
        $('#statActivas').text(stats.activas || 0);
        $('#statTotal').text(stats.total || 0);
    }).fail(function() {
        console.error('Error al cargar estadísticas');
    });
}

function drawChart() {
    const periodo = $('#periodoSelect').val();
    
    $.getJSON('suscripciones_actions.php?action=summary&period=' + periodo, function(data) {
        const labels = data.map(d => d.nombre_plan);
        const vals = data.map(d => parseInt(d.total));
        
        const ctx = document.getElementById('planesChart').getContext('2d');
        
        if (planesChart) {
            planesChart.destroy();
        }
        
        planesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Suscriptores',
                    data: vals,
                    backgroundColor: [
                        'rgba(255, 170, 102, 0.8)', // Naranja principal
                        'rgba(255, 165, 0, 0.8)',   // Naranja de acento
                        'rgba(255, 140, 0, 0.8)',   // Naranja oscuro
                        'rgba(255, 185, 51, 0.8)',  // Naranja claro
                        'rgba(255, 195, 77, 0.8)',  // Naranja suave
                        'rgba(255, 205, 102, 0.8)', // Naranja muy claro
                        'rgba(255, 215, 128, 0.8)'  // Naranja pastel
                    ],
                    borderColor: [
                        'rgba(255, 170, 102, 1)',   // Naranja principal
                        'rgba(255, 165, 0, 1)',     // Naranja de acento
                        'rgba(255, 140, 0, 1)',     // Naranja oscuro
                        'rgba(255, 185, 51, 1)',    // Naranja claro
                        'rgba(255, 195, 77, 1)',    // Naranja suave
                        'rgba(255, 205, 102, 1)',   // Naranja muy claro
                        'rgba(255, 215, 128, 1)'    // Naranja pastel
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Distribución de Suscripciones por Plan'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });
    }).fail(function() {
        console.error('Error al cargar datos del gráfico');
    });
}

function loadAlerts() {
    $.getJSON('suscripciones_actions.php?action=alerts', function(list) {
        const $l = $('#alertsList');
        $l.empty();
        
        if (list.length === 0) {
            $l.append(`
                <li class="list-group-item text-center text-success">
                    <i class="fas fa-check-circle"></i> No hay alertas pendientes
                </li>
            `);
            return;
        }
        
        for (const item of list) {
            const estado = item.nombre_estado || '';
            let estadoBadge = '';
            let alertClass = 'alert-item';
            
            if (estado === 'VENCIDA') {
                estadoBadge = `<span class="badge bg-danger">${estado}</span>`;
                alertClass += ' alert-vencida';
            } else {
                estadoBadge = `<span class="badge bg-warning text-dark">${item.nombre_plan}</span>`;
                alertClass += ' alert-proxima';
            }
            
            $l.append(`
                <li class="list-group-item ${alertClass}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${item.nombre_cliente.toUpperCase()}</strong><br>
                            <small class="text-muted">Vence: ${item.fecha_fin}</small>
                        </div>
                        ${estadoBadge}
                    </div>
                </li>
            `);
        }
    }).fail(function() {
        $('#alertsList').html(`
            <li class="list-group-item text-center text-danger">
                <i class="fas fa-exclamation-triangle"></i> Error al cargar alertas
            </li>
        `);
    });
}

function loadAlertsTable() {
    $.getJSON('suscripciones_actions.php?action=alerts_detailed', function(list) {
        const tbody = $('#alertsTable tbody');
        tbody.empty();
        
        if (list.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="7" class="text-center text-success">
                        <i class="fas fa-check-circle"></i> No hay alertas pendientes
                    </td>
                </tr>
            `);
            return;
        }
        
        for (const item of list) {
            const diasRestantes = item.dias_restantes || 0;
            const estado = item.nombre_estado || '';
            
            let estadoBadge = '';
            let diasBadge = '';
            
            if (estado === 'VENCIDA') {
                estadoBadge = `<span class="badge bg-danger">${estado}</span>`;
                diasBadge = `<span class="badge bg-danger">${Math.abs(diasRestantes)} días vencida</span>`;
            } else if (diasRestantes <= 3) {
                estadoBadge = `<span class="badge bg-warning text-dark">${estado}</span>`;
                diasBadge = `<span class="badge bg-warning text-dark">${diasRestantes} días</span>`;
            } else {
                estadoBadge = `<span class="badge bg-warning text-dark">${estado}</span>`;
                diasBadge = `<span class="badge bg-warning text-dark">${diasRestantes} días</span>`;
            }
            
            tbody.append(`
                <tr>
                    <td>${item.id_suscripcion}</td>
                    <td>${item.nombre_cliente.toUpperCase()}</td>
                    <td>${item.nombre_plan || 'Sin plan'}</td>
                    <td>${item.fecha_fin}</td>
                    <td>${estadoBadge}</td>
                    <td>${diasBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-success btn-activate-alert" data-id="${item.id_suscripcion}" title="Activar">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-danger btn-cancel-alert" data-id="${item.id_suscripcion}" title="Cancelar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `);
        }
    }).fail(function() {
        $('#alertsTable tbody').html(`
            <tr>
                <td colspan="7" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error al cargar datos detallados
                </td>
            </tr>
        `);
    });
}

function activateSubscription(id) {
    if (confirm('¿Activar esta suscripción?')) {
        $.post('suscripciones_actions.php?action=activate', {id: id})
            .done(function(response) {
                if (response.success) {
                    alert('Suscripción activada exitosamente');
                    loadStats();
                    loadAlerts();
                    loadAlertsTable();
                } else {
                    alert('Error: ' + response.error);
                }
            })
            .fail(function() {
                alert('Error de conexión al servidor');
            });
    }
}

function cancelSubscription(id) {
    if (confirm('¿Cancelar esta suscripción?')) {
        $.post('suscripciones_actions.php?action=cancel', {id: id})
            .done(function(response) {
                if (response.success) {
                    alert('Suscripción cancelada exitosamente');
                    loadStats();
                    loadAlerts();
                    loadAlertsTable();
                } else {
                    alert('Error: ' + response.error);
                }
            })
            .fail(function() {
                alert('Error de conexión al servidor');
            });
    }
}
</script>

</body>
</html>