<?php
session_start();
require 'con_db.php';
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

require_permission('notificaciones', PERM_READ);

// --- Parámetros ---
$filtro = $_GET['tipo'] ?? 'clientes';
$limite = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// --- Selección de consulta ---

        $tabla = "tbl_calendario ca
                  JOIN tbl_cliente c ON ca.id_cliente = c.id_cliente
                  JOIN tbl_estado_calendario ec ON ca.id_estadoCalendario = ec.id_estadoCalendario";
        $campos = "c.nombre_cliente AS Cliente,
                   ca.contenido AS Contenido,
                   ec.nombre_estado AS Estado,
                   ca.fecha_notificacion AS Fecha,
                   ca.hora_notificacion AS Hora";

// --- Paginación ---
$total = $conexion->query("SELECT COUNT(*) AS total FROM $tabla")->fetch_assoc()['total'];
$total_paginas = ceil($total / $limite);
$query = "SELECT $campos FROM $tabla LIMIT $limite OFFSET $offset";
$result = $conexion->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Notificaciones - Config</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
<style>
:root {
    --naranja: #f0ad4e;
    --naranja-oscuro: #ec971f;
    --fondo: #f8f8f8;
    --texto: #333;
}
body {
    font-family: 'Segoe UI', sans-serif;
    background: var(--fondo);
    margin: 0;
    padding: 20px;
}
.container {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,.1);
}
h1 {
    color: var(--naranja-oscuro);
    margin-bottom: 20px;
}
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
}
select {
    padding: 8px 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 15px;
}
th, td {
    padding: 12px 15px;
    border-bottom: 1px solid #ddd;
    text-align: center;
    vertical-align: middle;
}
th {
    background: var(--naranja-oscuro);
    color: white;
    font-weight: bold;
}
tr:nth-child(even) {
    background-color: #fdf5e6;
}
tr:hover {
    background: #f8e5c4;
    transition: background-color 0.3s ease;
}
/* --- BOTONES --- */
.btn-export {
    background: #5cb85c;
    color: white;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
}
.btn-export:hover { background: #4cae4c; }
.btn-pdf {
    background: #d9534f;
    color: white;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
}
.btn-pdf:hover { background: #c9302c; }
.back {
    color: var(--naranja-oscuro);
    text-decoration: none;
    font-weight: bold;
}
.pagination {
    margin-top: 10px;
    text-align: center;
}
.pagination a {
    padding: 6px 12px;
    margin: 2px;
    background: #eee;
    border-radius: 5px;
    text-decoration: none;
    color: var(--texto);
}
.pagination a.active {
    background: var(--naranja-oscuro);
    color: white;
}
.pagination a:hover {
    background: var(--naranja);
}
</style>
</head>
<body>

<a href="menuprincipal.php" class="back"><i class="fas fa-arrow-left"></i> Volver al Menú </a>

<div class="container">
<h1><i class="fas fa-chart-bar"></i> Reporte de notificaciones</h1>

<form method="GET" class="filter-bar">
    <?php $filtro="notificaciones" ?>
    <div>
        <a href="exportar_pdf.php?tipo=<?= $filtro ?>" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
    </div>
</form>

<div id="tablaReportes">
<table>
<thead><tr>
<?php foreach(array_keys($result->fetch_assoc() ?? []) as $col){echo "<th>$col</th>";}$result->data_seek(0);?>
</tr></thead>
<tbody>
<?php if($result->num_rows>0): while($row=$result->fetch_assoc()): ?>
<tr><?php foreach($row as $campo){echo "<td>".htmlspecialchars($campo)."</td>";} ?></tr>
<?php endwhile; else: ?>
<tr><td colspan="10" style="text-align:center;">No hay registros.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php if ($total_paginas > 1): ?>
<div class="pagination">
<?php for($i=1;$i<=$total_paginas;$i++): ?>
    <a href="?tipo=<?= $filtro ?>&pagina=<?= $i ?>" class="<?= $i==$pagina?'active':'' ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>

</div>

<script>
// Refrescar automáticamente cada 5 segundos
setInterval(() => {
    const tipo = document.querySelector('select[name="tipo"]').value;
    fetch(`tabla_reportes.php?tipo=${tipo}`)
        .then(res => res.text())
        .then(html => {
            document.getElementById('tablaReportes').innerHTML = html;
        });
}, 5000);
</script>

</body>
</html>
