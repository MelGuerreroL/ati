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

require_permission('suscripciones', PERM_CREATE);
require 'con_db.php';

// Cargar clientes directamente desde PHP
$clientes = [];
$planes = [];

try {
    // Obtener clientes
    $res_clientes = $conexion->query("SELECT id_cliente, nombre_cliente, correo_electronico FROM tbl_cliente ORDER BY nombre_cliente");
    if ($res_clientes) {
        while ($row = $res_clientes->fetch_assoc()) {
            $clientes[] = [
                'id_cliente' => $row['id_cliente'],
                'correo' => $row['correo_electronico'],
                'nombre_completo' => $row['nombre_cliente']
            ];
        }
    }
    
    // Obtener planes
    $res_planes = $conexion->query("SELECT id_periodo as id_plan, nombre_periodo as nombre_plan, dias, precio, descripcion FROM tbl_periodo ORDER BY dias");
    if ($res_planes) {
        while ($row = $res_planes->fetch_assoc()) {
            $planes[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error cargando datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agregar Suscripción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
    
    .forms-container {
        max-width: 800px;
        margin: 0 auto;
        margin-top: 30px;
    }
    
    .button-selector {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin: 30px 0;
    }
    
    .selector-button {
        background: white;
        border: 2px solid var(--blue-primary);
        color: var(--text-dark);
        padding: 20px 30px;
        border-radius: 15px;
        font-size: 1.1em;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        min-width: 200px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    .selector-button i {
        font-size: 2em;
        color: var(--blue-light);
    }
    
    .selector-button:hover {
        border-color: var(--blue-dark);
        background: var(--blue-primary);
        color: var(--text-light);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    
    .selector-button.active {
        background: var(--blue-primary);
        border-color: var(--blue-primary);
        color: var(--text-light);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    
    .form-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }
    
    .form-section h3 {
        color: var(--text-dark);
        margin-bottom: 25px;
        border-bottom: 2px solid var(--blue-primary);
        padding-bottom: 10px;
        font-weight: bold;
    }
    
    .btn-primary {
        background-color: var(--blue-primary);
        border-color: var(--blue-primary);
        color: var(--text-light);
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .btn-primary:hover {
        background-color: var(--blue-dark);
        border-color: var(--blue-dark);
        color: var(--text-light);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
        font-weight: 600;
    }
    
    .btn-success:hover {
        background-color: #218838;
        border-color: #218838;
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
    
    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
        font-weight: 600;
    }
    
    .btn-danger:hover {
        background-color: #c82333;
        border-color: #bd2130;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }
    
    .btn-info {
        background-color: var(--blue-primary);
        border-color: var(--blue-primary);
        color: var(--text-light);
        font-weight: 600;
    }
    
    .btn-info:hover {
        background-color: var(--blue-dark);
        border-color: var(--blue-dark);
        color: var(--text-light);
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
    
    @media (max-width: 768px) {
        .button-selector {
            flex-direction: column;
            align-items: center;
        }
        
        .selector-button {
            width: 100%;
            max-width: 300px;
        }
    }
    
    .dropdown-menu.show {
        display: block;
    }
    
    .dropdown-item:hover {
        background-color: var(--blue-primary);
        color: var(--text-light);
    }
    
    #emailDropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        background: white;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        max-height: 200px;
        overflow-y: auto;
    }
    
    #emailDropdown .dropdown-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
        transition: all 0.2s ease;
    }
    
    #emailDropdown .dropdown-item:hover {
        background-color: var(--blue-primary) !important;
        color: white !important;
    }
    
    #emailDropdown .dropdown-item:hover small {
        color: rgba(255, 255, 255, 0.8) !important;
    }
    
    #emailDropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .form-control[readonly] {
        background-color: #e9ecef;
        border-color: #28a745;
        color: #155724;
    }
    
    .form-control[readonly]:focus {
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
</style>

</head>
<body>

<div class="main-container">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Agregar Suscripción</h1>
        <a href="pre_suscripciones.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Volver a Gestión de Suscripciones
        </a>
    </div>

    <!-- Botones de selección -->
    <div class="button-selector">
        <button id="btnSuscripcion" class="selector-button active">
            <i class="fas fa-file-contract"></i>
            <span>Agregar Suscripción</span>
        </button>
        <button id="btnPlan" class="selector-button">
            <i class="fas fa-calendar-alt"></i>
            <span>Agregar Plan</span>
        </button>
    </div>

    <div class="forms-container">
        <!-- Formulario Agregar Suscripción -->
        <div id="formSuscripcion" class="form-section">
            <h3><i class="fas fa-file-contract"></i> Nueva Suscripción</h3>
            
            <form id="addForm">
                <div class="mb-3">
                    <label>Seleccionar Correo Electrónico</label>
                    <select id="selectEmailDropdown" class="form-select" name="correo_electronico" required>
                        <option value="">-- Buscar y seleccionar correo electrónico --</option>
                    </select>
                    <input type="hidden" id="selectEmail" name="correo_electronico_hidden">
                    <div id="selectedEmailDisplay" style="display: none; color: #28a745; font-weight: bold; margin-top: 10px;">
                        <i class="fas fa-check-circle"></i> <span>Ningún correo seleccionado</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Nombre Cliente</label>
                    <input type="text" id="addClientName" name="nombre_cliente" class="form-control" required
                           placeholder="Se asignará automáticamente al seleccionar el correo">
                    <div class="form-text">El nombre se completará automáticamente cuando selecciones un correo electrónico.</div>
                </div>

                
                
                <div class="mb-3">
                    <label>Período</label>
                    <select id="selectPeriodo" name="periodo_opcion" class="form-select" required>
                        <option value="">Seleccione un período</option>
                        <option value="MENSUAL">MENSUAL</option>
                        <option value="BIMESTRAL">BIMESTRAL</option>
                        <option value="SEMESTRAL">SEMESTRAL</option>
                        <option value="ANUAL">ANUAL</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Antivirus</label>
                    <select id="addPlan" name="id_plan" class="form-select" required>
                        <!-- Opciones se llenan dinámicamente con JS -->
                    </select>
                </div>
                <div class="mb-3">
                    <label>Días de duración</label>
                    <input type="number" name="duracion_dias" class="form-control" value="" readonly>
                    <div class="form-text">Los días se asignan automáticamente según el plan y el período seleccionados.</div>
                </div>
                <div class="mb-3">
                    <label>Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Fecha fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" readonly>
                    <div class="form-text">Se calcula automáticamente a partir de la fecha de inicio y los días de duración.</div>
                </div>
                <div class="mb-3">
                    <label>Precio</label>
                    <input type="number" step="0.01" name="precio" class="form-control" value="0.00" readonly>
                    <div class="form-text">El precio se asigna automáticamente según el plan seleccionado.</div>
                </div>
                <div class="mb-3">
                    <label>Observaciones</label>
                    <textarea name="observaciones" class="form-control"></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-plus"></i> Agregar Suscripción
                </button>
            </form>
        </div>

        <!-- Formulario Agregar Plan/Período -->
        <div id="formPlan" class="form-section" style="display: none;">
            <h3><i class="fas fa-calendar-alt"></i> Nuevo Plan/Período</h3>
            
            <form id="addPeriodoForm">
                <div class="mb-3">
                    <label class="form-label">ID Período</label>
                    <input type="text" class="form-control" value="Se asignará automáticamente" readonly>
                    <div class="form-text">El ID se generará automáticamente al crear el período.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nombre Período <span class="text-danger">*</span></label>
                    <input type="text" id="nombrePeriodo" name="nombre_periodo" class="form-control" 
                           placeholder="Ej: TRIMESTRAL, BIMESTRAL, etc." required 
                           style="text-transform: uppercase;" 
                           oninput="this.value = this.value.toUpperCase()">
                </div>

                <div class="mb-3">
                    <label class="form-label">Días <span class="text-danger">*</span></label>
                    <input type="number" id="diasPeriodo" name="dias" class="form-control" 
                           placeholder="Número de días del período" min="1" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción <span class="text-danger">*</span></label>
                    <textarea id="descripcionPeriodo" name="descripcion" class="form-control" rows="3" 
                              placeholder="Descripción detallada del período" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Precio <span class="text-danger">*</span></label>
                    <input type="number" id="precioPeriodo" name="precio" class="form-control" placeholder="Precio del período" min="0" step="0.01" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-plus"></i> Crear Período
                </button>
            </form>

            <div class="mt-4">
                <h5><i class="fas fa-list"></i> Períodos Existentes</h5>
                
                <div class="table-responsive">
                    <table class="table table-sm table-striped" id="tablaPeriodos">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Días</th>
                                <th>Precio</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Se cargará dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Datos cargados directamente desde PHP
var allClients = <?php echo json_encode($clientes); ?>;
var allPlans = <?php echo json_encode($planes); ?>;

console.log('Clientes cargados desde PHP:', allClients);
console.log('Planes cargados desde PHP:', allPlans);

$(document).ready(function() {
    console.log('🚀 Inicializando formulario de suscripción');
    
    // Actualizar debug inicial
    updateDebugInfo();
    
    // Monitorear cambios en campos (sin debug)
    $('#selectEmailDropdown').on('select2:select select2:clear', function(){});
    $('#addClientName').on('input', function(){});
    
    // Configurar botones de selección
    $('#btnSuscripcion').on('click', function() {
        $('#btnSuscripcion').addClass('active');
        $('#btnPlan').removeClass('active');
        $('#formSuscripcion').show();
        $('#formPlan').hide();
    });
    
    $('#btnPlan').on('click', function() {
        $('#btnPlan').addClass('active');
        $('#btnSuscripcion').removeClass('active');
        $('#formPlan').show();
        $('#formSuscripcion').hide();
    });
    
    // Cargar planes en el select
    loadPlansInSelect();
    loadEmailsInSelect();
    loadPeriodos(); // Cargar tabla de períodos existentes
    
    // Inicializar filtro de emails
    window.allClientsOriginal = allClients; // Guardar copia original
    
    // Verificar que tenemos datos de clientes
    console.log('🔍 Verificando datos de clientes...');
    console.log('📊 Total de clientes disponibles:', allClients ? allClients.length : 0);
    if (allClients && allClients.length > 0) {
        console.log('✅ Clientes cargados correctamente desde PHP');
        console.log('📝 Ejemplo del primer cliente:', allClients[0]);
    } else {
        console.error('❌ No hay clientes disponibles o error en la carga');
    }
    
    // Actualizar debug visual
    updateDebugInfo();
    
    // Monitor field changes for debug
    $('#searchEmail').on('input', updateDebugInfo);
    $('#addClientName').on('input', updateDebugInfo);
    
    // Verificar que el formulario existe
    if ($('#addForm').length === 0) {
        return;
    }
    
    // Formulario de suscripción
    $('#addForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Submit del formulario de suscripción capturado');
        
        const email = $('#selectEmail').val();
        const nombre = $('#addClientName').val().trim();
        const plan = $('#addPlan').val();
        const fechaInicio = $('input[name="fecha_inicio"]').val();
        
        console.log('Datos del formulario:', {email, nombre, plan, fechaInicio});
        
        if (!email) {
            alert('Por favor seleccione un correo electrónico');
            return;
        }
        
        if (!nombre) {
            alert('Por favor ingrese el nombre del cliente');
            return;
        }
        
        if (!plan) {
            alert('Por favor seleccione un plan');
            return;
        }
        
        if (!fechaInicio) {
            alert('Por favor seleccione la fecha de inicio');
            return;
        }
        
        console.log('Validaciones pasadas, enviando formulario...');
        
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Procesando...');
        
        $.post('suscripciones_actions.php?action=add', $(this).serialize())
            .done(function(response) {
                console.log('Respuesta recibida:', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('Suscripción creada exitosamente');
                        $('#addForm')[0].reset();
                        loadFilters();
                    } else {
                        alert('Error: ' + (result.error || 'Error desconocido'));
                    }
                } catch(e) {
                    console.error('Error parsing JSON:', e);
                    alert('Error procesando respuesta del servidor');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error AJAX:', {xhr, status, error, response: xhr.responseText});
                alert('Error de conexión: ' + error);
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Agregar Suscripción');
            });
    });
    
    // Formulario de período
    $('#addPeriodoForm').on('submit', function(e) {
        e.preventDefault();
        
        const nombre = $('#nombrePeriodo').val().trim();
        const dias = $('#diasPeriodo').val();
        const precio = $('#precioPeriodo').val();
        const desc = $('#descripcionPeriodo').val().trim();
        
        if (!nombre || !dias || !desc) {
            alert('Complete todos los campos requeridos');
            return;
        }
        
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Creando...');
        
        $.post('periodos_actions.php?action=add', $(this).serialize())
            .done(function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('Período creado exitosamente');
                        $('#addPeriodoForm')[0].reset();
                        loadPeriodos();
                        loadFilters();
                    } else {
                        alert('Error: ' + (result.error || 'Error desconocido'));
                    }
                } catch(e) {
                    alert('Error procesando respuesta');
                }
            })
            .fail(function() {
                alert('Error de conexión');
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Crear Período');
            });
    });
});



