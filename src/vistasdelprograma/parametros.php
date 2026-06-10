<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';

if (empty($_SESSION['usuario_id'])) {
    header("Location: ../../public/index.php?error=Debes iniciar sesión");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
registrar_bitacora($conexion, $id_usuario, 5, "ACCESO", "Ingresó a la pantalla de Parámetros");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Parámetros</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
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
    <h2>⚙️ Parámetros</h2>

    <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
    <a href="pre_administracion.php" class="btn">⬅ Volver</a>

    <button class="btn" id="btnAdd">➕ Añadir Parámetro</button>
</div>


    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
        <div>
            <label>Buscar (Nombre o Valor):</label><br>
            <input type="text" id="q" placeholder="Ej: NOMBRE, 123...">
        </div>
        <button class="btn" onclick="loadTabla()">Buscar</button>

        <a id="pdfBtn" class="btn" target="_blank">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
    </div>

    <div id="tablaContainer">Cargando...</div>
</div>

<!-- MODAL AÑADIR -->
<div id="modalAdd" class="modal-backdrop">
    <div class="modal">
        <h3>➕ Añadir Parámetro</h3>

        <div class="form-row">
            <label>Parámetro (MAYÚSCULAS, permite espacios):</label>
            <input id="add_parametro" type="text" maxlength="100" placeholder="Ej: MAXIMO PRECIO">
            <div id="err_parametro" class="form-error"></div>
        </div>

        <div class="form-row">
            <label>Valor (SOLO NÚMEROS):</label>
            <input id="add_valor" type="text" maxlength="20" placeholder="Ej: 1000">
            <div id="err_valor" class="form-error"></div>
        </div>

        <div class="small">El número de usuario se agregará automáticamente.</div>

        <div class="actions">
            <button class="icon-btn" onclick="closeModal('modalAdd')">Cancelar</button>
            <button class="btn" onclick="submitAdd()">Guardar</button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<div id="modalEdit" class="modal-backdrop">
    <div class="modal">
        <h3>✏️ Editar Valor</h3>

        <div class="form-row">
            <label>Parámetro:</label>
            <input id="edit_parametro" type="text" readonly>
        </div>

        <div class="form-row">
            <label>Valor (SOLO NÚMEROS):</label>
            <input id="edit_valor" type="text" maxlength="20">
            <div id="err_edit_valor" class="form-error"></div>
        </div>

        <div class="actions">
            <button class="icon-btn" onclick="closeModal('modalEdit')">Cancelar</button>
            <button class="btn" onclick="submitEdit()">Actualizar</button>
        </div>
    </div>
</div>

<script>
let currentEditId = null;

function showModal(id){ document.getElementById(id).style.display = 'flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }

document.getElementById('btnAdd').onclick = () => showModal('modalAdd');

document.getElementById('add_parametro').addEventListener('input', (e)=>{
    e.target.value = e.target.value.toUpperCase();
});

function onlyNumbers(el){
    el.addEventListener('input', ()=>{
        el.value = el.value.replace(/[^0-9]/g,'');
    });
}
onlyNumbers(document.getElementById('add_valor'));
onlyNumbers(document.getElementById('edit_valor'));

function loadTabla(pagina=1){
    const q=document.getElementById("q").value||"";
    document.getElementById("pdfBtn").href = `parametros_pdf.php?q=${encodeURIComponent(q)}`;

    fetch(`parametros_controller.php?action=load&pagina=${pagina}&q=${encodeURIComponent(q)}`)
    .then(r=>r.text())
    .then(html=>{
        document.getElementById("tablaContainer").innerHTML = html;
    });
}

function submitAdd(){
    const parametro = document.getElementById('add_parametro').value.trim();
    const valor = document.getElementById('add_valor').value.trim();

    document.getElementById('err_parametro').textContent="";
    document.getElementById('err_valor').textContent="";

    if(!parametro){
        document.getElementById('err_parametro').textContent="Debe ingresar un parámetro.";
        return;
    }

    if(!/^[A-Z0-9 ]+$/.test(parametro)){
        document.getElementById('err_parametro').textContent="Solo MAYÚSCULAS, números y espacios.";
        return;
    }

    if(!valor){
        document.getElementById('err_valor').textContent="Debe ingresar un valor.";
        return;
    }

    if(!/^\d+$/.test(valor)){
        document.getElementById('err_valor').textContent="Solo números permitidos.";
        return;
    }

    const parametro_final = parametro.replace(/ +/g,"_");

    const data = new URLSearchParams();
    data.append("parametro", parametro_final);
    data.append("valor", valor);

    fetch("parametros_controller.php?action=add",{
        method:"POST",
        body:data
    })
    .then(r=>r.json())
    .then(obj=>{
        if(obj.success){
            closeModal("modalAdd");
            loadTabla();
            alert(obj.message);
        }else{
            alert(obj.message);
        }
    });
}

function openEdit(id, parametro, valor){
    currentEditId=id;
    document.getElementById("edit_parametro").value = parametro;
    document.getElementById("edit_valor").value = valor;
    showModal("modalEdit");
}

function submitEdit(){
    const valor=document.getElementById("edit_valor").value.trim();

    if(!/^\d+$/.test(valor)){
        document.getElementById("err_edit_valor").textContent="Solo números permitidos.";
        return;
    }

    const data=new URLSearchParams();
    data.append("id", currentEditId);
    data.append("valor", valor);

    fetch("parametros_controller.php?action=update",{
        method:"POST",
        body:data
    })
    .then(r=>r.json())
    .then(obj=>{
        if(obj.success){
            closeModal("modalEdit");
            loadTabla();
            alert(obj.message);
        }else{
            alert(obj.message);
        }
    });
}

window.onload = () => loadTabla();
</script>
</body>
</html>

