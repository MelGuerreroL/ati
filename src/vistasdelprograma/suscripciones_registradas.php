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
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Suscripciones Registradas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
 <style>
    :root{
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
      max-width: 1400px;
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
        color: var(--text-light);
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
        color: var(--text-light);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }    
    .btn-primary {
        background-color: var(--blue-primary);
        border-color: var(--blue-primary);
        color: var(--text-light);
        font-weight: 600;
    }
    
    .btn-primary:hover {
        background-color: var(--blue-dark);
        border-color: var(--blue-dark);
        color: var(--text-light);
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
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
    
    .btn-secondary {
      background-color: var(--blue-light);
      border-color: var(--blue-light);
      color: var(--text-dark);
      font-weight: 600;
    }
    
    .btn-secondary:hover {
        background-color: var(--blue-primary);
        border-color: var(--blue-primary);
        color: var(--text-light);
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }    
    .btn-close {
        background: none;
        border: none;
        opacity: 0.5;
        transition: opacity 0.3s;
    }
    
    .btn-close:hover {
        opacity: 1;
        transform: scale(1.1);
    }    
    .card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }
    
    .filters {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .dropdown-item:hover {
      background-color: var(--blue-primary);
      color: var(--text-light);
    }
    
    .select2-results__option--highlighted {
      background-color: var(--blue-primary) !important;
      color: white !important;
    }
    
    .select2-filter-dropdown .select2-search__field:focus {
      border-color: var(--blue-primary) !important;
      box-shadow: 0 0 0 0.2rem rgba(30,136,229,0.25) !important;
      background-color: white !important;
    }
    
    .page-link {
      color: var(--blue-primary);
      background-color: white;
      border-color: #dee2e6;
      padding: 0.5rem 0.75rem;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .page-link:hover {
      color: var(--text-light);
      background-color: var(--blue-primary);
      border-color: var(--blue-primary);
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }
    
    .page-item.active .page-link {
      background-color: var(--blue-primary);
      border-color: var(--blue-primary);
      color: var(--text-light);
    }
</style>

</head>
<body>

<div class="main-container">
  <div class="page-header">
    <h1><i class="fas fa-list"></i> Suscripciones Registradas</h1>
    <a href="pre_suscripciones.php" class="back-button">
      <i class="fas fa-arrow-left"></i> Volver a Gestión de Suscripciones
    </a>
  </div>

  <!-- Botones de acción -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
      <button id="btnAdd" class="btn btn-success" onclick="window.location.href='suscripciones_agregar.php'">
        <i class="fas fa-plus"></i> Agregar Suscripción
      </button>
      <button id="btnReminders" class="btn btn-warning">
        <i class="fas fa-bell"></i> Generar Recordatorio
      </button>
    </div>
    <button id="btnExportPdf" class="btn btn-danger">
      <i class="fas fa-file-pdf"></i> Reporte General PDF
    </button>
  </div>

  <div class="card p-3">
    <!-- Filtros de búsqueda -->
    <div class="filters d-flex gap-2 mb-3">
      <select id="filterClient" class="form-select select2-filter" style="width: 280px;">
        <option value="">Buscar cliente...</option>
      </select>
      <select id="filterPlan" class="form-select">
        <option value="">Todos los planes</option>
      </select>
      <select id="filterStatus" class="form-select">
        <option value="">Todos los estados</option>
        <option value="1">ACTIVA</option>
        <option value="2">VENCIDA</option>
        <option value="3">CANCELADA</option>
      </select>
      <input id="filterFrom" type="date" class="form-control" placeholder="Fecha desde">
      <input id="filterTo" type="date" class="form-control" placeholder="Fecha hasta">
      <button id="btnFilter" class="btn btn-primary">
        <i class="fas fa-search"></i> Buscar
      </button>
    </div>

    <!-- Tabla de suscripciones -->
    <div class="table-responsive">
      <table id="susTable" class="display table table-striped" style="width:100%">
        <thead>
          <tr>
            <th>Número de Suscripción</th>
            <th class="d-none">N° Cliente</th>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Precio</th>
            <th>Pagado</th>
            <th>Estado</th>
            <th>Tiempo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <small class="text-muted">El número de cliente se ha ocultado para simplificar la vista. Sigue disponible para exportaciones y acciones.</small>
    
    <!-- Controles de paginación -->
    <div id="paginationControls" class="d-flex justify-content-between align-items-center mt-3">
      <div>
        <span id="paginationInfo" class="text-muted"></span>
      </div>
      <nav aria-label="Paginación de suscripciones">
        <ul id="paginationList" class="pagination mb-0">
        </ul>
      </nav>
    </div>
  </div>
</div>

<!-- Modal Agregar -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Suscripción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addForm">
        <div class="modal-body">
          <div class="row">
            <!-- Columna izquierda -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="addSelectEmailDropdown" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                <select id="addSelectEmailDropdown" name="correo_electronico" class="form-select" required>
                  <option value="">Seleccione un correo electrónico...</option>
                </select>
                <div class="form-text">Seleccione el correo electrónico del cliente</div>
              </div>
              
              <div class="mb-3">
                <label for="addClientName" class="form-label">Nombre Cliente <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="text" id="addClientName" name="nombre_cliente" class="form-control" required>

                </div>
                <div class="form-text">Se completa automáticamente al seleccionar el correo</div>
              </div>
              
              <div class="mb-3">
                <label for="addPlan" class="form-label">Plan de Suscripción <span class="text-danger">*</span></label>
                <select id="addPlan" name="id_plan" class="form-select" required>
                  <option value="">Seleccione un plan...</option>
                </select>
                <div class="form-text">Seleccione el plan de suscripción</div>
              </div>
              
              <div class="mb-3">
                <label for="addPrecio" class="form-label">Precio <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" step="0.01" id="addPrecio" name="precio" class="form-control" value="0.00" readonly>
                </div>
                <div class="form-text">Se asigna automáticamente según el plan seleccionado</div>
              </div>
            </div>
            
            <!-- Columna derecha -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="addDuracionDias" class="form-label">Duración (días) <span class="text-danger">*</span></label>
                <input type="number" id="addDuracionDias" name="duracion_dias" class="form-control" value="30" readonly>
                <div class="form-text">Se asigna automáticamente según el plan seleccionado</div>
              </div>
              
              <div class="mb-3">
                <label for="addFechaInicio" class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                <input type="date" id="addFechaInicio" name="fecha_inicio" class="form-control" required>
                <div class="form-text">Fecha en que inicia la suscripción</div>
              </div>
              
              <div class="mb-3">
                <label for="addFechaFin" class="form-label">Fecha de Fin</label>
                <input type="date" id="addFechaFin" name="fecha_fin" class="form-control" readonly>
                <div class="form-text">Se calcula automáticamente</div>
              </div>
              
              <div class="mb-3">
                <label for="addObservaciones" class="form-label">Observaciones</label>
                <textarea id="addObservaciones" name="observaciones" class="form-control" rows="3" placeholder="Observaciones adicionales (opcional)..."></textarea>
                <div class="form-text">Información adicional sobre la suscripción</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times"></i> Cancelar
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Guardar Suscripción
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Suscripción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editForm">
        <div class="modal-body">
          <input type="hidden" id="editIdSuscripcion" name="id_suscripcion">
          
          <div class="row">
            <!-- Columna izquierda -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="editCorreoElectronico" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                <input type="email" id="editCorreoElectronico" name="correo_electronico" class="form-control" required>
                <div class="form-text">Correo electrónico del cliente</div>
              </div>
              
              <div class="mb-3">
                <label for="editClientName" class="form-label">Nombre Cliente <span class="text-danger">*</span></label>
                <input type="text" id="editClientName" name="nombre_cliente" class="form-control" required>
                <div class="form-text">Nombre completo del cliente</div>
              </div>
              
              <div class="mb-3">
                <label for="editPlan" class="form-label">Plan de Suscripción <span class="text-danger">*</span></label>
                <select id="editPlan" name="id_plan" class="form-select" required>
                  <option value="">Seleccione un plan...</option>
                </select>
                <div class="form-text">Seleccione el plan de suscripción</div>
              </div>
              
              <div class="mb-3">
                <label for="editPrecio" class="form-label">Precio <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" step="0.01" id="editPrecio" name="precio" class="form-control" value="0.00">
                </div>
                <div class="form-text">Se asigna automáticamente según el plan seleccionado</div>
              </div>
              
              <div class="mb-3">
                <label for="editEstado" class="form-label">Estado <span class="text-danger">*</span></label>
                <select id="editEstado" name="id_estado" class="form-select" required>
                  <option value="1">ACTIVA</option>
                  <option value="2">VENCIDA</option>
                  <option value="3">CANCELADA</option>
                </select>
                <div class="form-text">Estado actual de la suscripción</div>
              </div>
            </div>
            
            <!-- Columna derecha -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="editDuracionDias" class="form-label">Duración (días) <span class="text-danger">*</span></label>
                <input type="number" id="editDuracionDias" name="duracion_dias" class="form-control" value="30">
                <div class="form-text">Se asigna automáticamente según el plan seleccionado</div>
              </div>
              
              <div class="mb-3">
                <label for="editFechaInicio" class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                <input type="date" id="editFechaInicio" name="fecha_inicio" class="form-control" required>
                <div class="form-text">Fecha en que inicia la suscripción</div>
              </div>
              
              <div class="mb-3">
                <label for="editFechaFin" class="form-label">Fecha de Fin</label>
                <input type="date" id="editFechaFin" name="fecha_fin" class="form-control" readonly>
                <div class="form-text">Se calcula automáticamente</div>
              </div>
              
              <div class="mb-3">
                <label for="editObservaciones" class="form-label">Observaciones</label>
                <textarea id="editObservaciones" name="observaciones" class="form-control" rows="3" placeholder="Observaciones adicionales (opcional)..."></textarea>
                <div class="form-text">Información adicional sobre la suscripción</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times"></i> Cancelar
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Guardar Cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

<script>
// Función para actualizar estados automáticamente
function updatePaymentStatusesAuto() {
  $.post('suscripciones_actions.php?action=update_auto_status', {}, function(response) {
    console.log('Auto status update:', response);
  }, 'json').fail(function(xhr) {
    console.log('Auto status update failed:', xhr.responseText);
  });
}

let susTable;
let allClients = [];

// Cargar datos al inicio
$(document).ready(function() {
  updatePaymentStatusesAuto();
  loadClients();
  
  // Verificar que Select2 esté disponible antes de cargar filtros
  if (typeof $.fn.select2 !== 'undefined') {
    console.log('✅ Select2 está disponible');
    loadFilters();
  } else {
    console.error('❌ Select2 no está disponible');
    // Reintentamos después de 1 segundo
    setTimeout(function() {
      loadFilters();
    }, 1000);
  }
  
  setupDataTable();
  setupEventHandlers();
  
  // Establecer fecha de hoy por defecto
  const today = new Date().toISOString().split('T')[0];
  $('input[name="fecha_inicio"]').val(today);
});

function loadClients() {
  console.log('📧 Cargando correos...');
  $.ajax({
    url: 'get_clients.php',
    dataType: 'json',
    method: 'GET',
    success: function(response) {
      console.log('✅ Respuesta completa del servidor:', response);
      
      try {
        if (!response || typeof response !== 'object') {
          throw new Error('Respuesta inválida del servidor');
        }

        if (!response.success) {
          throw new Error(response.error || 'Error desconocido del servidor');
        }

        if (!Array.isArray(response.data)) {
          throw new Error('Los datos no están en el formato de array esperado');
        }

        allClients = response.data;
        console.log('📊 Clientes cargados exitosamente:', allClients.length);
        console.log('📊 Primeros 3 clientes:', allClients.slice(0, 3));
        
        // Primero poblar las opciones
        populateSelect2Options();
        
        // Luego configurar Select2 y los eventos
        setupSelect2AfterData();
        
      } catch (error) {
        console.error('❌ Error al procesar la respuesta de clientes:', error.message);
        console.error('❌ Respuesta recibida:', response);
        allClients = [];
      }
    },
    error: function(xhr, status, error) {
      console.error('❌ Error AJAX al cargar clientes:', {
        status: status,
        error: error,
        responseText: xhr.responseText
      });
      allClients = [];
    }
  });
}

// Función para configurar Select2 después de cargar los datos
function setupSelect2AfterData() {
  console.log('🔧 Configurando Select2 después de cargar datos...');
  
  // Destruir Select2 existente si existe
  if ($('#addSelectEmailDropdown').hasClass('select2-hidden-accessible')) {
    $('#addSelectEmailDropdown').select2('destroy');
  }
  if ($('#editSelectEmailDropdown').hasClass('select2-hidden-accessible')) {
    $('#editSelectEmailDropdown').select2('destroy');
  }
  
  // Configuración común para ambos Select2
  const select2Config = {
    theme: 'bootstrap-5',
    placeholder: 'Busque y seleccione un correo electrónico...',
    allowClear: true,
    minimumInputLength: 0, // Permitir mostrar todas las opciones al abrir
    width: '100%'
  };
  
  // Configurar Select2 para modal de agregar
  $('#addSelectEmailDropdown').select2({
    ...select2Config,
    dropdownParent: $('#addModal')
  });

  // Configurar Select2 para modal de editar
  $('#editSelectEmailDropdown').select2({
    ...select2Config,
    dropdownParent: $('#editModal')
  });

  // Esperar un poco más para que Select2 esté completamente inicializado
  setTimeout(() => {
    console.log('🔎 Configurando eventos DESPUÉS de Select2...');
    setupEmailSelectionEvents();
  }, 500);
  
  console.log('✅ Select2 configurado con', allClients.length, 'clientes');
}

// Función para configurar los eventos de selección de email
function setupEmailSelectionEvents() {
  console.log('🔧 Configurando eventos de selección...');
  
  // Verificar que Select2 esté inicializado
  const addSelect2Ready = $('#addSelectEmailDropdown').hasClass('select2-hidden-accessible');
  const editSelect2Ready = $('#editSelectEmailDropdown').hasClass('select2-hidden-accessible');
  
  console.log('🔎 Select2 estado:');
  console.log('  Add modal ready:', addSelect2Ready);
  console.log('  Edit modal ready:', editSelect2Ready);
  
  if (!addSelect2Ready || !editSelect2Ready) {
    console.warn('⚠️ Select2 no está completamente inicializado. Reintentando...');
    setTimeout(() => {
      setupEmailSelectionEvents();
    }, 1000);
    return;
  }
  
  // Función para obtener nombre por email
  window.getClientNameByEmail = function(email) {
    console.log('🔍 Buscando cliente por email:', email);
    for (let i = 0; i < allClients.length; i++) {
      if (allClients[i].correo_electronico === email) {
        console.log('✅ Cliente encontrado:', allClients[i]);
        return allClients[i].nombre_cliente;
      }
    }
    console.warn('❌ Cliente no encontrado para email:', email);
    return null;
  };
  
  // Función común para asignar nombre (GLOBAL)
  window.asignarNombre = function(selectedValue, mode) {
    console.log(`🎯 ASIGNAR NOMBRE (${mode}):`, selectedValue);
    
    const campoNombre = mode === 'edit' ? '#editClientName' : '#addClientName';
    
    if (selectedValue && selectedValue !== '') {
      // Buscar directamente en allClients
      let nombreEncontrado = null;
      
      for (let i = 0; i < allClients.length; i++) {
        if (allClients[i].correo_electronico === selectedValue) {
          nombreEncontrado = allClients[i].nombre_cliente;
          console.log('✅ Cliente encontrado:', allClients[i]);
          break;
        }
      }
      
      if (nombreEncontrado) {
        // Asignar directamente
        $(campoNombre).val(nombreEncontrado);
        console.log(`✅ NOMBRE ASIGNADO (${mode}):`, nombreEncontrado);
        
        // Solo hacer verificación de límites para modal de agregar
        if (mode === 'add') {
          // Verificar límite de suscripciones activas (solo información, no bloquea)
          $.post('suscripciones_actions.php?action=check_limit', {
            correo_electronico: selectedValue
          })
          .done(function(checkResponse) {
            if (checkResponse.success) {
              if (!checkResponse.can_add) {
                // Mostrar advertencia visual sin bloquear
                $(campoNombre).css('background-color', '#fff3cd');
                $(campoNombre).after('<small class="text-warning d-block mt-1" id="limitWarning"><i class="fas fa-exclamation-triangle"></i> ' + checkResponse.message + '</small>');
              } else {
                // Limpiar advertencias previas
                $(campoNombre).css('background-color', '');
                $('#limitWarning').remove();
                if (checkResponse.details && checkResponse.details.total_suscripciones > 0) {
                  $(campoNombre).after('<small class="text-info d-block mt-1" id="limitInfo"><i class="fas fa-info-circle"></i> ' + checkResponse.message + '</small>');
                }
              }
            }
          })
          .fail(function() {
            console.warn('No se pudo verificar límite de suscripciones');
          });
        }
      } else {
        console.error(`❌ NO SE ENCONTRÓ CLIENTE (${mode}):`, selectedValue);
        $(campoNombre).val('ERROR: Cliente no encontrado');
      }
    } else {
      console.log(`🗑️ EMAIL CLEARED (${mode})`);
      $(campoNombre).val('');
    }
  }
  
  // Función global para testing manual
  window.testEmailSelection = function(email, mode = 'add') {
    console.log('🧪 TESTING MANUAL:', email, mode);
    asignarNombre(email, mode);
  };
  
  // Remover todos los eventos existentes
  $('#addSelectEmailDropdown').off();
  $('#editSelectEmailDropdown').off();
  
  console.log('🔧 CONFIGURANDO EVENTOS MÚLTIPLES...');
  
  // === ESTRATEGIA 1: EVENTOS SELECT2 NATIVOS ===
  $('#addSelectEmailDropdown').on('select2:select', function(e) {
    const selectedValue = e.params.data.id;
    console.log('🎯 SELECT2:SELECT (add):', selectedValue);
    window.asignarNombre(selectedValue, 'add');
  });
  
  $('#editSelectEmailDropdown').on('select2:select', function(e) {
    const selectedValue = e.params.data.id;
    console.log('🎯 SELECT2:SELECT (edit):', selectedValue);
    window.asignarNombre(selectedValue, 'edit');
  });
  
  // === ESTRATEGIA 2: EVENTOS CHANGE NATIVOS ===
  $('#addSelectEmailDropdown').on('change', function() {
    const selectedValue = $(this).val();
    console.log('🎯 CHANGE EVENT (add):', selectedValue);
    window.asignarNombre(selectedValue, 'add');
  });
  
  $('#editSelectEmailDropdown').on('change', function() {
    const selectedValue = $(this).val();
    console.log('🎯 CHANGE EVENT (edit):', selectedValue);
    window.asignarNombre(selectedValue, 'edit');
  });
  
  // === ESTRATEGIA 3: EVENTOS CLEAR ===
  $('#addSelectEmailDropdown').on('select2:clear', function() {
    console.log('🗑️ SELECT2:CLEAR (add)');
    window.asignarNombre('', 'add');
  });
  
  $('#editSelectEmailDropdown').on('select2:clear', function() {
    console.log('🗑️ SELECT2:CLEAR (edit)');
    asignarNombre('', 'edit');
  });
  
  // === ESTRATEGIA 4: DELEGACIÓN DE EVENTOS ===
  $(document).on('change', '#addSelectEmailDropdown', function() {
    const selectedValue = $(this).val();
    console.log('🎯 DELEGATED CHANGE (add):', selectedValue);
    asignarNombre(selectedValue, 'add');
  });
  
  $(document).on('change', '#editSelectEmailDropdown', function() {
    const selectedValue = $(this).val();
    console.log('🎯 DELEGATED CHANGE (edit):', selectedValue);
    asignarNombre(selectedValue, 'edit');
  });
  
  console.log('✅ EVENTOS CONFIGURADOS CON MÚLTIPLES ESTRATEGIAS');
  
  // === TESTING INMEDIATO ===
  setTimeout(() => {
    console.log('🧪 PROBANDO FUNCIONALIDAD...');
    console.log('💡 Para probar manualmente: testEmailSelection("castilloemer2002@gmail.com", "add")');
    
    // Verificar eventos registrados
    const addEvents = $._data($('#addSelectEmailDropdown')[0], 'events');
    const editEvents = $._data($('#editSelectEmailDropdown')[0], 'events');
    console.log('📊 Eventos ADD registrados:', addEvents);
    console.log('📊 Eventos EDIT registrados:', editEvents);
  }, 100);
}

// Función para poblar las opciones iniciales de Select2
function populateSelect2Options() {
  console.log('🔄 Poblando opciones iniciales de Select2...');
  
  if (!allClients || allClients.length === 0) {
    console.warn('⚠️ No hay clientes para poblar');
    return;
  }
  
  console.log('📊 Clientes disponibles para poblar:', allClients);
  
  // Limpiar opciones existentes
  $('#addSelectEmailDropdown').empty().append('<option value="">Seleccione un correo electrónico...</option>');
  $('#editSelectEmailDropdown').empty().append('<option value="">Seleccione un correo electrónico...</option>');
  
  // Añadir todos los correos (no solo los primeros 10, ya que Select2 manejará el filtrado)
  let optionsAdded = 0;
  allClients.forEach((client, index) => {
    if (client.correo_electronico && client.nombre_cliente) {
      const optionText = `${client.correo_electronico} (${client.nombre_cliente})`;
      
      // Crear opciones con data attributes para fácil acceso
      const optionAdd = $('<option></option>')
        .attr('value', client.correo_electronico)
        .attr('data-nombre', client.nombre_cliente)
        .text(optionText);
        
      const optionEdit = $('<option></option>')
        .attr('value', client.correo_electronico)
        .attr('data-nombre', client.nombre_cliente)
        .text(optionText);
      
      $('#addSelectEmailDropdown').append(optionAdd);
      $('#editSelectEmailDropdown').append(optionEdit);
      optionsAdded++;
      
      console.log(`📝 Opción ${index + 1} agregada:`, {
        email: client.correo_electronico,
        nombre: client.nombre_cliente,
        texto: optionText
      });
    } else {
      console.warn(`⚠️ Cliente ${index + 1} tiene datos incompletos:`, client);
    }
  });
  
  console.log(`✅ Opciones pobladas: ${optionsAdded}/${allClients.length}`);
}

// Función específica para repoblar opciones en el modal de editar
function populateSelect2OptionsForEdit() {
  console.log('🔄 Repoblando opciones para modal de editar...');
  
  if (!allClients || allClients.length === 0) {
    console.warn('⚠️ No hay clientes para poblar en editar');
    return;
  }
  
  // Solo repoblar el dropdown de editar
  allClients.forEach((client, index) => {
    if (client.correo_electronico && client.nombre_cliente) {
      const optionText = `${client.correo_electronico} (${client.nombre_cliente})`;
      
      // Verificar si la opción ya existe
      if ($('#editSelectEmailDropdown').find(`option[value="${client.correo_electronico}"]`).length === 0) {
        const optionEdit = $('<option></option>')
          .attr('value', client.correo_electronico)
          .attr('data-nombre', client.nombre_cliente)
          .text(optionText);
        
        $('#editSelectEmailDropdown').append(optionEdit);
      }
    }
  });
  
  console.log('✅ Opciones repobladas para modal de editar');
}

function loadFilters() {
  $.getJSON('suscripciones_actions.php?action=clients', function(clients) {
    let opts = '<option value="">Buscar cliente...</option>';
    for(const c of clients) opts += `<option value="${c.id_cliente}">${c.nombre_cliente.toUpperCase()}</option>`;
    $('#filterClient').html(opts);
    
    // Configuración completa para búsqueda en tiempo real
    setTimeout(() => {
      // Destruir Select2 existente si existe
      if ($('#filterClient').hasClass('select2-hidden-accessible')) {
        $('#filterClient').select2('destroy');
      }
      
      // Configuración optimizada para búsqueda y selección
      $('#filterClient').select2({
        theme: 'bootstrap-5',
        placeholder: 'Escriba para buscar cliente...',
        allowClear: true,
        minimumInputLength: 0, // Mostrar todas las opciones al abrir
        width: '100%',
        closeOnSelect: true, // Cerrar al seleccionar
        escapeMarkup: function(markup) { return markup; }, // Permitir HTML
        templateResult: function(result) {
          // Función para resaltar texto coincidente
          if (!result.id || result.loading) return result.text;
          
          // Obtener el término de búsqueda
          const term = $('.select2-search__field').val();
          if (!term) return result.text;
          
          // Resaltar el término en el texto
          const regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
          const highlighted = result.text.replace(regex, '<mark>$1</mark>');
          return $('<span>' + highlighted + '</span>');
        },
        language: {
          searching: function () {
            return "Buscando...";
          },
          noResults: function () {
            return "No se encontraron resultados";
          },
          inputTooShort: function () {
            return "Escriba para buscar";
          }
        },
        // Configuración para mostrar el dropdown desde el principio
        dropdownAutoWidth: false,
        dropdownCssClass: 'select2-filter-dropdown',
        // Matcher personalizado para búsqueda flexible
        matcher: function(params, data) {
          // Si no hay término de búsqueda, mostrar todos los resultados
          if ($.trim(params.term) === '') {
            return data;
          }

          // Búsqueda insensible a mayúsculas y acentos
          const term = params.term.toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Quitar acentos
          
          const text = data.text.toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Quitar acentos
          
          // Búsqueda parcial: si el texto contiene el término
          if (text.indexOf(term) > -1) {
            return data;
          }

          // Buscar por palabras individuales
          const words = term.split(' ');
          let matches = true;
          
          for (let word of words) {
            if (word.trim() !== '' && text.indexOf(word.trim()) === -1) {
              matches = false;
              break;
            }
          }
          
          return matches ? data : null;
        }
      });
      
      console.log('✅ Select2 configurado con búsqueda avanzada y selección');
    }, 500);
    
  }).fail(function() {
    console.error('❌ Error al cargar clientes');
  });

  $.getJSON('suscripciones_actions.php?action=plans', function(plans) {
    console.log('📋 Planes cargados:', plans);
    let opts = '<option value="">Todos los planes</option>';
    let opts2 = '<option value="">Seleccione un plan...</option>';
    
    for(const p of plans) { 
      // Para filtros (solo nombre)
      opts += `<option value="${p.id_plan}">${p.nombre_plan}</option>`; 
      
      // Para modales (con datos de precio y días)
      const precio = p.precio || 0;
      const dias = p.duracion_dias || 30;
      opts2 += `<option value="${p.id_plan}" data-precio="${precio}" data-dias="${dias}">${p.nombre_plan}</option>`;
    }
    
    $('#filterPlan').html(opts);
    $('#addPlan, #editPlan').html(opts2);
    
    console.log('✅ Planes agregados a los selects con datos de precio y días');
  }).fail(function(xhr, status, error) {
    console.error('❌ Error cargando planes:', status, error, xhr.responseText);
  });
}

// Función para forzar recarga completa de datos
function forceTableReload() {
  if (susTable) {
    // Método 1: Limpiar cache y recargar con logging detallado
    susTable.ajax.reload(function(json) {
      // Forzar redraw adicional para asegurar actualización
      susTable.draw(false);
    }, false); // false = no resetear paginación
    
    // Método 2: Invalidar cualquier cache adicional
    setTimeout(() => {
      susTable.columns.adjust().draw(false);
    }, 200);
  }
}

// Función nuclear: destruir y recrear la tabla (último recurso)
function nuclearTableReload() {
  if (susTable) {
    susTable.destroy(); // Destruir completamente
    susTable = null;
  }
  
  // Recrear tabla después de 100ms
  setTimeout(() => {
    setupDataTable();
  }, 100);
}

function setupDataTable() {
  // Variable global para controlar la página actual
  window.currentRequestedPage = 1;
  window.currentPageSize = 10;
  
  susTable = $('#susTable').DataTable({
    serverSide: true,
    processing: true,
    destroy: true, // Permitir reinicialización
    deferRender: true,
    stateSave: false, // Deshabilitar guardado de estado
    ajax: function(data, callback, settings) {
      // Usar nuestras variables globales en lugar de data.length si es inválido
      let pageSize = window.currentPageSize;
      let start = (window.currentRequestedPage - 1) * pageSize;
      
      // Si data.length es válido, usarlo; si no, usar nuestro backup
      if (data.length > 0) {
        pageSize = data.length;
        start = data.start;
        window.currentRequestedPage = Math.floor(start / pageSize) + 1;
      }
      
      const params = {
        action: 'list',
        page: window.currentRequestedPage,
        pageSize: pageSize,
        client: $('#filterClient').val(),
        plan: $('#filterPlan').val(),
        status: $('#filterStatus').val(),
        from: $('#filterFrom').val(),
        to: $('#filterTo').val(),
        _t: Date.now() // Timestamp para evitar cache
      };

      console.log('🔄 DataTables AJAX Request:');
      console.log('  - DataTables data.start:', data.start);
      console.log('  - DataTables data.length:', data.length);
      console.log('  - Using requested page:', window.currentRequestedPage);
      console.log('  - Using page size:', pageSize);
      console.log('  - Calculated start:', start);
      console.log('  - Params being sent:', params);

      // Agregar headers anti-cache
      $.ajaxSetup({
        cache: false,
        beforeSend: function(xhr) {
          xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
          xhr.setRequestHeader('Pragma', 'no-cache');
          xhr.setRequestHeader('Expires', '0');
        }
      });

      $.getJSON('suscripciones_actions.php', params, function(response) {
        console.log('📥 Respuesta del servidor recibida:');
        console.log('  - Response completo:', response);
        
        const data_array = response.data || response;
        const meta = response.meta || {};
        
        console.log('📊 Datos procesados:');
        console.log('  - Data array length:', data_array ? data_array.length : 0);
        console.log('  - Meta objeto:', meta);
        
        // *** VERIFICACIÓN ESPECÍFICA PARA PÁGINA 2 ***
        if (window.currentRequestedPage === 2) {
          console.log('🔍 *** DEBUGGING PÁGINA 2 ***');
          console.log('  - Data array completo:', data_array);
          console.log('  - Primer elemento:', data_array && data_array.length > 0 ? data_array[0] : 'NINGUNO');
          console.log('  - Cantidad de elementos:', data_array ? data_array.length : 0);
          console.log('  - Meta datos:', meta);
        }
        
        // Debug específico para nombre_plan
        if (data_array && data_array.length > 0) {
          console.log('🔍 Primer elemento completo:', data_array[0]);
          console.log('🏷️ nombre_plan del primer elemento:', data_array[0].nombre_plan);
          console.log('🆔 id_plan del primer elemento:', data_array[0].id_plan);
          console.log('📋 plan_nombre del primer elemento:', data_array[0].plan_nombre);
          console.log('📅 periodo_nombre del primer elemento:', data_array[0].periodo_nombre);
        }
        
        // Validar y normalizar valores de meta para DataTables
        const normalizedMeta = {
          total: parseInt(meta.total) || 0,
          page: parseInt(meta.page) || 1,
          pageSize: parseInt(meta.pageSize) || 10,
          totalPages: parseInt(meta.totalPages) || 1,
          hasNextPage: meta.hasNextPage || false,
          hasPreviousPage: meta.hasPreviousPage || false
        };
        
        console.log('📈 Meta normalizado para DataTables:', normalizedMeta);
        
        // Callback para DataTables con estructura correcta
        const datatableResponse = {
          draw: data.draw,
          recordsTotal: normalizedMeta.total,
          recordsFiltered: normalizedMeta.total,
          data: data_array
        };
        
        console.log('🎯 Enviando a DataTables:', datatableResponse);
        
        // *** DEBUGGING ESPECIAL PARA PÁGINA 2 ***
        if (window.currentRequestedPage === 2) {
          console.log('🚨 *** CALLBACK PÁGINA 2 ***');
          console.log('  - datatableResponse.data:', datatableResponse.data);
          console.log('  - datatableResponse.recordsTotal:', datatableResponse.recordsTotal);
          console.log('  - datatableResponse.recordsFiltered:', datatableResponse.recordsFiltered);
          console.log('  - data.draw:', datatableResponse.draw);
        }
        
        callback(datatableResponse);
        
        // Actualizar controles de paginación personalizados
        updatePaginationControls(normalizedMeta);
      }).fail(function(jqxhr, textStatus, error) {
        console.error('❌ Error AJAX:', textStatus, error);
        console.error('❌ Response text:', jqxhr.responseText);
        console.error('❌ Status code:', jqxhr.status);
        
        // Mostrar datos vacíos en caso de error con estructura correcta para DataTables
        const errorResponse = {
          draw: data.draw,
          recordsTotal: 0,
          recordsFiltered: 0,
          data: []
        };
        
        callback(errorResponse);
        
        // Limpiar controles de paginación
        $('#paginationInfo').text('Error al cargar datos');
        $('#paginationList').html('');
      });
    },
    columns: [
      {data: 'id_suscripcion'},
      {data: 'id_cliente', className: 'd-none'},
      {data: 'nombre_cliente', render: function(data) { 
        return data ? data.toUpperCase() : ''; 
      }},
      {data: 'nombre_plan'},
      {data: 'fecha_inicio'},
      {data: 'fecha_fin'},
      {data: 'precio', render: function(data) { return parseFloat(data).toFixed(2); }},
      {data: 'pagado', render: function(data) {
        return data == 1 ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>';
      }},
      {data: 'nombre_estado', render: function(data) {
        if(data === 'ACTIVA') {
          return '<span class="badge bg-success">' + data + '</span>';
        } else if(data === 'VENCIDA') {
          return '<span class="badge bg-warning text-dark">' + data + '</span>';
        } else {
          return '<span class="badge bg-danger">' + data + '</span>';
        }
      }},
      {data: 'estado_tiempo'},
      {data: null, render: function(row) { 
        let html = `<div class="btn-group" role="group">`;
        html += `<button class="btn btn-sm btn-primary btn-edit" data-id="${row.id_suscripcion}" data-bs-toggle="modal" data-bs-target="#editModal">Editar</button>`;
        html += `<button class="btn btn-sm btn-success btn-activate" data-id="${row.id_suscripcion}">Activar</button>`;
        html += `<button class="btn btn-sm btn-danger btn-cancel" data-id="${row.id_suscripcion}">Cancelar</button>`;
        html += `</div>`;
        return html;
      }}
    ],
    pageLength: 10,
    displayLength: 10, // Agregar esto como backup
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
    },
    searching: false,
    lengthChange: false,
    info: false,
    paging: false, // Deshabilitamos la paginación de DataTables porque usamos la nuestra
    // Asegurar configuraciones de servidor
    serverSide: true,
    processing: true
  });
  
  // Agregar evento para debugging después del draw
  susTable.on('draw.dt', function(e, settings) {
    const api = new $.fn.dataTable.Api(settings);
    const pageInfo = api.page.info();
    const currentData = api.rows({page: 'current'}).data().toArray();
    
    console.log('📋 *** DataTables DRAW EVENT ***');
    console.log('  - Página actual:', pageInfo.page + 1);
    console.log('  - Registros en página actual:', currentData.length);
    console.log('  - Total de registros:', pageInfo.recordsTotal);
    console.log('  - Registros filtrados:', pageInfo.recordsFiltered);
    console.log('  - Datos en página actual:', currentData);
    
    if (pageInfo.page === 1 && currentData.length === 0) { // página 2 (0-based)
      console.log('🚨 *** PÁGINA 2 SIN DATOS - INVESTIGANDO ***');
      console.log('  - API data completa:', api.data().toArray());
      console.log('  - Rows visible:', api.rows({page: 'current'}).nodes().length);
    }
  });
}

function updatePaginationControls(meta) {
  if (!meta || typeof meta !== 'object') return;
  
  // Validar y normalizar valores de meta
  const page = Math.max(1, parseInt(meta.page) || 1);
  const pageSize = Math.max(1, parseInt(meta.pageSize) || 10);
  const total = Math.max(0, parseInt(meta.total) || 0);
  
  console.log('Meta recibido:', meta);
  console.log('Valores normalizados - Page:', page, 'PageSize:', pageSize, 'Total:', total);
  
  // Calcular from y to correctamente
  const from = total > 0 ? ((page - 1) * pageSize) + 1 : 0;
  const to = Math.min(page * pageSize, total);
  
  console.log('Calculados - From:', from, 'To:', to);
  
  const info = `Mostrando ${from} a ${to} de ${total} registros`;
  $('#paginationInfo').text(info);
  
  let paginationHtml = '';
  
  // Botón Anterior
  const hasPreviousPage = page > 1;
  if (hasPreviousPage) {
    paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${page - 1}">Anterior</a></li>`;
  } else {
    paginationHtml += `<li class="page-item disabled"><span class="page-link">Anterior</span></li>`;
  }
  
  // Números de página
  const totalPages = Math.max(1, Math.ceil(total / pageSize));
  const currentPage = page;
  
  console.log('Paginación - TotalPages:', totalPages, 'CurrentPage:', currentPage);
  
  for (let i = 1; i <= totalPages; i++) {
    if (i === currentPage) {
      paginationHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
    } else {
      paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
    }
  }
  
  // Botón Siguiente
  const hasNextPage = page < totalPages;
  if (hasNextPage) {
    paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${page + 1}">Siguiente</a></li>`;
  } else {
    paginationHtml += `<li class="page-item disabled"><span class="page-link">Siguiente</span></li>`;
  }
  
  $('#paginationList').html(paginationHtml);
}

function setupEventHandlers() {
  // Filtros
  $('#btnFilter').on('click', function() {
    susTable.ajax.reload();
  });
  
  // Paginación
  $(document).on('click', '#paginationList a.page-link', function(e) {
    e.preventDefault();
    const page = parseInt($(this).data('page'));
    console.log('🖱️ Click en página:', page);
    
    if (page && page > 0) {
      // Actualizar la variable global de página solicitada
      window.currentRequestedPage = page;
      console.log('📄 Estableciendo página solicitada:', page);
      
      // Recargar la tabla con la nueva página
      susTable.ajax.reload(function() {
        console.log('✅ Tabla recargada para página:', page);
      }, false);
    }
  });

  // Generar recordatorios
  $('#btnReminders').on('click', function() {
    if (confirm('¿Generar recordatorios automáticos para suscripciones próximas a vencer?')) {
      $(this).prop('disabled', true).text('Generando...');
      $.post('suscripciones_actions.php?action=generate_reminders', {}, function(response) {
        if (response.success) {
          alert('Recordatorios generados: ' + response.inserted);
          susTable.ajax.reload();
        } else {
          alert('Error: ' + response.error);
        }
      }, 'json').always(function() {
        $('#btnReminders').prop('disabled', false).html('<i class="fas fa-bell"></i> Generar Recordatorio');
      });
    }
  });

  // Exportar PDF
  $('#btnExportPdf').on('click', generatePDF);

  // Configurar Select2 para correos
  setupSelect2EmailDropdowns();

  // Configurar eventos de planes 
  setupPlanChangeHandlers();

  // Formularios
  setupForms();

  // Acciones de tabla
  $(document).on('click', '.btn-edit', handleEdit);
  $(document).on('click', '.btn-activate', handleActivate);
  $(document).on('click', '.btn-cancel', handleCancel);
  
  // Event handlers para modales
  $('#addModal').on('shown.bs.modal', function() {
    console.log('🔵 Modal de agregar abierto');
    
    // Inicializar fecha de hoy
    const today = new Date().toISOString().split('T')[0];
    $('#addFechaInicio').val(today);
    
    // Resetear campos
    $('#addPrecio').val('0.00');
    $('#addDuracionDias').val('30');
    $('#addFechaFin').val('');
    
    // Resetear Select2
    $('#addSelectEmailDropdown').val(null).trigger('change');
    $('#addPlan').val('').trigger('change');
    $('#addClientName').val('');
    
    // Limpiar advertencias de límites de suscripciones previas
    $('#limitWarning, #limitInfo').remove();
    $('#addClientName').css('background-color', '');
    
    console.log('✅ Modal de agregar reseteado');
  });
  
  $('#editModal').on('shown.bs.modal', function() {
    console.log('🔵 Modal de editar abierto');
    
    // Event listeners para campos de editar
    $('#editCorreoElectronico, #editClientName').off('input.debugUpdate').on('input.debugUpdate', function() {
    });
    
    // Mostrar información actual en consola para debugging
    setTimeout(() => {
      console.log('📊 Estado actual del formulario de edición:');
      console.log('  ID Suscripción:', $('#editIdSuscripcion').val());
      console.log('  Email:', $('#editCorreoElectronico').val());
      console.log('  Nombre Cliente:', $('#editClientName').val());
      console.log('  Plan:', $('#editPlan').val(), '- Texto:', $('#editPlan option:selected').text());
      console.log('  Precio:', $('#editPrecio').val());
      console.log('  Duración:', $('#editDuracionDias').val());
      console.log('  Fecha Inicio:', $('#editFechaInicio').val());
      console.log('  Fecha Fin:', $('#editFechaFin').val());
      console.log('  Estado:', $('#editEstado').val(), '- Texto:', $('#editEstado option:selected').text());
      console.log('  Observaciones:', $('#editObservaciones').val());
      
    }, 200);
  });
  
  $('#editModal').on('hide.bs.modal', function() {
    console.log('🔴 Cerrando modal de editar');
  });
}

// Configurar Select2 para los dropdowns de email (estructura inicial)
function setupSelect2EmailDropdowns() {
  console.log('🔧 Configurando estructura inicial de Select2...');
  
  // Solo crear la estructura básica, los datos y eventos se configurarán después
  console.log('✅ Estructura inicial lista, esperando datos...');
}

// Configurar handlers para cambios de plan
function setupPlanChangeHandlers() {
  console.log('🔧 Configurando plan change handlers...');
  
  // Plan change event para modal agregar
  $(document).on('change', '#addPlan', function() {
    const selectedOption = $(this).find('option:selected');
    const planId = $(this).val();
    
    console.log('📋 Plan seleccionado (add):', planId);
    console.log('📋 Opción seleccionada (add):', selectedOption);
    
    if (planId) {
      const precio = selectedOption.data('precio') || 0;
      const dias = selectedOption.data('dias') || 30;
      
      console.log('💰 Datos del plan (add) - Precio:', precio, 'Días:', dias);
      
      $('#addPrecio').val(parseFloat(precio).toFixed(2));
      $('#addDuracionDias').val(dias);
      
      console.log('✅ Campos actualizados (add) - Precio:', $('#addPrecio').val(), 'Días:', $('#addDuracionDias').val());
      
      // Actualizar fecha fin si hay fecha inicio
      const fechaInicio = $('#addFechaInicio').val();
      if (fechaInicio && dias) {
        const fecha = new Date(fechaInicio);
        fecha.setDate(fecha.getDate() + parseInt(dias));
        const fechaFin = fecha.toISOString().split('T')[0];
        $('#addFechaFin').val(fechaFin);
        console.log('📅 Fecha fin calculada (add):', fechaFin);
      }
    } else {
      $('#addPrecio').val('0.00');
      $('#addDuracionDias').val('');
      $('#addFechaFin').val('');
    }
  });

  // Plan change event para modal editar
  $(document).on('change', '#editPlan', function() {
    const selectedOption = $(this).find('option:selected');
    const planId = $(this).val();
    
    console.log('📋 Plan seleccionado (edit):', planId);
    console.log('📋 Opción seleccionada (edit):', selectedOption);
    
    if (planId) {
      const precio = selectedOption.data('precio') || 0;
      const dias = selectedOption.data('dias') || 30;
      
      console.log('💰 Datos del plan (edit) - Precio:', precio, 'Días:', dias);
      
      $('#editPrecio').val(parseFloat(precio).toFixed(2));
      $('#editDuracionDias').val(dias);
      
      console.log('✅ Campos actualizados (edit) - Precio:', $('#editPrecio').val(), 'Días:', $('#editDuracionDias').val());
      
      // Actualizar fecha fin si hay fecha inicio
      const fechaInicio = $('#editFechaInicio').val();
      if (fechaInicio && dias) {
        const fecha = new Date(fechaInicio);
        fecha.setDate(fecha.getDate() + parseInt(dias));
        const fechaFin = fecha.toISOString().split('T')[0];
        $('#editFechaFin').val(fechaFin);
        console.log('📅 Fecha fin calculada (edit):', fechaFin);
      }
    } else {
      $('#editPrecio').val('0.00');
      $('#editDuracionDias').val('');
      $('#editFechaFin').val('');
    }
  });

  // Evento para calcular fecha fin cuando cambie fecha inicio (modal agregar)
  $(document).on('change', '#addFechaInicio', function() {
    const fechaInicio = $(this).val();
    const dias = $('#addDuracionDias').val();
    if (fechaInicio && dias) {
      const fecha = new Date(fechaInicio);
      fecha.setDate(fecha.getDate() + parseInt(dias));
      const fechaFin = fecha.toISOString().split('T')[0];
      $('#addFechaFin').val(fechaFin);
      console.log('📅 Fecha fin recalculada (add):', fechaFin);
    }
  });

  // Evento para calcular fecha fin cuando cambie fecha inicio (modal editar)
  $(document).on('change', '#editFechaInicio', function() {
    const fechaInicio = $(this).val();
    const dias = $('#editDuracionDias').val();
    if (fechaInicio && dias) {
      const fecha = new Date(fechaInicio);
      fecha.setDate(fecha.getDate() + parseInt(dias));
      const fechaFin = fecha.toISOString().split('T')[0];
      $('#editFechaFin').val(fechaFin);
      console.log('📅 Fecha fin recalculada (edit):', fechaFin);
    }
  });
}

// Función para actualizar información de debug
function setupForms() {
  // Event handlers para actualizar automáticamente precio, duración y fecha fin
  setupPlanChangeHandlers();
  setupDateChangeHandlers();
  
  // Formulario agregar
  $('#addForm').on('submit', function(e) {
    e.preventDefault();
    
    // Primero validar si el cliente puede tener más suscripciones
    const correoElectronico = $('#addSelectEmailDropdown').val();
    
    if (!correoElectronico) {
      alert('Por favor seleccione un correo electrónico');
      return;
    }
    
    // Mostrar indicador de carga
    const $submitBtn = $('#addForm button[type="submit"]');
    const originalText = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Validando...');
    
    // Verificar límite de suscripciones antes de proceder
    $.post('suscripciones_actions.php?action=check_limit', {
      correo_electronico: correoElectronico
    })
    .done(function(checkResponse) {
      if (!checkResponse.success) {
        alert('Error al verificar límites: ' + (checkResponse.error || 'Error desconocido'));
        $submitBtn.prop('disabled', false).html(originalText);
        return;
      }
      
      if (!checkResponse.can_add) {
        // No puede agregar más suscripciones
        alert('⚠️ ' + checkResponse.message);
        $submitBtn.prop('disabled', false).html(originalText);
        return;
      }
      
      // Puede proceder - enviar formulario
      $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Creando suscripción...');
      
      const formData = $('#addForm').serialize();
      
      console.log('Datos del formulario enviados:', formData);
      console.log('Valores individuales:');
      console.log('- Precio:', $('input[name="precio"]').val());
      console.log('- Duración:', $('input[name="duracion_dias"]').val());
      console.log('- Plan ID:', $('#addPlan').val());
      console.log('- Plan nombre:', $('#addPlan option:selected').text());
      
      $.post('suscripciones_actions.php?action=add', formData)
        .done(function(response) {
          if (response.success) {
            $('#addModal').modal('hide');
            $('#addForm')[0].reset();
            $('#selectedEmailDisplay').hide();
            susTable.ajax.reload();
            alert('✅ Suscripción creada exitosamente');
          } else {
            alert('❌ Error: ' + (response.error || 'Error desconocido'));
          }
        })
        .fail(function() {
          alert('❌ Error de conexión al servidor');
        })
        .always(function() {
          $submitBtn.prop('disabled', false).html(originalText);
        });
    })
    .fail(function() {
      alert('❌ Error de conexión al verificar límites');
      $submitBtn.prop('disabled', false).html(originalText);
    });
  });

  // Formulario editar
  $('#editForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    
    $.post('suscripciones_actions.php?action=edit', formData)
      .done(function(response) {
        if (response.success) {
          $('#editModal').modal('hide');
          
          alert('Suscripción actualizada exitosamente');
          
          // Método más agresivo: limpiar todos los parámetros de filtro temporalmente
          const originalClient = $('#filterClient').val();
          const originalPlan = $('#filterPlan').val();
          const originalStatus = $('#filterStatus').val();
          const originalFrom = $('#filterFrom').val();
          const originalTo = $('#filterTo').val();
          
          // Limpiar filtros momentáneamente para forzar nueva consulta
          $('#filterClient, #filterPlan, #filterStatus, #filterFrom, #filterTo').val('');
          
          // Forzar reload inmediato sin filtros
          setTimeout(() => {
            susTable.ajax.reload(function() {
              // Restaurar filtros originales
              $('#filterClient').val(originalClient);
              $('#filterPlan').val(originalPlan);
              $('#filterStatus').val(originalStatus);
              $('#filterFrom').val(originalFrom);
              $('#filterTo').val(originalTo);
              
              // Reload final con filtros restaurados
              setTimeout(() => {
                susTable.ajax.reload(null, false);
              }, 200);
            }, false);
          }, 1000);
          
        } else {
          alert('Error: ' + (response.error || 'Error desconocido'));
        }
      })
      .fail(function(xhr, status, error) {
        console.error('❌ Error AJAX al actualizar:', {
          status: status,
          error: error,
          responseText: xhr.responseText
        });
        alert('Error de conexión al actualizar. Revise la consola para más detalles.');
      });
  });
}

