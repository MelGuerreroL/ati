bitacora.php
<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';

if (empty($_SESSION['usuario_id'])) {
    header("Location: ../../public/index.php?error=Debes iniciar sesión");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
registrar_bitacora($conexion, $id_usuario, 2, "ACCESO", "Ingresó a la pantalla de Bitácora");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Bitácora del Sistema</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<<style>
:root { 
    --azul: #1E88E5;          /* Azul elegante principal */
    --azul-oscuro: #1565C0;   /* Azul profundo para hover */
}
body { 
    background: #f5f5f5; 
    font-family: 'Segoe UI', sans-serif; 
    padding: 25px; 
}

.container {
    background: white; 
    padding: 25px; 
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,.15);
}

h2 { color: var(--azul-oscuro); }
input { 
    padding: 8px; 
    border-radius: 6px; 
    border:1px solid #ccc; 
}

.btn {
    padding: 8px 12px; 
    background: var(--azul-oscuro); 
    color:white;
    text-decoration:none; 
    border-radius:6px; 
    cursor:pointer;
}
.btn:hover { background:var(--azul); }

table { 
    width:100%; 
    border-collapse: collapse; 
    margin-top:15px; 
}
th { 
    background:var(--azul-oscuro); 
    color:white; 
    padding:10px; 
}
td { 
    padding:10px; 
    border-bottom:1px solid #ddd; 
}
tr:nth-child(even) { background:#e8f1fb; }

/* CENTRAR TODAS LAS COLUMNAS MENOS DESCRIPCIÓN */
table td, table th {
    text-align: center;
}

/* columna descripción (última) alineada a la izquierda */
table td:last-child, table th:last-child {
    text-align: left;
}

/* ajustar ancho */
table td:last-child {
    width: 40%;
}

/* PAGINACIÓN */
.pagination {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-top: 15px;
    padding: 10px 0;
}

.pagination a, .pagination span {
    padding: 6px 12px;
    background:#eee;
    border-radius:5px;
    cursor:pointer;
}

.pagination span {
    background: transparent;
    cursor: default;
}

.pagination .active {
    background:var(--azul-oscuro);
    color:white;
    font-weight: bold;
}
</style>

</head>
<body>

<div class="container">
<h2>📘 Bitácora</h2>

<a href="pre_administracion.php" class="btn">⬅ Volver</a>
<br><br>

<div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">
    <div>
        <label>Fecha Inicial:</label><br>
        <input type="date" id="f1">
    </div>

    <div>
        <label>Fecha Final:</label><br>
        <input type="date" id="f2">
    </div>

    <div>
        <label>Buscar:</label><br>
        <input type="text" id="q" placeholder="Buscar por número, acción, descripción...">
    </div>

    <button class="btn" onclick="loadTabla()">Buscar</button>

    <a id="pdfBtn" class="btn" target="_blank">
        <i class="fas fa-file-pdf"></i> PDF
    </a>
</div>

<div id="tablaContainer"></div>
</div>

<script>
function loadTabla(pagina = 1) {
    const f1 = document.getElementById("f1").value || "";
    const f2 = document.getElementById("f2").value || "";
    const q  = document.getElementById("q").value  || "";

    document.getElementById("pdfBtn").href =
        `bitacora_pdf.php?f1=${f1}&f2=${f2}&q=${q}`;

    fetch(`bitacora_controller.php?action=load&pagina=${pagina}&f1=${f1}&f2=${f2}&q=${q}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById("tablaContainer").innerHTML = html;
        });
}

document.addEventListener("DOMContentLoaded", () => loadTabla());
</script>

</body>
</html>




