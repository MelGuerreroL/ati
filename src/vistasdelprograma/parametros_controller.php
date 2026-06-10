<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo "No autorizado.";
    exit;
}

$action = $_GET['action'] ?? '';

/* ===========================================================
    CARGAR TABLA
=========================================================== */
if ($action == "load") {

    $q_raw = $_GET['q'] ?? "";
    $pagina = intval($_GET['pagina'] ?? 1);
    $limite = 10;
    $offset = ($pagina - 1) * $limite;

    // --------------------------
    //    FILTRO INTELIGENTE
    // --------------------------
    $where = "1=1";

    if ($q_raw !== "") {

        // Si escribió solo números → coincidencia EXACTA
        if (ctype_digit($q_raw)) {

            $num = intval($q_raw);

            $where .= " AND (
                p.id_parametro = $num OR
                p.id_usuario = $num OR
                p.valor = '$num'
            )";

        } else {

            // Texto → LIKE
            $q = "%".$conexion->real_escape_string($q_raw)."%";

            $where .= " AND (
                p.parametro LIKE '$q' OR
                p.valor LIKE '$q'
            )";
        }
    }

    // Obtener total
    $total = $conexion->query("
        SELECT COUNT(*) AS total
        FROM tbl_ms_parametros p
        WHERE $where
    ")->fetch_assoc()['total'];

    $total_paginas = max(1, ceil($total / $limite));

    // Obtener datos
    $res = $conexion->query("
        SELECT p.id_parametro, p.parametro, p.valor, p.id_usuario,
               p.fecha_creado, p.fecha_modificado
        FROM tbl_ms_parametros p
        WHERE $where
        ORDER BY p.fecha_modificado DESC
        LIMIT $limite OFFSET $offset
    ");
    ?>

    <table>
        <tr>
            <th>Núm. Parámetro</th>
            <th>Parámetro</th>
            <th>Valor</th>
            <th>Núm. Usuario</th>
            <th>Fecha Creado</th>
            <th>Fecha Modificado</th>
            <th>Acciones</th>
        </tr>

        <?php while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id_parametro'] ?></td>
                <td><?= htmlspecialchars($row['parametro']) ?></td>
                <td><?= htmlspecialchars($row['valor']) ?></td>
                <td><?= $row['id_usuario'] ?></td>
                <td><?= $row['fecha_creado'] ?></td>
                <td><?= $row['fecha_modificado'] ?></td>

                <td>
                    <button class="icon-btn"
                        onclick="openEdit(
                            <?= $row['id_parametro'] ?>,
                            '<?= addslashes($row['parametro']) ?>',
                            '<?= $row['valor'] ?>'
                        )">
                        ✏️ Editar
                    </button>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a onclick="loadTabla(<?= $i ?>)"
               class="<?= $i == $pagina ? 'active' : '' ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>

    <?php
    exit;
}


/* ===========================================================
    AÑADIR PARÁMETRO
=========================================================== */
if ($action == "add") {

    $parametro = $_POST['parametro'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $id_usuario = $_SESSION['usuario_id'];

    $parametro = trim($parametro);
    $parametro = mb_strtoupper($parametro, 'UTF-8');
    $parametro = preg_replace('/\s+/', '_', $parametro);

    $valor = trim($valor);

    if ($parametro == "" || $valor == "") {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos.']);
        exit;
    }

    if (!preg_match('/^[A-Z_]+$/', $parametro)) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre solo puede tener MAYÚSCULAS y guion bajo.'
        ]);
        exit;
    }

    if (!preg_match('/^\d+$/', $valor)) {
        echo json_encode(['success' => false, 'message' => 'El valor debe ser numérico.']);
        exit;
    }

    // Verificar duplicado
    $stmt = $conexion->prepare("SELECT id_parametro FROM tbl_ms_parametros WHERE parametro=?");
    $stmt->bind_param("s", $parametro);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'El parámetro ya existe.']);
        exit;
    }

    // Insertar
    $stmt = $conexion->prepare("
        INSERT INTO tbl_ms_parametros (parametro, valor, id_usuario)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("ssi", $parametro, $valor, $id_usuario);

    if ($stmt->execute()) {
        registrar_bitacora($conexion, $id_usuario, 5, "CREAR", "Creó un Parámetro");
        echo json_encode(['success' => true, 'message' => 'Parámetro creado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar el parámetro.']);
    }

    exit;
}


/* ===========================================================
    ACTUALIZAR VALOR
=========================================================== */
if ($action == "update") {

    $id = intval($_POST['id'] ?? 0);
    $valor = trim($_POST['valor'] ?? "");
    $id_usuario = $_SESSION['usuario_id'];

    if ($id <= 0 || $valor === "") {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
        exit;
    }

    if (!preg_match('/^\d+$/', $valor)) {
        echo json_encode(['success' => false, 'message' => 'El valor debe ser numérico.']);
        exit;
    }

    $stmt = $conexion->prepare("SELECT parametro, valor FROM tbl_ms_parametros WHERE id_parametro=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Parámetro no encontrado.']);
        exit;
    }

    $row = $res->fetch_assoc();
    $old_val = $row['valor'];
    $parametro = $row['parametro'];

    $stmt2 = $conexion->prepare("UPDATE tbl_ms_parametros SET valor=? WHERE id_parametro=?");
    $stmt2->bind_param("si", $valor, $id);

    if ($stmt2->execute()) {
        registrar_bitacora(
            $conexion,
            $id_usuario,
            5,
            "EDITO",
            "Actualizó el Valor de un Parámetro"
        );
        echo json_encode(['success' => true, 'message' => 'Valor actualizado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar.']);
    }

    exit;
}

echo "Acción no válida.";