// Configurar event handlers para cambios de plan
function setupPlanChangeHandlers() {
  // Para el formulario de agregar
  $('#addPlan').on('change', function() {
    const planId = $(this).val();
    console.log('Plan cambiado (agregar):', planId);
    if (planId) {
      updatePlanInfo(planId, 'add');
    }
  });
  
  // Para el formulario de editar
  $('#editPlan').on('change', function() {
    const planId = $(this).val();
    console.log('Plan cambiado (editar):', planId);
    if (planId) {
      updatePlanInfo(planId, 'edit');
    }
  });
}

// Configurar event handlers para cambios de fecha
function setupDateChangeHandlers() {
  // Para el formulario de agregar
  $('input[name="fecha_inicio"]').on('change', function() {
    updateFechaFin('add');
  });
  
  // Para el formulario de editar
  $('#editFechaInicio').on('change', function() {
    updateFechaFin('edit');
  });
}

// Actualizar información del plan (precio y duración)
function updatePlanInfo(planId, formType) {
  const planSelect = formType === 'add' ? $('#addPlan') : $('#editPlan');
  const selectedOption = planSelect.find('option:selected');
  const planName = selectedOption.text().trim();
  
  if (!planName || planName === 'Seleccione plan') {
    return;
  }
  
  // Obtener precio y duración del plan
  $.get('suscripciones_actions.php', {
    action: 'get_plan_info',
    plan: planName
  }, function(response) {
    const precioField = formType === 'add' ? 'input[name="precio"]' : '#editPrecio';
    const duracionField = formType === 'add' ? 'input[name="duracion_dias"]' : '#editDuracionDias';
    
    $(precioField).val(response.precio || 0);
    $(duracionField).val(response.duracion || 30);
    
    // Actualizar también la fecha fin
    updateFechaFin(formType);
  }, 'json').fail(function(xhr, status, error) {
    console.error('Error al obtener información del plan:', error);
  });
}