// Funcion de Email Select eliminada - usando solo la version Select2

function onEmailSelect(selectElement) {
    const selectedOption = $(selectElement).find(':selected');
    const clientData = selectedOption.data('client');
    
    if (clientData) {
        console.log('✅ Correo seleccionado desde dropdown:', clientData.correo);
        selectEmail(clientData.correo, clientData.nombre_completo);
    } else {
        // Limpiar campos si no hay selección
        $('#selectEmail').val('');
        $('#addClientName').val('');
        $('#selectedEmailDisplay').hide();
        updateDebugInfo();
    }
}

function filterEmails(query) {
    const select = $('#selectEmailDropdown');
    const lowerQuery = query.toLowerCase().trim();
    
    // Si no hay query, mostrar todos
    if (lowerQuery === '') {
        select.find('option').show();
        return;
    }
    
    // Filtrar opciones
    select.find('option').each(function() {
        const option = $(this);
        const text = option.text().toLowerCase();
        
        if (option.val() === '' || text.includes(lowerQuery)) {
            option.show();
        } else {
            option.hide();
        }
    });
    
    console.log('Filtro aplicado:', query);
}

// Eliminar las funciones de carga AJAX ya que usamos datos de PHP
function loadClients() {
    // Ya no necesaria - datos cargados desde PHP
    console.log('Clientes ya disponibles desde PHP:', allClients.length);
}

