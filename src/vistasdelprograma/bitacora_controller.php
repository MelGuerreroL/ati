<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';

if (empty($_SESSION['usuario_id'])) {
    die("No autorizado.");
}

$action = $_GET['action'] ?? '';
$id_usuario = $_SESSION['usuario_id'];

if ($action == "load") {

    // ===========================
    // SANITIZAR PARÁMETROS
    // ===========================
    $f1 = isset($_GET['f1']) ? $conexion->real_escape_string($_GET['f1']) : "";
    $f2 = isset($_GET['f2']) ? $conexion->real_escape_string($_GET['f2']) : "";
    $q  = isset($_GET['q'])  ? $conexion->real_escape_string($_GET['q'])  : "";

    $pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
    if ($pagina < 1) $pagina = 1;

    $limite = 10;
    $offset = ($pagina - 1) * $limite;

    // ===========================
    // ARMAR FILTROS
    // ===========================
    $where = "1=1";

    if ($f1 !== "") {
        $where .= " AND DATE(fecha) >= '$f1'";
    }

    if ($f2 !== "") {
        $where .= " AND DATE(fecha) <= '$f2'";
    }

    if ($q !== "") {

    // Si el usuario busca un número exacto (solo dígitos)
    if (ctype_digit($q)) {

        // Búsqueda exacta SOLO en campos numéricos
        $where .= " AND (
            id_bitacora = $q OR
            id_objetos = $q OR
            id_usuario = $q
        )";

    } else {

        // Si el usuario escribe texto, se usa LIKE normal
        $q2 = "%$q%";
        $where .= " AND (
            fecha LIKE '$q2' OR
            accion LIKE '$q2' OR
            descripcion LIKE '$q2'
        )";
    }
}

    // ===========================
    // CONTAR REGISTROS
    // ===========================
    $resTotal = $conexion->query("SELECT COUNT(*) AS total FROM tbl_ms_bitacora WHERE $where");
    $total = $resTotal->fetch_assoc()['total'];
    $total_paginas = max(1, ceil($total / $limite));

    // ===========================
    // CONSULTA PRINCIPAL (CORREGIDA)
    // ===========================
    $res = $conexion->query("
        SELECT id_bitacora, fecha, id_objetos, accion, id_usuario, descripcion
        FROM tbl_ms_bitacora
        WHERE $where
        ORDER BY fecha DESC, id_bitacora DESC
        LIMIT $limite OFFSET $offset
    ");

    ?>

    <!-- ===============================
         TABLA DE RESULTADOS
    ================================== -->
    <table>
        <tr>
            <th>Núm. Bitácora</th>
            <th>Fecha</th>
            <th>No. Objeto</th>
            <th>Acción</th>
            <th>Núm. Usuario</th>
            <th>Descripción</th>
        </tr>

        <?php while($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id_bitacora'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['id_objetos'] ?></td>
                <td><?= $row['accion'] ?></td>
                <td><?= $row['id_usuario'] ?></td>
                <td><?= $row['descripcion'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <!-- ===============================
         PAGINACIÓN INTELIGENTE
    ================================== -->
    <div class="pagination">
        <?php
        $mostrar = 2;

        // Flechas hacia atrás
        if ($pagina > 1) {
            echo "<a onclick='loadTabla(1)'>&laquo;</a>";
            echo "<a onclick='loadTabla(".($pagina - 1).")'>&lsaquo;</a>";
        }

        // 1 ...
        if ($pagina > ($mostrar + 2)) {
            echo "<a onclick='loadTabla(1)'>1</a>";
            echo "<span>...</span>";
        }

        // Páginas centrales
        for ($i = max(1, $pagina - $mostrar); $i <= min($total_paginas, $pagina + $mostrar); $i++) {
            echo "<a onclick='loadTabla($i)' class='".($i==$pagina?"active":"")."'>$i</a>";
        }

        // ... último
        if ($pagina < ($total_paginas - ($mostrar + 1))) {
            echo "<span>...</span>";
            echo "<a onclick='loadTabla($total_paginas)'>$total_paginas</a>";
        }

        // Flechas adelante
        if ($pagina < $total_paginas) {
            echo "<a onclick='loadTabla(".($pagina + 1).")'>&rsaquo;</a>";
            echo "<a onclick='loadTabla($total_paginas)'>&raquo;</a>";
        }
        ?>
    </div>

    <?php
    exit;
}

echo "Acción no válida.";