// Actualizar fecha fin basada en fecha inicio y duración
function updateFechaFin(formType) {
  const fechaInicioField = formType === 'add' ? 'input[name="fecha_inicio"]' : '#editFechaInicio';
  const duracionField = formType === 'add' ? 'input[name="duracion_dias"]' : '#editDuracionDias';
  const fechaFinField = formType === 'add' ? 'input[name="fecha_fin"]' : '#editFechaFin';
  
  const fechaInicio = $(fechaInicioField).val();
  const duracion = parseInt($(duracionField).val()) || 30;
  
  console.log('Actualizando fecha fin:', 'inicio:', fechaInicio, 'duración:', duracion, 'tipo:', formType);
  
  if (fechaInicio) {
    const inicio = new Date(fechaInicio);
    // Agregar duración - 1 días para que sea inclusivo
    inicio.setDate(inicio.getDate() + duracion - 1);
    const fechaFin = inicio.toISOString().split('T')[0];
    $(fechaFinField).val(fechaFin);
    console.log('Fecha fin calculada:', fechaFin);
  }
}

function handleEdit() {
  const id = $(this).data('id');
  console.log('🔧 Editando suscripción ID:', id);
  
  if (!id) {
    alert('Error: No se encontró ID de la suscripción');
    return;
  }
  
  // Mostrar indicador de carga
  const $button = $(this);
  const originalText = $button.text();
  $button.prop('disabled', true).text('Cargando...');
  
  const url = 'suscripciones_actions.php?action=get_subscription&id=' + id;
  console.log('📡 Haciendo petición a:', url);
  
  $.ajax({
    url: url,
    method: 'GET',
    dataType: 'json',
    timeout: 10000, // 10 segundos timeout
    success: function(response) {
      console.log('📄 Respuesta completa del servidor:', response);
      
      if (response && response.success) {
        const data = response.data;
        console.log('📄 Datos de suscripción cargados:', data);
        
        if (!data) {
          console.error('❌ No se encontraron datos de suscripción');
          alert('Error: No se encontraron datos de la suscripción');
          return;
        }
        
        // Cargar TODOS los datos en el formulario
        $('#editIdSuscripcion').val(data.id_suscripcion || '');
        $('#editCorreoElectronico').val(data.correo_electronico || '');
        $('#editClientName').val(data.nombre_cliente || '');
        $('#editPlan').val(data.id_plan || '');
        $('#editPrecio').val(data.precio || '0.00');
        $('#editDuracionDias').val(data.duracion_dias || '30');
        $('#editFechaInicio').val(data.fecha_inicio || '');
        $('#editFechaFin').val(data.fecha_fin || '');
        $('#editEstado').val(data.id_estadoSuscripcion || '1');
        $('#editObservaciones').val(data.observaciones || '');
        
        console.log('✅ Datos cargados en el formulario');
        
        console.log('✅ Formulario de edición completamente cargado');
      } else {
        const errorMsg = (response && response.error) ? response.error : 'Error desconocido del servidor';
        console.error('❌ Error en respuesta:', errorMsg);
        console.error('❌ Respuesta completa:', response);
        alert('Error al cargar datos: ' + errorMsg);
      }
    },
    error: function(xhr, status, error) {
      console.error('❌ Error AJAX al cargar suscripción:');
      console.error('  Status:', status);
      console.error('  Error:', error);
      console.error('  Response status:', xhr.status);
      console.error('  Response text:', xhr.responseText);
      console.error('  Ready state:', xhr.readyState);
      
      let userMessage = 'Error de conexión al cargar datos.';
      
      if (xhr.status === 404) {
        userMessage = 'Error 404: Archivo suscripciones_actions.php no encontrado';
      } else if (xhr.status === 500) {
        userMessage = 'Error 500: Error interno del servidor. Revise los logs de PHP.';
      } else if (xhr.status === 0) {
        userMessage = 'Error de conexión: No se pudo conectar al servidor';
      } else if (status === 'timeout') {
        userMessage = 'Error: Tiempo de espera agotado';
      } else if (status === 'parsererror') {
        userMessage = 'Error: Respuesta del servidor no es JSON válido';
      }
      
      alert(userMessage + '\n\nDetalles en consola (F12)');
    },
    complete: function() {
      // Restaurar botón
      $button.prop('disabled', false).text(originalText);
    }
  });
}