function loadFilters() {
    // Ya no necesaria - datos cargados desde PHP  
    console.log('Planes ya disponibles desde PHP:', allPlans.length);
}

function loadPeriodos() {
    console.log('📋 === INICIANDO CARGA DE PERÍODOS ===');
    console.log('🔍 Variable allPlans:', allPlans);
    console.log('🔍 Tipo de allPlans:', typeof allPlans);
    console.log('🔍 Array.isArray(allPlans):', Array.isArray(allPlans));
    console.log('🔍 allPlans.length:', allPlans ? allPlans.length : 'undefined');
    
    // Verificar si el elemento tabla existe
    const tablaBody = $('#tablaPeriodos tbody');
    console.log('🔍 Elemento tabla encontrado:', tablaBody.length > 0);
    
    // Primero intentar con los datos ya disponibles desde PHP
    if (allPlans && Array.isArray(allPlans) && allPlans.length > 0) {
        console.log('✅ Usando datos de PHP - Planes disponibles:', allPlans.length);
        console.log('📊 Todos los planes:', allPlans);
        
        let tbody = '';
        allPlans.forEach(function(plan, index) {
            console.log(`📝 Procesando plan ${index + 1}:`, plan);
            
            const id = plan.id_plan || plan.id_periodo || 'N/A';
            const nombre = plan.nombre_plan || plan.nombre_periodo || 'Sin nombre';
            const dias = plan.dias || 0;
            const precio = parseFloat(plan.precio || 0);
            const descripcion = plan.descripcion || 'Sin descripción';
            
            tbody += `<tr>
                <td>${id}</td>
                <td>${(nombre || 'Sin nombre').toUpperCase()}</td>
                <td>${dias}</td>
                <td>${precio.toFixed(2)}</td>
                <td>${descripcion}</td>
            </tr>`;
        });
        
        console.log('🎯 HTML generado (primeros 300 chars):', tbody.substring(0, 300));
        tablaBody.html(tbody);
        console.log('✅ Tabla actualizada. Filas insertadas:', tablaBody.find('tr').length);
        return;
    }
    
    // Si llegamos aquí, allPlans está vacío o no es válido
    console.warn('⚠️ allPlans no es válido:', {
        'allPlans': allPlans,
        'tipo': typeof allPlans,
        'esArray': Array.isArray(allPlans),
        'longitud': allPlans ? allPlans.length : 'N/A'
    });
    
    // Mostrar mensaje de carga y intentar AJAX
    tablaBody.html(`
        <tr><td colspan="5" class="text-center text-info">
            🔄 Cargando períodos via AJAX...
            <br><button onclick="loadPeriodos()" class="btn btn-sm btn-primary mt-2">🔄 Recargar</button>
        </td></tr>
    `);
    
    console.log('📡 Iniciando petición AJAX a periodos_actions.php');
    
    $.getJSON('periodos_actions.php?action=list')
        .done(function(response) {
            console.log('📡 Respuesta AJAX completa:', response);
            
            if (response && response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
                console.log('✅ Datos AJAX válidos:', response.data.length, 'períodos');
                
                let tbody = '';
                response.data.forEach(function(p, index) {
                    console.log(`📝 Plan AJAX ${index + 1}:`, p);
                    tbody += `<tr>
                        <td>${p.id_periodo}</td>
                        <td>${(p.nombre_periodo || 'Sin nombre').toUpperCase()}</td>
                        <td>${p.dias}</td>
                        <td>${(p.precio || 0).toFixed(2)}</td>
                        <td>${p.descripcion || 'Sin descripción'}</td>
                    </tr>`;
                });
                
                tablaBody.html(tbody);
                console.log('✅ Tabla cargada via AJAX con', response.data.length, 'períodos');
            } else {
                tablaBody.html(`
                    <tr><td colspan="5" class="text-center text-warning">
                        ⚠️ No se encontraron períodos en la base de datos<br>
                        <small>Respuesta AJAX: ${JSON.stringify(response)}</small><br>
                        <button onclick="loadPeriodos()" class="btn btn-sm btn-warning mt-2">🔄 Intentar de nuevo</button>
                        <a href="setup_periodos.php" class="btn btn-sm btn-success mt-2">➕ Crear períodos</a>
                    </td></tr>
                `);
                console.warn('❌ Sin datos válidos en respuesta AJAX:', response);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('❌ Error AJAX completo:', {
                'xhr': xhr,
                'status': status, 
                'error': error,
                'responseText': xhr.responseText
            });
            
            tablaBody.html(`
                <tr><td colspan="5" class="text-center text-danger">
                    ❌ Error cargando períodos: ${error}<br>
                    <small>Status: ${status}, Response: ${xhr.responseText}</small><br>
                    <button onclick="loadPeriodos()" class="btn btn-sm btn-danger mt-2">🔄 Reintentar</button>
                    <a href="periodos_actions.php?action=list" target="_blank" class="btn btn-sm btn-info mt-2">🔗 Probar AJAX</a>
                </td></tr>
            `);
        });
    
    console.log('📋 === FIN FUNCIÓN LOADPERIODOS ===');
}

function setupPeriodoForm() {
    // Ya no necesaria - evento configurado en $(document).ready
}

function checkMaxSuscripciones(email, callback) {
    $.post('suscripciones_actions.php?action=check_limit', {email: email})
        .done(function(response) {
            const result = typeof response === 'string' ? JSON.parse(response) : response;
            if (result.can_add) {
                callback(true);
            } else {
                alert('❌ ' + result.message);
                callback(false);
            }
        })
        .fail(function() {
            alert('Error verificando límites');
            callback(false);
        });
}

function testPeriodoConnection() {
    const testData = {
        nombre_periodo: 'TEST_' + Date.now(),
        dias: 30,
        descripcion: 'Prueba de conexión',
        precio: 10.99
    };
    
    $.post('periodos_actions.php?action=add', testData)
        .done(function(response) {
            const result = typeof response === 'string' ? JSON.parse(response) : response;
            if (result.success) {
                alert('✅ Test exitoso');
                loadPeriodos();
            } else {
                alert('❌ Error: ' + result.error);
            }
        })
        .fail(function() { alert('❌ Error de conexión'); });
}

// Eventos de búsqueda de email
$(document).on('input', '#searchEmail', function() {
    const query = $(this).val().toLowerCase().trim();
    
    if (query.length < 1) {
        $('#emailDropdown').hide();
        return;
    }
    
    console.log('🔍 Buscando clientes con:', query);
    console.log('📊 Clientes disponibles para búsqueda:', allClients ? allClients.length : 0);
    
    if (!allClients || allClients.length === 0) {
        $('#emailDropdown').html('<div class="dropdown-item text-danger">⚠️ No hay clientes en tbl_cliente o error de conexión</div>').show();
        return;
    }
    
    const matches = allClients.filter(function(c) {
        const correo = (c.correo || '').toLowerCase();
        const nombre = (c.nombre_completo || '').toLowerCase();
        const match = correo.includes(query) || nombre.includes(query);
        return match;
    });
    
    console.log(`📋 Encontrados ${matches.length} clientes que coinciden con "${query}"`);
    
    let html = '';
    if (matches.length > 0) {
        matches.forEach(function(c) {
            html += `<div class="dropdown-item" onclick="selectEmail('${c.correo}', '${c.nombre_completo}')" style="cursor: pointer; padding: 8px 12px; border-bottom: 1px solid #eee;">
                <strong>${c.correo}</strong><br>
                <small class="text-muted">${c.nombre_completo}</small>
            </div>`;
        });
    } else {
        html = '<div class="dropdown-item text-muted">❌ No se encontraron clientes con ese criterio</div>';
    }
    
    $('#emailDropdown').html(html).show();
});

function updateDebugInfo() {
    // Actualizar información de clientes
    let clientesInfo = '';
    if (allClients && allClients.length > 0) {
        clientesInfo = `Total: ${allClients.length} clientes\n`;
        allClients.slice(0, 5).forEach((cliente, index) => {
            clientesInfo += `${index + 1}. ${cliente.correo} - ${cliente.nombre_completo}\n`;
        });
        if (allClients.length > 5) {
            clientesInfo += `... y ${allClients.length - 5} más`;
        }
    } else {
        clientesInfo = 'No hay clientes cargados';
    }
    $('#debugClientes').text(clientesInfo);
    
    // Actualizar estado del formulario
    $('#debugSearchEmail').text($('#searchEmail').val() || 'vacío');
    $('#debugSelectEmail').text($('#selectEmail').val() || 'vacío');
    $('#debugNombreCliente').text($('#addClientName').val() || 'vacío');
}

function loadEmailsInSelect() {
    console.log('🎯 Inicializando Select2 con búsqueda integrada...');
    
    if (!allClients || allClients.length === 0) {
        console.error('❌ No hay clientes disponibles');
        return;
    }
    
    // Preparar datos para Select2
    const emailData = allClients.map(function(client) {
        return {
            id: client.correo,
            text: `${client.correo} - ${client.nombre_completo}`,
            clientData: client
        };
    });
    
    // Ordenar por nombre
    emailData.sort((a, b) => a.clientData.nombre_completo.localeCompare(b.clientData.nombre_completo));
    
    // Inicializar Select2 con búsqueda integrada
    $('#selectEmailDropdown').select2({
        data: emailData,
        theme: 'bootstrap-5',
        placeholder: '🔍 Buscar correo electrónico o nombre de cliente...',
        allowClear: true,
        width: '100%',
        minimumInputLength: 0,
        language: {
            noResults: function() {
                return '❌ No se encontraron resultados';
            },
            searching: function() {
                return '🔍 Buscando...';
            },
            loadingMore: function() {
                return 'Cargando más resultados...';
            }
        },
        // Función personalizada de búsqueda
        matcher: function(params, data) {
            // Si no hay término de búsqueda, mostrar todo
            if ($.trim(params.term) === '') {
                return data;
            }
            
            // Convertir término de búsqueda a minúsculas para búsqueda case-insensitive
            const term = params.term.toLowerCase();
            const text = data.text.toLowerCase();
            
            // Buscar en el texto completo (correo + nombre)
            if (text.indexOf(term) > -1) {
                return data;
            }
            
            // También buscar específicamente en el nombre
            if (data.clientData && data.clientData.nombre_completo.toLowerCase().indexOf(term) > -1) {
                return data;
            }
            
            // También buscar específicamente en el correo
            if (data.clientData && data.clientData.correo.toLowerCase().indexOf(term) > -1) {
                return data;
            }
            
            return null;
        }
    });
    
    // Manejar selección
    $('#selectEmailDropdown').on('select2:select', function(e) {
        const data = e.params.data;
        if (data.clientData) {
            console.log('✅ Cliente seleccionado desde Select2:', data.clientData.correo);
            selectEmail(data.clientData.correo, data.clientData.nombre_completo);
        }
    });
    
    // Manejar limpieza (cuando se hace clic en X)
    $('#selectEmailDropdown').on('select2:clear', function(e) {
        console.log('🧹 Limpiando selección...');
        $('#selectEmail').val('');
        $('#addClientName').val('');
        $('#selectedEmailDisplay').hide();
    });
    
    console.log('✅ Select2 inicializado correctamente con', allClients.length, 'clientes');
}

function loadPlansInSelect() {
    console.log('Cargando planes en select...');
    let opts = '<option value="">Seleccione antivirus</option>';
    
    allPlans.forEach(function(plan) {
        opts += `<option value="${plan.id_plan}" data-precio="${plan.precio || 0}" data-dias="${plan.dias || 30}">${(plan.nombre_plan || 'Sin nombre').toUpperCase()}</option>`;
    });
    
    $('#addPlan').html(opts);
    console.log('Planes cargados en select:', allPlans.length);
}

function getPeriodoMultiplicador(periodo) {
    switch ((periodo || '').toUpperCase()) {
        case 'BIMESTRAL': return 2;
        case 'SEMESTRAL': return 6;
        case 'ANUAL': return 12;
        default: return 1; // MENSUAL o vacío
    }
}

function calcularDuracionYPrecio() {
    const selectedOption = $('#addPlan').find('option:selected');
    const planId = $('#addPlan').val();
    const periodo = $('#selectPeriodo').val();

    if (!planId) {
        $('input[name="precio"]').val('0.00');
        $('input[name="duracion_dias"]').val('');
        $('#fecha_fin').val('');
        return;
    }

    const precioPorDia = parseFloat(selectedOption.data('precio')) || 0;
    const diasBase = parseInt(selectedOption.data('dias')) || 0;
    const multiplicador = getPeriodoMultiplicador(periodo);
    const duracion = diasBase * multiplicador;
    const precioTotal = precioPorDia * duracion;

    $('input[name="precio"]').val(precioTotal.toFixed(2));
    $('input[name="duracion_dias"]').val(duracion);

    const fechaInicio = $('input[name="fecha_inicio"]').val();
    if (fechaInicio && duracion) {
        const fecha = new Date(fechaInicio);
        fecha.setDate(fecha.getDate() + duracion);
        const fechaFin = fecha.toISOString().split('T')[0];
        $('#fecha_fin').val(fechaFin);
    }
}

function selectEmail(email, nombre) {
    console.log('✅ Seleccionando cliente:', email);
    
    $('#selectEmail').val(email);
    $('#addClientName').val(nombre);
    
    // Mostrar confirmación
    $('#selectedEmailDisplay').show().find('span').text(`✅ ${email} - ${nombre}`);
    
    console.log('✅ Campos del formulario actualizados correctamente');
}

// Plan change event
$(document).on('change', '#addPlan', function() {
    calcularDuracionYPrecio();
});

// Period change event
$(document).on('change', '#selectPeriodo', function() {
    calcularDuracionYPrecio();
});

// Evento para calcular fecha fin cuando cambie fecha inicio
$(document).on('change', 'input[name="fecha_inicio"]', function() {
    const fechaInicio = $(this).val();
    const dias = $('input[name="duracion_dias"]').val();
    if (fechaInicio && dias) {
        const fecha = new Date(fechaInicio);
        fecha.setDate(fecha.getDate() + parseInt(dias));
        const fechaFin = fecha.toISOString().split('T')[0];
        $('#fecha_fin').val(fechaFin);
    }
});

// Ocultar dropdown al hacer clic fuera
$(document).on('click', function(e) {
    if (!$(e.target).closest('#searchEmail, #emailDropdown').length) {
        $('#emailDropdown').hide();
    }
});

function updateDebugInfo() {
    $('#debugSearchEmail').text($('#selectEmailDropdown').val() || 'vacío');
    $('#debugSelectEmail').text($('#selectEmail').val() || 'vacío');
    $('#debugNombreCliente').text($('#addClientName').val() || 'vacío');
    $('#debugAllClients').text(typeof allClients !== 'undefined' ? allClients.length + ' clientes' : 'undefined');
    $('#debugAllPlans').text(typeof allPlans !== 'undefined' ? allPlans.length + ' planes' : 'undefined');
}

function testFormFunctionality() {
    console.log('🧪 Testing form functionality...');
    
    // Test 1: Verificar elementos DOM
    console.log('DOM Elements Check:');
    console.log('searchEmail exists:', $('#searchEmail').length > 0);
    console.log('selectEmail exists:', $('#selectEmail').length > 0);
    console.log('addClientName exists:', $('#addClientName').length > 0);
    console.log('emailDropdown exists:', $('#emailDropdown').length > 0);
    
    // Test 2: Verificar datos JavaScript
    console.log('Data Check:');
    console.log('allClients defined:', typeof allClients !== 'undefined');
    console.log('allClients length:', allClients ? allClients.length : 'N/A');
    console.log('allPlans defined:', typeof allPlans !== 'undefined');
    console.log('allPlans length:', allPlans ? allPlans.length : 'N/A');
    
    if (allClients && allClients.length > 0) {
        console.log('First client:', allClients[0]);
        
        // Test 3: Simular selección de cliente
        const testClient = allClients[0];
        console.log('Testing client selection with:', testClient.correo);
        
        $('#searchEmail').val(testClient.correo).trigger('input');
        
        setTimeout(function() {
            console.log('After search input - dropdown visible:', $('#emailDropdown').is(':visible'));
            console.log('Dropdown content:', $('#emailDropdown').html());
            
            // Simular click en cliente
            selectEmail(testClient.correo, testClient.nombre_completo);
            updateDebugInfo();
        }, 500);
    } else {
        console.error('❌ No clients available for testing');
        alert('❌ No hay clientes disponibles para probar');
    }
}

function testFormFunctionality() {
    console.log('🧪 Testing form functionality...');
    
    // Test 1: Verificar elementos DOM
    console.log('DOM Elements Check:');
    console.log('searchEmail exists:', $('#searchEmail').length > 0);
    console.log('selectEmail exists:', $('#selectEmail').length > 0);
    console.log('addClientName exists:', $('#addClientName').length > 0);
    console.log('emailDropdown exists:', $('#emailDropdown').length > 0);
    
    // Test 2: Verificar datos JavaScript
    console.log('Data Check:');
    console.log('allClients defined:', typeof allClients !== 'undefined');
    console.log('allClients length:', allClients ? allClients.length : 'N/A');
    console.log('allPlans defined:', typeof allPlans !== 'undefined');
    console.log('allPlans length:', allPlans ? allPlans.length : 'N/A');
    
    if (allClients && allClients.length > 0) {
        console.log('First client:', allClients[0]);
        
        // Test 3: Simular selección de cliente
        const testClient = allClients[0];
        console.log('Testing client selection with:', testClient.correo);
        
        $('#searchEmail').val(testClient.correo).trigger('input');
        
        setTimeout(function() {
            console.log('After search input - dropdown visible:', $('#emailDropdown').is(':visible'));
            console.log('Dropdown content:', $('#emailDropdown').html());
            
            // Simular click en cliente
            selectEmail(testClient.correo, testClient.nombre_completo);
            updateDebugInfo();
        }, 500);
    } else {
        console.error('❌ No clients available for testing');
        alert('❌ No hay clientes disponibles para probar');
    }
}
</script>

</body>
</html>