function handleActivate() {
  const id = $(this).data('id');
  if (confirm('¿Activar esta suscripción?')) {
    $.post('suscripciones_actions.php?action=activate', {id: id})
      .done(function(response) {
        if (response.success) {
          alert('Suscripción activada exitosamente');
          setTimeout(() => {
            forceTableReload();
          }, 1000);
        } else {
          alert('Error: ' + response.error);
        }
      })
      .fail(function() {
        alert('Error de conexión al servidor');
      });
  }
}

function handleCancel() {
  const id = $(this).data('id');
  if (confirm('¿Cancelar esta suscripción?')) {
    $.post('suscripciones_actions.php?action=cancel', {id: id})
      .done(function(response) {
        if (response.success) {
          alert('Suscripción cancelada exitosamente');
          setTimeout(() => {
            forceTableReload();
          }, 1000);
        } else {
          alert('Error: ' + response.error);
        }
      })
      .fail(function() {
        alert('Error de conexión al servidor');
      });
  }
}

function handleDelete() {
  const id = $(this).data('id');
  if (confirm('¿Está seguro de eliminar esta suscripción? Esta acción no se puede deshacer.')) {
    $.post('suscripciones_actions.php?action=delete', {id: id})
      .done(function(response) {
        if (response.success) {
          susTable.ajax.reload();
          alert('Suscripción eliminada exitosamente');
        } else {
          alert('Error: ' + response.error);
        }
      })
      .fail(function() {
        alert('Error de conexión al servidor');
      });
  }
}

function generatePDF() {
  const params = {
    action: 'list',
    client: $('#filterClient').val(),
    plan: $('#filterPlan').val(),
    status: $('#filterStatus').val(),
    from: $('#filterFrom').val(),
    to: $('#filterTo').val(),
    pageSize: 999999
  };
  
  // Determinar título del reporte según filtros aplicados
  let filtrosAplicados = [];
  
  if (params.client) {
    const clienteNombre = $('#filterClient option:selected').text();
    filtrosAplicados.push(`Cliente: ${clienteNombre}`);
  }
  if (params.plan) {
    const planNombre = $('#filterPlan option:selected').text();
    filtrosAplicados.push(`Plan: ${planNombre}`);
  }
  if (params.status) {
    const estadoNombre = $('#filterStatus option:selected').text();
    filtrosAplicados.push(`Estado: ${estadoNombre}`);
  }
  if (params.from && params.to) {
    filtrosAplicados.push(`Periodo: ${params.from} a ${params.to}`);
  } else if (params.from) {
    filtrosAplicados.push(`Desde: ${params.from}`);
  } else if (params.to) {
    filtrosAplicados.push(`Hasta: ${params.to}`);
  }
  
  $.getJSON('suscripciones_actions.php', params, function(response) {
    const data = response.data ? response.data : response;
    
    if (!data || data.length === 0) {
      alert('No hay datos para generar el reporte con los filtros aplicados.');
      return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'pt', 'a4');
    
    // Configurar colores exactos como el reporte de pagos
    const orangeHeaderColor = [240, 173, 78]; // Naranja del reporte de pagos (#f0ad4e)
    const whiteColor = [255, 255, 255];
    
    // Header naranja exacto como la imagen
    doc.setFillColor(orangeHeaderColor[0], orangeHeaderColor[1], orangeHeaderColor[2]);
    doc.rect(0, 0, doc.internal.pageSize.width, 120, 'F');
    
    // Logo de la empresa desde archivo - MÁS GRANDE
    const logoPath = '../../assets/img/logo_empresa.png';
    try {
      // Logo mucho más grande y mejor posicionado
      doc.addImage(logoPath, 'PNG', 15, 15, 90, 90);
    } catch (error) {
      console.log('No se pudo cargar el logo, usando logo alternativo');
      // Logo alternativo más grande si no se puede cargar el archivo
      const logoX = 60;
      const logoY = 60;
      const logoRadius = 45; // Más grande
      
      // Círculo exterior naranja más oscuro
      doc.setFillColor(220, 160, 70);
      doc.circle(logoX, logoY, logoRadius, 'F');
      
      // Círculo interior blanco
      doc.setFillColor(255, 255, 255);
      doc.circle(logoX, logoY, logoRadius - 3, 'F');
      
      // Círculo central naranja
      doc.setFillColor(orangeHeaderColor[0], orangeHeaderColor[1], orangeHeaderColor[2]);
      doc.circle(logoX, logoY, logoRadius - 8, 'F');
      
      // Dibujar las pesas del logo (dos círculos conectados)
      doc.setFillColor(80, 80, 80);
      // Barra central
      doc.rect(logoX - 15, logoY - 2, 30, 4, 'F');
      
      // Peso izquierdo
      doc.circle(logoX - 18, logoY, 8, 'F');
      doc.circle(logoX - 18, logoY - 10, 6, 'F');
      doc.circle(logoX - 18, logoY + 10, 6, 'F');
      
      // Peso derecho  
      doc.circle(logoX + 18, logoY, 8, 'F');
      doc.circle(logoX + 18, logoY - 10, 6, 'F');
      doc.circle(logoX + 18, logoY + 10, 6, 'F');
      
      // Texto "GC" en el centro
      doc.setTextColor(255, 255, 255);
      doc.setFontSize(16);
      doc.setFont('helvetica', 'bold');
      doc.text('GC', logoX - 12, logoY + 5);
    }
    
    // Texto del header reposicionado para el logo más grande
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(28);
    doc.setFont('helvetica', 'bold');
    doc.text('Reporte de Suscripciones', 130, 50);
    
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    const now = new Date();
    const fechaFormateada = 'Generado el ' + now.getDate().toString().padStart(2, '0') + ' de ' + 
                            ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                             'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][now.getMonth()] + 
                            ' de ' + now.getFullYear() + ', ' + 
                            now.getHours().toString().padStart(2, '0') + ':' + 
                            now.getMinutes().toString().padStart(2, '0');
    doc.text(fechaFormateada, 130, 72);
    doc.text('Sistema de Gestión de Suscripciones - Gym Club', 130, 88);
    
    // Información de filtros si existen (debajo del header)
    let startY = 140;
    if (filtrosAplicados.length > 0) {
      doc.setTextColor(60, 60, 60);
      doc.setFontSize(9);
      doc.setFont('helvetica', 'italic');
      doc.text('Filtros aplicados: ' + filtrosAplicados.join(' | '), 30, startY);
      startY += 20;
    }
    
    // Preparar datos de la tabla exactamente como la imagen
    const tableData = data.map(row => [
      row.id_suscripcion.toString(),
      row.nombre_cliente,
      row.nombre_plan || 'Sin plan',
      row.fecha_inicio,
      row.fecha_fin,
      parseFloat(row.precio).toFixed(2),
      row.pagado == 1 ? 'Sí' : 'No',
      row.nombre_estado
    ]);
    
    // Tabla con diseño exacto de la imagen
    doc.autoTable({
      startY: startY,
      head: [['N°\nSuscripción', 'Cliente', 'Plan', 'Fecha\nInicio', 'Fecha Fin', 'Precio', 'Pagado', 'Estado']],
      body: tableData,
      margin: { left: 75, right: 75 }, // Márgenes más grandes para centrar mejor
      tableWidth: 'auto', // Ancho automático para centrar
      styles: { 
        fontSize: 9,
        textColor: [0, 0, 0],
        lineColor: [220, 220, 220],
        lineWidth: 0.5,
        cellPadding: 6,
        valign: 'middle',
        halign: 'center' // Centrar contenido por defecto
      },
      headStyles: { 
        fillColor: [240, 173, 78], // Mismo naranja del reporte de pagos
        textColor: [255, 255, 255],
        fontSize: 10,
        fontStyle: 'bold',
        halign: 'center',
        valign: 'middle',
        cellPadding: 8
      },
      bodyStyles: {
        fillColor: [255, 255, 255]
      },
      alternateRowStyles: {
        fillColor: [250, 250, 250]
      },
      columnStyles: {
        0: { cellWidth: 75, halign: 'center' },   // N° Suscripción
        1: { cellWidth: 115, halign: 'left' },    // Cliente
        2: { cellWidth: 85, halign: 'left' },     // Plan
        3: { cellWidth: 75, halign: 'center' },   // Fecha Inicio
        4: { cellWidth: 75, halign: 'center' },   // Fecha Fin
        5: { cellWidth: 70, halign: 'left' },     // Precio
        6: { cellWidth: 60, halign: 'center' },   // Pagado
        7: { cellWidth: 75, halign: 'center' }    // Estado
      },
      didParseCell: function(data) {
        // Colorear estados exactamente como en la imagen
        if (data.column.index === 7 && data.section === 'body') {
          if (data.cell.text[0] === 'VENCIDA') {
            data.cell.styles.textColor = [220, 53, 69]; // Rojo exacto
            data.cell.styles.fontStyle = 'bold';
          } else if (data.cell.text[0] === 'ACTIVA') {
            data.cell.styles.textColor = [40, 167, 69]; // Verde exacto
            data.cell.styles.fontStyle = 'bold';
          } else {
            data.cell.styles.textColor = [108, 117, 125]; // Gris
            data.cell.styles.fontStyle = 'bold';
          }
        }
        // Colorear columna Pagado exactamente como en la imagen
        if (data.column.index === 6 && data.section === 'body') {
          if (data.cell.text[0] === 'No') {
            data.cell.styles.textColor = [220, 53, 69]; // Rojo exacto
            data.cell.styles.fontStyle = 'bold';
          } else if (data.cell.text[0] === 'Sí') {
            data.cell.styles.textColor = [40, 167, 69]; // Verde exacto  
            data.cell.styles.fontStyle = 'bold';
          }
        }
      }
    });
    
    // Agregar paginación al PDF
    const pageCount = doc.internal.getNumberOfPages();
    const pageHeight = doc.internal.pageSize.height;
    
    for (let i = 1; i <= pageCount; i++) {
      doc.setPage(i);
      
      // Número de página en la esquina inferior derecha
      doc.setTextColor(120, 120, 120);
      doc.setFontSize(10);
      doc.text(`Página ${i} de ${pageCount}`, doc.internal.pageSize.width - 80, pageHeight - 15);
      
      // Total de registros en la esquina inferior izquierda
      doc.text(`Total de registros: ${data.length}`, 30, pageHeight - 15);
      
      // Línea de pie de página
      doc.setDrawColor(220, 220, 220);
      doc.setLineWidth(0.5);
      doc.line(30, pageHeight - 25, doc.internal.pageSize.width - 30, pageHeight - 25);
    }
    
    // Abrir PDF en nueva ventana
    doc.output('dataurlnewwindow');
  }).fail(function(xhr, status, error) {
    console.error('Error al generar PDF:', error);
    alert('Error al generar el reporte PDF. Revise la consola para más detalles.');
  });
}
</script>

</body>
</html>