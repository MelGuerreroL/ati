<?php
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();
session_start();
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
  header('Location: ../../public/index.php?error=Debes iniciar sesión');
  exit;
}
require 'con_db.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Módulo de Suscripciones</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root{
      --pf-bg: #fff8f2; /* muy suave anaranjado claro */
      --pf-primary: #ff7a18; /* naranja principal */
      --pf-primary-600: #e66d10; /* naranja oscuro */
      --pf-success: #198754;
      --pf-warning: #ffb84d; /* tono dorado/naranja claro */
      --pf-secondary: #6c757d;
      --pf-card-bg: #ffffff;
      --pf-text: #2b2b2b;
    }
    body{ background: var(--pf-bg); color: var(--pf-text); }
    .card{ background: var(--pf-card-bg); border-radius: .75rem; box-shadow: 0 6px 18px rgba(34,26,20,0.04); border: 1px solid rgba(255,122,24,0.06); }
    .filters .form-control { min-width: 160px; }

    /* Buttons */
  .btn-primary{ background-color: var(--pf-primary) !important; border-color: rgba(255,122,24,0.95) !important; color: #fff !important; }
  .btn-primary:hover{ background-color: var(--pf-primary-600) !important; }
  .btn-primary:focus, .btn-primary:active{ box-shadow: 0 0 0 .25rem rgba(255,122,24,0.12) !important; }
    .btn-success{ background: linear-gradient(135deg, #ff8c00 0%, #ffa500 100%) !important; border-color: rgba(255,140,0,0.9) !important; color: #fff !important; }
    .btn-success:hover{ background: linear-gradient(135deg, #e67e00 0%, #ff9500 100%) !important; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,.15); }
    .btn-warning{ background: linear-gradient(135deg, #ffa500 0%, #ffc107 100%) !important; border-color: rgba(255,165,0,0.9) !important; color: #fff !important; }
    .btn-warning:hover{ background: linear-gradient(135deg, #ff9500 0%, #ffb700 100%) !important; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,.15); }
    .btn-secondary{ background: linear-gradient(135deg, #ff7a18 0%, #ff9044 100%) !important; border-color: rgba(255,122,24,0.9) !important; color: #fff !important; }
    .btn-secondary:hover{ background: linear-gradient(135deg, #e66d10 0%, #ff8030 100%) !important; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,.15); }

    /* Modal header */
  .modal-header{ background: linear-gradient(90deg, rgba(255,122,24,0.07), rgba(255,184,77,0.02)); border-bottom: none; }
  .modal-title{ color: var(--pf-primary); font-weight:600; }

    /* Estilos personalizados para paginación */
    .pagination .page-link {
      color: #ff7a18 !important;
      border-color: #ff7a18 !important;
      background-color: #fff !important;
    }
    .pagination .page-link:hover {
      color: #fff !important;
      background-color: #ff7a18 !important;
      border-color: #ff7a18 !important;
    }
    .pagination .page-item.active .page-link {
      background-color: #ff7a18 !important;
      border-color: #ff7a18 !important;
      color: #fff !important;
    }
    .pagination .page-item.disabled .page-link {
      color: #6c757d !important;
      background-color: #fff !important;
      border-color: #dee2e6 !important;
    }

    /* Table header */
  table.dataTable thead{ background: linear-gradient(180deg, rgba(255,122,24,0.06), rgba(255,122,24,0.02)); }
  table.dataTable thead th{ color: #7a3e10; }

    /* Badges */
    .badge.custom-warning{ background-color: var(--pf-warning); color: #212529; }
    .clickeable-pagado:hover{ opacity: 0.8; transform: scale(1.05); transition: all 0.2s ease; }
    .clickeable-pagado:hover{ opacity: 0.8; transform: scale(1.05); transition: all 0.2s ease; }

    /* Inputs */
    input.form-control, select.form-select, textarea.form-control{ border-radius: .45rem; }

    /* Card headings */
    .card h5{ color: var(--pf-primary); }
    .list-group-item{ background: transparent; border: none; padding-left: 0; }
  </style>
</head>
<body>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Módulo de Suscripciones</h2>
    <div>
      <button id="btnAdd" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">Agregar suscripción</button>
      <button id="btnReminders" class="btn btn-warning ms-2">Generar Recordatorio</button>
      <button id="btnExportPdf" class="btn btn-secondary ms-2">Reporte General PDF</button>
      <a id="btnBack" href="menuprincipal.php" class="btn btn-sm btn-outline-secondary ms-3" title="Volver al Menú">&larr; Volver al Menú</a>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-lg-6">
      <div class="card p-3">
        <h5>Resumen gráfico</h5>
        <div class="d-flex mb-2">
          <select id="periodoSelect" class="form-select w-auto me-2">
            <option value="month" selected>Este mes</option>
            <option value="3months">Últimos 3 meses</option>
            <option value="year">Último año</option>
            <option value="all">Todo</option>
          </select>
          <small class="text-muted align-self-center">Suscriptores por plan</small>
        </div>
        <canvas id="planesChart" height="140"></canvas>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-3">
        <h5>Alertas próximas</h5>
        <ul id="alertsList" class="list-group">
          <li class="list-group-item">Cargando...</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="card p-3">
    <div class="filters d-flex gap-2 mb-3">
      <select id="filterClient" class="form-select"></select>
      <select id="filterPlan" class="form-select"></select>
      <select id="filterStatus" class="form-select">
        <option value="">Todos los estados</option>
        <option value="1">ACTIVA</option>
        <option value="2">VENCIDA</option>
        <option value="3">CANCELADA</option>
      </select>
      <input id="filterFrom" type="date" class="form-control">
      <input id="filterTo" type="date" class="form-control">
      <button id="btnFilter" class="btn btn-primary">Buscar</button>
    </div>

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
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Suscripción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addForm">
        <div class="modal-body">
          <div class="mb-3">
            <label>Seleccionar Correo Electrónico</label>
            <div class="position-relative">
              <input type="text" id="searchEmail" class="form-control mb-2" placeholder="Buscar correo electrónico..." autocomplete="off">
              <div id="emailDropdown" class="dropdown-menu w-100" style="max-height: 200px; overflow-y: auto; display: none;">
                <!-- Las opciones se cargarán aquí dinámicamente -->
              </div>
            </div>
            <input type="hidden" id="selectEmail" name="correo_electronico" required>
            <div id="selectedEmailDisplay" class="form-control bg-light" style="min-height: 38px; display: flex; align-items: center; color: #6c757d; display: none;">
              <span>Ningún correo seleccionado</span>
            </div>
          </div>
          <div class="mb-3">
            <label>Nombre Cliente</label>
            <input type="text" id="addClientName" name="nombre_cliente" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Plan</label>
            <select id="addPlan" name="id_plan" class="form-select" required></select>
          </div>
          <div class="mb-3">
            <label>Días de duración</label>
            <input type="number" name="duracion_dias" class="form-control" value="30" readonly>
            <div class="form-text">Los días se asignan automáticamente según el plan seleccionado.</div>
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
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Suscripción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editForm">
        <div class="modal-body">
          <input type="hidden" id="editIdSuscripcion" name="id_suscripcion">
          <div class="mb-3">
            <label>Correo Electrónico</label>
            <div class="position-relative">
              <input type="text" id="editSearchEmail" class="form-control mb-2" placeholder="Buscar correo electrónico..." autocomplete="off">
              <div id="editEmailDropdown" class="dropdown-menu w-100" style="max-height: 200px; overflow-y: auto; display: none;">
                <!-- Las opciones se cargarán aquí dinámicamente -->
              </div>
            </div>
            <input type="hidden" id="editSelectEmail" name="correo_electronico" required>
            <div id="editSelectedEmailDisplay" class="form-control bg-light" style="min-height: 38px; display: flex; align-items: center; color: #6c757d; display: none;">
              <span>Ningún correo seleccionado</span>
            </div>
          </div>
          <div class="mb-3">
            <label>Nombre Cliente</label>
            <input type="text" id="editClientName" name="nombre_cliente" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Plan</label>
            <select id="editPlan" name="id_plan" class="form-select" required></select>
          </div>
          <div class="mb-3">
            <label>Días de duración</label>
            <input type="number" id="editDuracionDias" name="duracion_dias" class="form-control" value="30">
            <div class="form-text">Los días se actualizan automáticamente según el plan seleccionado.</div>
          </div>
          <div class="mb-3">
            <label>Fecha inicio</label>
            <input type="date" id="editFechaInicio" name="fecha_inicio" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Fecha fin</label>
            <input type="date" id="editFechaFin" name="fecha_fin" class="form-control" readonly>
            <div class="form-text">Se calcula automáticamente a partir de la fecha de inicio y los días de duración.</div>
          </div>
          <div class="mb-3">
            <label>Precio</label>
            <input type="number" step="0.01" id="editPrecio" name="precio" class="form-control" value="0.00">
            <div class="form-text">El precio se actualiza automáticamente según el plan seleccionado.</div>
          </div>
          <div class="mb-3">
            <label>Estado</label>
            <select id="editEstado" name="id_estado" class="form-select" required>
              <option value="1">ACTIVA</option>
              <option value="2">VENCIDA</option>
              <option value="3">CANCELADA</option>
            </select>
          </div>
          <div class="mb-3">
            <label>Observaciones</label>
            <textarea id="editObservaciones" name="observaciones" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
// Función para actualizar estados automáticamente
function updatePaymentStatusesAuto() {
  $.post('suscripciones_actions.php?action=update_auto_status', {}, function(response) {
    console.log('Auto status update:', response);
  }, 'json').fail(function(xhr) {
    console.log('Auto status update failed:', xhr.responseText);
  });
}

let planesChart;
let allClients = []; // Variable global para almacenar todos los clientes

function loadClients() {
  console.log('Cargando correos...');
  // Construir la URL basada en la ubicación actual
  const currentPath = window.location.pathname;
  const url = currentPath.includes('vistasdelprograma') ? 'get_clients.php' : 'src/vistasdelprograma/get_clients.php';
  console.log('URL de la petición:', url);
  
  $.ajax({
    url: url,
    dataType: 'json',
    method: 'GET',
    success: function(response) {
      console.log('Respuesta completa del servidor:', response);
      
      try {
        if (!response || typeof response !== 'object') {
          throw new Error('Respuesta inválida del servidor');
        }

        if (!response.success) {
          throw new Error(response.error || 'Error desconocido al cargar los correos');
        }
        
        if (!response.data || !Array.isArray(response.data) || response.data.length === 0) {
          console.log('No se encontraron correos');
          allClients = [];
          updateEmailDropdowns([]);
          return;
        }

        // Almacenar todos los clientes para búsqueda
        allClients = response.data.filter(client => client && client.correo_electronico);
        console.log('Correos encontrados:', allClients.length);
        
        // Actualizar ambos dropdowns
        updateEmailDropdowns(allClients);
        
        console.log('Dropdowns actualizados con', allClients.length, 'correos');
      } catch (e) {
        console.error('Error procesando la respuesta:', e);
        alert('Error al procesar los datos: ' + e.message);
      }
    },
    error: function(xhr, status, error) {
      console.error('Error en la petición AJAX:', {
        status: status,
        error: error,
        response: xhr.responseText
      });
      try {
        const errorResponse = JSON.parse(xhr.responseText);
        alert('Error al cargar los correos: ' + (errorResponse.error || error));
      } catch (e) {
        alert('Error al cargar los correos. Por favor, intente de nuevo.');
      }
    }
  });
}

// Función para actualizar los dropdowns de email con búsqueda
function updateEmailDropdowns(clients) {
  const addDropdown = $('#emailDropdown');
  const editDropdown = $('#editEmailDropdown');
  
  // Limpiar dropdowns
  addDropdown.empty();
  editDropdown.empty();
  
  if (clients.length === 0) {
    const noDataItem = '<div class="dropdown-item text-muted">No hay correos registrados</div>';
    addDropdown.append(noDataItem);
    editDropdown.append(noDataItem);
    return;
  }
  
  // Agregar clientes a ambos dropdowns
  clients.forEach(client => {
    const item = `<div class="dropdown-item" data-email="${client.correo_electronico}" style="cursor: pointer;">${client.correo_electronico}</div>`;
    addDropdown.append(item);
    editDropdown.append(item);
  });
}

// Función para filtrar emails según la búsqueda
function filterEmails(searchTerm, dropdownId) {
  const dropdown = $(dropdownId);
  const filteredClients = allClients.filter(client => 
    client.correo_electronico.toLowerCase().includes(searchTerm.toLowerCase())
  );
  
  dropdown.empty();
  
  if (filteredClients.length === 0) {
    dropdown.append('<div class="dropdown-item text-muted">No se encontraron resultados</div>');
  } else {
    filteredClients.forEach(client => {
      const item = `<div class="dropdown-item" data-email="${client.correo_electronico}" style="cursor: pointer;">${client.correo_electronico}</div>`;
      dropdown.append(item);
    });
  }
}

// Función para seleccionar email
function selectEmail(email, isEdit = false) {
  if (isEdit) {
    $('#editSelectEmail').val(email);
    $('#editSelectedEmailDisplay span').text(email || 'Ningún correo seleccionado');
    $('#editSelectedEmailDisplay').toggle(!!email);
    $('#editSearchEmail').val('').attr('placeholder', email ? 'Cambiar correo electrónico...' : 'Buscar correo electrónico...');
    $('#editEmailDropdown').hide();
  } else {
    $('#selectEmail').val(email);
    $('#selectedEmailDisplay span').text(email || 'Ningún correo seleccionado');
    $('#selectedEmailDisplay').toggle(!!email);
    $('#searchEmail').val('').attr('placeholder', email ? 'Cambiar correo electrónico...' : 'Buscar correo electrónico...');
    $('#emailDropdown').hide();
  }
}

$(function(){
  // Ejecutar actualización automática de estados al cargar la página
  updatePaymentStatusesAuto();
  
  // Configurar actualización automática cada 5 minutos (300000 ms)
  setInterval(function() {
    console.log('Ejecutando actualización automática de estados...');
    updatePaymentStatusesAuto();
    // Recargar la tabla si es necesario
    if (typeof loadTable === 'function') {
      loadTable(currentPage);
    }
  }, 300000); // 5 minutos
  
  loadFilters();
  initTable();
  drawChart();
  loadAlerts();
  updatePlanInfo(); // Inicializar la funcionalidad de precios y duración automáticos
  
  // Event listeners para el buscador de correos
  
  // Buscador para agregar suscripción
  $('#searchEmail').on('input focus', function() {
    const searchTerm = $(this).val();
    filterEmails(searchTerm, '#emailDropdown');
    $('#emailDropdown').show();
  });
  
  // Buscador para editar suscripción
  $('#editSearchEmail').on('input focus', function() {
    const searchTerm = $(this).val();
    filterEmails(searchTerm, '#editEmailDropdown');
    $('#editEmailDropdown').show();
  });
  
  // Seleccionar email del dropdown (agregar)
  $(document).on('click', '#emailDropdown .dropdown-item[data-email]', function() {
    const email = $(this).data('email');
    selectEmail(email, false);
    // Auto-llenar nombre si existe
    const client = allClients.find(c => c.correo_electronico === email);
    if (client && client.nombre_cliente) {
      $('#addClientName').val(client.nombre_cliente);
    }
  });
  
  // Seleccionar email del dropdown (editar)
  $(document).on('click', '#editEmailDropdown .dropdown-item[data-email]', function() {
    const email = $(this).data('email');
    selectEmail(email, true);
    // Auto-llenar nombre si existe
    const client = allClients.find(c => c.correo_electronico === email);
    if (client && client.nombre_cliente) {
      $('#editClientName').val(client.nombre_cliente);
    }
  });
  
  // Ocultar dropdown al hacer click fuera
  $(document).on('click', function(e) {
    if (!$(e.target).closest('.position-relative').length) {
      $('#emailDropdown, #editEmailDropdown').hide();
    }
  });
  
  // Teclas de navegación en los buscadores
  $('#searchEmail, #editSearchEmail').on('keydown', function(e) {
    const dropdown = $(this).siblings('.dropdown-menu');
    const items = dropdown.find('.dropdown-item[data-email]');
    const activeItem = dropdown.find('.dropdown-item.active');
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (activeItem.length === 0) {
        items.first().addClass('active bg-primary text-white');
      } else {
        const next = activeItem.removeClass('active bg-primary text-white').next('[data-email]');
        if (next.length) {
          next.addClass('active bg-primary text-white');
        } else {
          items.first().addClass('active bg-primary text-white');
        }
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (activeItem.length === 0) {
        items.last().addClass('active bg-primary text-white');
      } else {
        const prev = activeItem.removeClass('active bg-primary text-white').prev('[data-email]');
        if (prev.length) {
          prev.addClass('active bg-primary text-white');
        } else {
          items.last().addClass('active bg-primary text-white');
        }
      }
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (activeItem.length) {
        activeItem.click();
      }
    } else if (e.key === 'Escape') {
      dropdown.hide();
    }
  });
  
  // Limpiar selección activa al mover el mouse
  $(document).on('mouseenter', '#emailDropdown .dropdown-item, #editEmailDropdown .dropdown-item', function() {
    $(this).siblings().removeClass('active bg-primary text-white');
  });
  
  // Función para formatear nombres: Primera letra en mayúscula, resto en minúscula, un solo espacio entre palabras
  function formatearNombre(texto) {
    if (!texto) return '';
    
    // Remover espacios múltiples y convertir a minúsculas
    let nombre = texto.trim().toLowerCase().replace(/\s+/g, ' ');
    
    // Capitalizar primera letra de cada palabra
    return nombre.replace(/\b\w/g, function(letra) {
      return letra.toUpperCase();
    });
  }
  
  // Función para validar y formatear en tiempo real
  function validarYFormatearNombre(input) {
    const valor = input.val();
    const cursorPosition = input[0].selectionStart;
    
    // Permitir solo letras, espacios y algunos caracteres especiales (acentos)
    let valorLimpio = valor.replace(/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s]/g, '');
    
    // No permitir más de un espacio consecutivo, pero permitir espacios
    valorLimpio = valorLimpio.replace(/\s{2,}/g, ' ');
    
    // No permitir espacios al inicio
    if (valorLimpio.startsWith(' ')) {
      valorLimpio = valorLimpio.substring(1);
    }
    
    // Solo formatear (capitalizar) cuando el usuario termine de escribir una palabra
    // Para esto, solo aplicamos capitalización si hay espacios o si es al final
    let valorFormateado = valorLimpio;
    
    // Si el último caracter no es un espacio, aplicar capitalización
    if (!valorLimpio.endsWith(' ') && valorLimpio.length > 0) {
      valorFormateado = formatearNombre(valorLimpio);
    } else if (valorLimpio.length > 0) {
      // Si termina en espacio, capitalizar solo las palabras completas
      const palabras = valorLimpio.split(' ');
      const ultimaPalabra = palabras.pop(); // Quitar la última (vacía por el espacio)
      const palabrasCompletas = palabras.map(palabra => 
        palabra.length > 0 ? palabra.charAt(0).toUpperCase() + palabra.slice(1).toLowerCase() : ''
      );
      palabrasCompletas.push(ultimaPalabra); // Agregar la última palabra sin formatear
      valorFormateado = palabrasCompletas.join(' ');
    }
    
    if (valor !== valorFormateado) {
      input.val(valorFormateado);
      
      // Restaurar posición del cursor
      const nuevaPosicion = Math.min(cursorPosition, valorFormateado.length);
      input[0].setSelectionRange(nuevaPosicion, nuevaPosicion);
    }
  }
  
  // Event listeners para formateo automático de nombres
  $('#addClientName, #editClientName').on('input', function() {
    validarYFormatearNombre($(this));
  });
  
  // Formateo completo al pegar contenido
  $('#addClientName, #editClientName').on('paste', function() {
    setTimeout(() => {
      const $input = $(this);
      const valorCompleto = formatearNombre($input.val());
      $input.val(valorCompleto);
    }, 50);
  });
  
  // Validación y formateo final al perder el foco
  $('#addClientName, #editClientName').on('blur', function() {
    const $input = $(this);
    let valor = $input.val().trim();
    
    // Formatear completamente al salir del campo
    const nombreFinal = formatearNombre(valor);
    $input.val(nombreFinal);
  });
  
  // Formateo al presionar espacio (para capitalizar la palabra anterior)
  $('#addClientName, #editClientName').on('keyup', function(e) {
    if (e.keyCode === 32) { // Espacio
      const $input = $(this);
      const valor = $input.val();
      
      // Si hay al menos una palabra completa seguida de espacio, formatear
      if (valor.includes(' ')) {
        const palabras = valor.split(' ');
        const palabrasFormateadas = palabras.map((palabra, index) => {
          // Solo formatear palabras que no estén siendo escritas (no sean la última)
          if (index < palabras.length - 1 && palabra.length > 0) {
            return palabra.charAt(0).toUpperCase() + palabra.slice(1).toLowerCase();
          }
          return palabra;
        });
        $input.val(palabrasFormateadas.join(' '));
      }
    }
  });
  
  // Load clients when add modal is shown and reset form fields
  // limpiar y preparar modal
  $('#addModal').on('show.bs.modal', function() {
    loadClients();
    $('#addForm')[0].reset();
    $('#fecha_fin').val('');
    $('input[name="precio"]').val('0.00'); // Resetear precio
    $('input[name="duracion_dias"]').val('30'); // Resetear duración
    // Limpiar selección de email
    selectEmail('', false);
  });

  // Preparar modal de edición
  $('#editModal').on('show.bs.modal', function() {
    // Limpiar selección de email antes de cargar los nuevos datos
    selectEmail('', true);
  });

  // Manejar la selección de correo
  $('#selectEmail').on('change', function() {
    console.log('Correo seleccionado:', $(this).val());
  });

  // Calcular y mostrar fecha_fin a partir de fecha_inicio + duracion_dias
  function computeFechaFin() {
    const fechaInicio = $('input[name="fecha_inicio"]').val();
    const duracion = parseInt($('input[name="duracion_dias"]').val() || '0', 10);
    const $fechaFin = $('#fecha_fin');
    if (!fechaInicio || !(duracion > 0)) {
      $fechaFin.val('');
      return;
    }
    // Crear fecha evitando problemas de zona horaria
    const [year, month, day] = fechaInicio.split('-').map(Number);
    const d = new Date(year, month - 1, day); // month es 0-indexed
    
    // Añadir duracion días (duracion - 1 para que sea inclusivo)
    // Si la suscripción inicia hoy y dura 1 día, termina hoy mismo
    d.setDate(d.getDate() + duracion - 1);
    
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    $fechaFin.val(`${yyyy}-${mm}-${dd}`);
  }

  // Función similar para el modal de editar
  function computeFechaFinEdit() {
    const fechaInicio = $('#editFechaInicio').val();
    const duracion = parseInt($('#editDuracionDias').val() || '0', 10);
    const $fechaFin = $('#editFechaFin');
    if (!fechaInicio || !(duracion > 0)) {
      $fechaFin.val('');
      return;
    }
    // Crear fecha evitando problemas de zona horaria
    const [year, month, day] = fechaInicio.split('-').map(Number);
    const d = new Date(year, month - 1, day); // month es 0-indexed
    
    // Añadir duracion días (duracion - 1 para que sea inclusivo)
    // Si la suscripción inicia hoy y dura 1 día, termina hoy mismo
    d.setDate(d.getDate() + duracion - 1);
    
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    $fechaFin.val(`${yyyy}-${mm}-${dd}`);
  }

  // Recalcular cuando cambian fecha_inicio o duracion
  $(document).on('change input', 'input[name="fecha_inicio"], input[name="duracion_dias"]', computeFechaFin);
  
  // También para el modal de editar
  $(document).on('change input', '#editFechaInicio, #editDuracionDias', computeFechaFinEdit);

  // Handle client selection
  $(document).on('click', '.select-client', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    
    $('#selectedClientId').val(id);
    $('#selectedClientName').text(name);
    $('#selectedClientInfo').removeClass('d-none');
  });

  $('#periodoSelect').on('change', drawChart);
  $('#btnFilter').on('click', function(){ loadTable(1); });

  $('#addForm').on('submit', function(e){
    e.preventDefault();
    
    // Formatear nombre antes de validar y enviar
    var $nombreInput = $('#addClientName');
    var nombre = formatearNombre($nombreInput.val().trim());
    $nombreInput.val(nombre);
    
    // Frontend validations: Nombre sólo letras y espacios; correo seleccionado
    var correo = $('#selectEmail').val() ? $('#selectEmail').val().trim() : '';
    var fechaInicio = $('input[name="fecha_inicio"]').val();
    var duracion = parseInt($('input[name="duracion_dias"]').val() || '0', 10);
    
    // Validar formato del nombre
    var nombreOk = /^[\p{L} ]+$/u.test(nombre);
    if (!nombre || nombre === '') { alert('El nombre del cliente es requerido'); return; }
    if (!nombreOk) { alert('Nombre sólo puede contener letras y espacios'); return; }
    if (correo === '') { alert('Debe seleccionar un correo electrónico'); return; }
    if (!fechaInicio) { alert('Debe seleccionar una fecha de inicio'); return; }
    if (!(duracion > 0)) { alert('Días de duración inválidos'); return; }

    // submit: server calculará fecha_fin a partir de fecha_inicio y duracion_dias
    $.post('suscripciones_actions.php?action=add', $(this).serialize(), function(resp){
      if(resp && resp.success){
        // cerrar modal usando la API de Bootstrap 5
        var modal = document.getElementById('addModal');
        var modalInstance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
        modalInstance.hide();
        loadTable(1); drawChart(); loadAlerts();
      } else {
        alert(resp && resp.error ? resp.error : 'Error al guardar la suscripción');
      }
    }, 'json').fail(function(xhr){
      // Intentar parsear JSON de error, si existe
      var msg = xhr.responseText;
      try { var j = JSON.parse(msg); msg = j.error || msg; } catch(e) {}
      alert('Error en la petición: ' + msg);
    });
  });

  // Manejar formulario de edición
  $('#editForm').on('submit', function(e){
    e.preventDefault();
    
    // Formatear nombre antes de validar y enviar
    var $nombreInput = $('#editClientName');
    var nombre = formatearNombre($nombreInput.val().trim());
    $nombreInput.val(nombre);
    
    // Validaciones similares al formulario de agregar
    var correo = $('#editSelectEmail').val() ? $('#editSelectEmail').val().trim() : '';
    var fechaInicio = $('#editFechaInicio').val();
    var duracion = parseInt($('#editDuracionDias').val() || '0', 10);
    
    // Validar formato del nombre
    var nombreOk = /^[\p{L} ]+$/u.test(nombre);
    if (!nombre || nombre === '') { 
      alert('El nombre del cliente es requerido'); 
      return; 
    }
    if (!nombreOk) { 
      alert('Nombre sólo puede contener letras y espacios'); 
      return; 
    }
    if (correo === '') { 
      alert('Debe seleccionar un correo electrónico'); 
      return; 
    }
    if (!fechaInicio) { 
      alert('Debe seleccionar una fecha de inicio'); 
      return; 
    }
    if (!(duracion > 0)) { 
      alert('Días de duración inválidos'); 
      return; 
    }

    $.post('suscripciones_actions.php?action=edit', $(this).serialize(), function(resp){
      if(resp && resp.success){
        // Cerrar modal
        var modal = document.getElementById('editModal');
        var modalInstance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
        modalInstance.hide();
        loadTable(1); 
        drawChart(); 
        loadAlerts();
        alert('Suscripción actualizada exitosamente');
      } else {
        alert(resp && resp.error ? resp.error : 'Error al actualizar la suscripción');
      }
    }, 'json').fail(function(xhr){
      var msg = xhr.responseText;
      try { 
        var j = JSON.parse(msg); 
        msg = j.error || msg; 
      } catch(e) {}
      alert('Error en la petición: ' + msg);
    });
  });

  $('#btnReminders').on('click', function(){
    const $btn = $(this);
    $btn.prop('disabled', true).text('Generando...');
    
    $.post('suscripciones_actions.php?action=generate_reminders', {}, function(r){
      console.log('Respuesta exitosa:', r);
      alert(r.message || 'Recordatorios generados correctamente');
      $btn.prop('disabled', false).text('Generar Recordatorio');
      loadAlerts();
    }, 'json').fail(function(xhr, textStatus, errorThrown){
      // Logging detallado para diagnóstico
      console.log('Error AJAX:', {
        status: xhr.status,
        statusText: xhr.statusText, 
        responseText: xhr.responseText,
        textStatus: textStatus,
        errorThrown: errorThrown
      });
      
      // Manejo de errores mejorado
      let msg = 'Error desconocido en la petición';
      
      if (xhr.status === 0) {
        msg = 'Error de conectividad. Verifique que el servidor esté funcionando.';
      } else if (xhr.status === 404) {
        msg = 'Archivo no encontrado (Error 404). Verifique la ruta del archivo.';
      } else if (xhr.status === 500) {
        msg = 'Error interno del servidor (Error 500). Revise los logs del servidor.';
      } else if (xhr.responseText) {
        try {
          const errorObj = JSON.parse(xhr.responseText);
          msg = errorObj.error || errorObj.message || xhr.responseText;
        } catch(e) {
          // Si no es JSON válido, mostrar el texto de respuesta crudo
          msg = xhr.responseText.length > 200 ? 
                xhr.responseText.substring(0, 200) + '...' : 
                xhr.responseText;
        }
      }
      
      alert('ERROR: ' + msg + '\n\nCódigo de estado: ' + xhr.status);
      $btn.prop('disabled', false).text('Generar Recordatorio');
    });
  });

  $('#btnExportPdf').on('click', function(){
    // Exportar con diseño mejorado y colores anaranjados
    const params = {
      client: $('#filterClient').val(),
      plan: $('#filterPlan').val(),
      status: $('#filterStatus').val(),
      from: $('#filterFrom').val(),
      to: $('#filterTo').val(),
      pageSize: 999999 // Solicitar todos los registros para el PDF
    };
    $.getJSON('suscripciones_actions.php?action=list', params, function(response){
      // Extraer los datos del response (que puede tener nueva estructura {data:[], meta:{}})
      const data = response.data ? response.data : response;
      
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF('p','pt','a4');
      
      // Función para cargar el logo dinámicamente
      function cargarLogo() {
        return new Promise((resolve) => {
          const img = new Image();
          img.crossOrigin = 'anonymous';
          img.onload = function() {
            try {
              const canvas = document.createElement('canvas');
              const ctx = canvas.getContext('2d');
              canvas.width = this.width;
              canvas.height = this.height;
              ctx.drawImage(this, 0, 0);
              resolve(canvas.toDataURL('image/png'));
            } catch(e) {
              resolve(null);
            }
          };
          img.onerror = () => resolve(null);
          img.src = '../../assets/img/logo_empresa.png?' + new Date().getTime(); // Cache bust
        });
      }

      // Generar PDF con logo
      cargarLogo().then(logoDataUrl => {
        // Header con diseño mejorado
        doc.setFillColor(240, 173, 78); // Color naranja de pagos (#f0ad4e)
        doc.rect(0, 0, 595, 110, 'F'); // Barra superior más alta para el logo más grande
        
        // Agregar logo si está disponible
        if (logoDataUrl) {
          try {
            doc.addImage(logoDataUrl, 'PNG', 20, 5, 100, 100);
          } catch(e) {
            // Fallback si hay error con el logo
            doc.setFillColor(255, 255, 255);
            doc.circle(70, 55, 45, 'F');
            doc.setTextColor(240, 173, 78);
            doc.setFontSize(28);
            doc.setFont(undefined, 'bold');
            doc.text('GS', 58, 63);
          }
        } else {
          // Fallback: Círculo con iniciales si no hay logo
          doc.setFillColor(255, 255, 255);
          doc.circle(70, 55, 45, 'F');
          doc.setTextColor(240, 173, 78);
          doc.setFontSize(28);
          doc.setFont(undefined, 'bold');
          doc.text('GS', 58, 63); // Gym Suscripciones
        }
      
      // Título principal
      doc.setTextColor(255, 255, 255);
      doc.setFontSize(20);
      doc.setFont(undefined, 'bold');
      doc.text('Reporte de Suscripciones', 140, 35);
      
      // Subtítulo mejorado
      doc.setFontSize(11);
      doc.setFont(undefined, 'normal');
      const fecha = new Date().toLocaleDateString('es-ES', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      doc.text('Generado el ' + fecha, 140, 50);
      
      // Información adicional
      doc.setFontSize(9);
      doc.text('Sistema de Gestión de Suscripciones - Gym Club', 140, 65);
      
      // Línea decorativa
      doc.setDrawColor(236, 151, 31); // Color naranja más oscuro (#ec971f)
      doc.setLineWidth(2);
      doc.line(40, 85, 555, 85);
      
      // Resetear color de texto
      doc.setTextColor(51, 51, 51);
      
      // Tabla con estilos personalizados
      const headers = [['N° Suscripción','N° Cliente','Cliente','Plan','Fecha Inicio','Fecha Fin','Precio','Pagado','Estado']];
      const rows = data.map(r=>{
        // Formatear precio correctamente
        let precioFormateado = '0.00';
        if (r.precio) {
          const precio = typeof r.precio === 'string' ? parseFloat(r.precio) : r.precio;
          if (!isNaN(precio)) {
            precioFormateado = precio.toFixed(2);
          }
        }
        return [
          r.id_suscripcion, 
          r.id_cliente, 
          r.nombre_cliente, 
          r.nombre_plan, 
          r.fecha_inicio, 
          r.fecha_fin, 
          precioFormateado,
          r.pagado == 1 ? 'Sí' : 'No',
          r.nombre_estado
        ];
      });
      
      doc.autoTable({ 
        startY: 115, // Mejor posición para centrar el contenido
        head: headers, 
        body: rows,
        theme: 'striped',
        headStyles: {
          fillColor: [240, 173, 78], // Color naranja de pagos
          textColor: [255, 255, 255],
          fontSize: 10, // Tamaño optimizado para headers
          fontStyle: 'bold',
          halign: 'center',
          valign: 'middle',
          lineWidth: 0.5,
          lineColor: [236, 151, 31] // Color más oscuro para bordes
        },
        bodyStyles: {
          fontSize: 9, // Tamaño optimizado para el contenido
          textColor: [51, 51, 51],
          lineWidth: 0.3,
          lineColor: [200, 200, 200],
          valign: 'middle'
        },
        alternateRowStyles: {
          fillColor: [252, 248, 240] // Fondo muy suave naranja
        },
        columnStyles: {
          0: { halign: 'center', cellWidth: 75 }, // N° Suscripción (más espacio)
          1: { halign: 'center', cellWidth: 50 }, // N° Cliente 
          2: { halign: 'left', cellWidth: 80 },   // Cliente (reducido más)
          3: { halign: 'center', cellWidth: 55 }, // Plan (reducido más)
          4: { halign: 'center', cellWidth: 60 }, // Fecha Inicio (reducido más)
          5: { halign: 'center', cellWidth: 60 }, // Fecha Fin (reducido más)
          6: { halign: 'right', cellWidth: 50, fontStyle: 'bold' }, // Precio
          7: { halign: 'center', cellWidth: 45 }, // Pagado
          8: { halign: 'center', cellWidth: 65, fontStyle: 'bold' } // Estado
        },
        margin: { left: 25, right: 25 }, // Márgenes centrados
        tableWidth: 'auto', // Ancho automático para mejor centrado
        showHead: 'everyPage', // Headers en cada página
        didParseCell: function(data) {
          // Colorear texto del estado (ahora en columna 8)
          if (data.column.index === 8 && data.cell.section === 'body') {
            const estado = data.cell.raw;
            if (estado === 'ACTIVA') {
              data.cell.styles.textColor = [34, 139, 34]; // Verde oscuro
              data.cell.styles.fontStyle = 'bold';
            } else if (estado === 'VENCIDA') {
              data.cell.styles.textColor = [220, 53, 69]; // Rojo
              data.cell.styles.fontStyle = 'bold';
            } else if (estado === 'CANCELADA') {
              data.cell.styles.textColor = [108, 117, 125]; // Gris
              data.cell.styles.fontStyle = 'normal';
            }
          }
          // Colorear texto de la columna Pagado (columna 7)
          if (data.column.index === 7 && data.cell.section === 'body') {
            const pagado = data.cell.raw;
            if (pagado === 'Sí') {
              data.cell.styles.textColor = [34, 139, 34]; // Verde oscuro
              data.cell.styles.fontStyle = 'bold';
            } else {
              data.cell.styles.textColor = [220, 53, 69]; // Rojo
              data.cell.styles.fontStyle = 'bold';
            }
          }
        },
        didDrawPage: function(data) {
          // Header en cada página (excepto la primera)
          if (data.pageNumber > 1) {
            doc.setFillColor(240, 173, 78);
            doc.rect(0, 0, 595, 50, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.text('REPORTE DE SUSCRIPCIONES - Página ' + data.pageNumber, 40, 25);
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text('Continuación del reporte general', 40, 35);
            doc.setDrawColor(236, 151, 31);
            doc.setLineWidth(2);
            doc.line(40, 40, 555, 40);
          }
          
          // Footer mejorado en cada página
          const pageHeight = doc.internal.pageSize.height;
          
          // Línea decorativa superior del footer
          doc.setDrawColor(240, 173, 78);
          doc.setLineWidth(1);
          doc.line(40, pageHeight - 40, 555, pageHeight - 40);
          
          // Información del footer
          doc.setFontSize(9);
          doc.setTextColor(108, 117, 125);
          doc.setFont(undefined, 'normal');
          
          // Lado izquierdo - información del sistema
          doc.text('GYM CLUB - Sistema de Gestión de Suscripciones', 40, pageHeight - 25);
          doc.setFontSize(8);
          doc.text('Documento generado automáticamente', 40, pageHeight - 15);
          
          // Lado derecho - número de página
          const totalPages = doc.internal.getNumberOfPages();
          doc.setFontSize(9);
          doc.setFont(undefined, 'bold');
          doc.text(`Página ${data.pageNumber}`, 555, pageHeight - 25, { align: 'right' });
          
          // Fecha de generación en el footer
          doc.setFont(undefined, 'normal');
          doc.setFontSize(8);
          const fechaHora = new Date().toLocaleDateString('es-ES', {
            year: 'numeric',
            month: '2-digit', 
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
          });
          doc.text(fechaHora, 555, pageHeight - 15, { align: 'right' });
        }
      });
      
      // Guardar el documento
      doc.save('reporte_suscripciones.pdf');
      }); // Cierre de la promesa cargarLogo()
    });
  });
});

function loadFilters(){
  $.getJSON('suscripciones_actions.php?action=clients', function(clients){
    let opts = '<option value="">Todos los clientes</option>';
    for(const c of clients) opts += `<option value="${c.id_cliente}">${c.nombre_cliente}</option>`;
    $('#filterClient').html(opts);
  });

  // Pedir planes desde el servidor (asegura los planes fijos y devuelve sus ids)
  $.getJSON('suscripciones_actions.php?action=plans', function(plans){
    let opts = '<option value="">Todos los planes</option>';
    let opts2 = '<option value="">Seleccione plan</option>';
    for(const p of plans){ opts += `<option value="${p.id_plan}">${p.nombre_plan}</option>`; opts2 += `<option value="${p.id_plan}">${p.nombre_plan}</option>` }
    $('#filterPlan').html(opts);
    $('#addPlan').html(opts2);
  }).fail(function(){
    // Fallback: usar lista fija si falla
    const fixedPlans = ['Diario','Semanal','Quincenal','Mensual','Semestral','Anual'];
    let opts = '<option value="">Todos los planes</option>';
    let opts2 = '';
    for(const p of fixedPlans){ opts += `<option value="${p}">${p}</option>`; opts2 += `<option value="${p}">${p}</option>` }
    $('#filterPlan').html(opts);
    $('#addPlan').html(opts2);
  });
}

function initTable(){
  window.table = $('#susTable').DataTable({
    columns: [
      {data:'id_suscripcion'},
      {data:'id_cliente', visible:false},
      {data:'nombre_cliente'},
      {data:'nombre_plan'},
      {data:'fecha_inicio'},
      {data:'fecha_fin'},
      {data:'precio'},
      {data:'pagado', render: function(data, type, row) {
          if (data == 1) {
              return '<span class="badge bg-success clickeable-pagado" data-id="' + row.id_suscripcion + '" data-pagado="1" style="cursor: pointer;" title="Clic para marcar como no pagado"><i class="fas fa-check"></i> Sí</span>';
          } else {
              return '<span class="badge bg-warning text-dark clickeable-pagado" data-id="' + row.id_suscripcion + '" data-pagado="0" style="cursor: pointer;" title="Clic para marcar como pagado"><i class="fas fa-times"></i> No</span>';
          }
      }},
      {data:'nombre_estado'},
      {data:'estado_tiempo', render: function(data, type, row) {
          const dias = row.dias_restantes;
          if (dias > 3) {
              return '<span class="badge bg-success">' + data + '</span>';
          } else if (dias >= 0) {
              return '<span class="badge bg-warning text-dark">' + data + '</span>';
          } else {
              return '<span class="badge bg-danger">' + data + '</span>';
          }
      }},
      {data:null, render: function(row){ 
          let html = `<div class=\"btn-group\" role=\"group\">`;
          html += `<button class=\"btn btn-sm btn-primary btn-edit\" data-id=\"${row.id_suscripcion}\" data-bs-toggle=\"modal\" data-bs-target=\"#editModal\">Editar</button>`;
          html += `<button class=\"btn btn-sm btn-success btn-activate\" data-id=\"${row.id_suscripcion}\">Activar</button>`;
          html += `<button class=\"btn btn-sm btn-danger btn-cancel\" data-id=\"${row.id_suscripcion}\">Cancelar</button>`+
                  `<button class=\"btn btn-sm btn-secondary btn-delete\" data-id=\"${row.id_suscripcion}\">Eliminar</button>`+
                  `</div>`;
          return html;
      }}
    ],
    pageLength: 10,
    paging: false, // Deshabilitar paginación nativa de DataTables
    info: false,   // Deshabilitar información nativa de DataTables
    responsive: true
  });
  loadTable();
}

let currentPage = 1;
let pageSize = 10;

function loadTable(page = 1){
  // Actualizar estados antes de cargar la tabla
  updatePaymentStatusesAuto();
  
  currentPage = page;
  const params = {
    client: $('#filterClient').val(),
    plan: $('#filterPlan').val(),
    status: $('#filterStatus').val(),
    from: $('#filterFrom').val(),
    to: $('#filterTo').val(),
    page: currentPage,
    pageSize: pageSize
  };
  $.getJSON('suscripciones_actions.php?action=list', params, function(response){
    // Extraer los datos y metadatos del response
    const data = response.data ? response.data : response;
    const meta = response.meta || {};
    
    table.clear().rows.add(data).draw();
    
    // Actualizar controles de paginación
    updatePaginationControls(meta);
  });
}

function updatePaginationControls(meta) {
  const { total = 0, page = 1, totalPages = 1, hasPreviousPage = false, hasNextPage = false } = meta;
  
  // Actualizar información de paginación con mejor redacción
  let infoText = '';
  if (total === 0) {
    infoText = 'No se encontraron suscripciones';
  } else if (total === 1) {
    infoText = '1 suscripción encontrada';
  } else if (totalPages === 1) {
    infoText = `${total} suscripciones encontradas`;
  } else {
    const start = ((page - 1) * pageSize) + 1;
    const end = Math.min(page * pageSize, total);
    if (start === end) {
      infoText = `Suscripción ${start} de ${total}`;
    } else {
      infoText = `Suscripciones ${start} - ${end} de ${total}`;
    }
  }
  $('#paginationInfo').text(infoText);
  
  // Construir controles de paginación
  let paginationHtml = '';
  
  // Botón Anterior
  const prevDisabled = !hasPreviousPage ? 'disabled' : '';
  paginationHtml += `<li class="page-item ${prevDisabled}">
    <a class="page-link" href="#" onclick="loadTable(${page - 1}); return false;">Anterior</a>
  </li>`;
  
  // Páginas numeradas
  const startPage = Math.max(1, page - 2);
  const endPage = Math.min(totalPages, page + 2);
  
  if (startPage > 1) {
    paginationHtml += `<li class="page-item">
      <a class="page-link" href="#" onclick="loadTable(1); return false;">1</a>
    </li>`;
    if (startPage > 2) {
      paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
  }
  
  for (let i = startPage; i <= endPage; i++) {
    const active = i === page ? 'active' : '';
    paginationHtml += `<li class="page-item ${active}">
      <a class="page-link" href="#" onclick="loadTable(${i}); return false;">${i}</a>
    </li>`;
  }
  
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    paginationHtml += `<li class="page-item">
      <a class="page-link" href="#" onclick="loadTable(${totalPages}); return false;">${totalPages}</a>
    </li>`;
  }
  
  // Botón Siguiente
  const nextDisabled = !hasNextPage ? 'disabled' : '';
  paginationHtml += `<li class="page-item ${nextDisabled}">
    <a class="page-link" href="#" onclick="loadTable(${page + 1}); return false;">Siguiente</a>
  </li>`;
  
  $('#paginationList').html(paginationHtml);
  
  // Ocultar controles si solo hay una página
  if (totalPages <= 1) {
    $('#paginationControls').hide();
  } else {
    $('#paginationControls').show();
  }
}

// Función para actualizar el precio y duración según el plan seleccionado
function updatePlanInfo() {
  // Para modal agregar
  const planSelect = $('#addPlan');
  const precioInput = $('input[name="precio"]');
  const duracionInput = $('input[name="duracion_dias"]');
  const fechaFinInput = $('#fecha_fin');
  
  // Para modal editar
  const editPlanSelect = $('#editPlan');
  const editPrecioInput = $('#editPrecio');
  const editDuracionInput = $('#editDuracionDias');
  const editFechaFinInput = $('#editFechaFin');
  
  // Función común para actualizar plan info
  function updatePlan(planName, precioEl, duracionEl, fechaFinEl) {
    if (planName && planName !== 'Seleccione plan') {
      $.getJSON('suscripciones_actions.php?action=get_plan_info&plan=' + encodeURIComponent(planName), function(response) {
        if (response && typeof response.precio !== 'undefined' && typeof response.duracion !== 'undefined') {
          precioEl.val(response.precio.toFixed(2));
          duracionEl.val(response.duracion);
          duracionEl.trigger('change');
        }
      }).fail(function() {
        // Fallback
        const planInfo = {
          'Diario': { precio: 70, duracion: 1 },
          'Semanal': { precio: 400, duracion: 7 },
          'Quincenal': { precio: 500, duracion: 15 },
          'Mensual': { precio: 700, duracion: 30 },
          'Semestral': { precio: 3500, duracion: 180 },
          'Anual': { precio: 7000, duracion: 365 }
        };
        const info = planInfo[planName] || { precio: 0, duracion: 30 };
        precioEl.val(info.precio.toFixed(2));
        duracionEl.val(info.duracion);
        duracionEl.trigger('change');
      });
    } else {
      precioEl.val('0.00');
      duracionEl.val('30');
      fechaFinEl.val('');
    }
  }
  
  // Eventos para modal agregar
  planSelect.on('change', function() {
    const planName = planSelect.find('option:selected').text();
    updatePlan(planName, precioInput, duracionInput, fechaFinInput);
  });
  
  // Eventos para modal editar
  editPlanSelect.on('change', function() {
    const planName = editPlanSelect.find('option:selected').text();
    updatePlan(planName, editPrecioInput, editDuracionInput, editFechaFinInput);
  });
}

// Delegated handlers for table actions
// Manejar botón editar
$(document).on('click', '.btn-edit', function(){
  const id = $(this).data('id');
  
  // Cargar datos de la suscripción
  $.getJSON('suscripciones_actions.php?action=get_subscription', { id: id }, function(data) {
    if (data && data.success && data.subscription) {
      const sub = data.subscription;
      
      // Llenar el formulario
      $('#editIdSuscripcion').val(sub.id_suscripcion);
      $('#editClientName').val(sub.nombre_cliente);
      $('#editFechaInicio').val(sub.fecha_inicio);
      $('#editFechaFin').val(sub.fecha_fin);
      $('#editPrecio').val(parseFloat(sub.precio || 0).toFixed(2));
      $('#editDuracionDias').val(sub.duracion_dias || 30);
      $('#editObservaciones').val(sub.observaciones || '');
      
      // Cargar correos y planes, luego seleccionar los correctos
      loadClientsForEdit(sub.correo_electronico);
      loadPlansForEdit(sub.id_plan, sub.id_estado);
    } else {
      alert('Error al cargar los datos de la suscripción');
    }
  }).fail(function() {
    alert('Error al obtener los datos de la suscripción');
  });
});

function loadClientsForEdit(selectedEmail) {
  const url = window.location.pathname.includes('vistasdelprograma') ? 'get_clients.php' : 'src/vistasdelprograma/get_clients.php';
  
  $.ajax({
    url: url,
    dataType: 'json',
    method: 'GET',
    success: function(response) {
      console.log('Respuesta para edición:', response);
      
      if (response && response.success && response.data) {
        // Actualizar la lista global de clientes
        allClients = response.data.filter(client => client && client.correo_electronico);
        
        // Actualizar el dropdown de edición
        updateEmailDropdowns(allClients);
        
        // Seleccionar el correo actual
        if (selectedEmail) {
          selectEmail(selectedEmail, true); // true para indicar que es edición
          
          // Auto-llenar nombre si existe
          const client = allClients.find(c => c.correo_electronico === selectedEmail);
          if (client && client.nombre_cliente) {
            $('#editClientName').val(client.nombre_cliente);
          }
        }
      } else {
        console.error('Error en la respuesta de get_clients para edición:', response);
        alert('Error al cargar correos electrónicos');
      }
    },
    error: function(xhr, status, error) {
      console.error('Error AJAX al cargar clientes para edición:', {
        status: status,
        error: error,
        response: xhr.responseText
      });
      alert('Error al cargar correos electrónicos para edición');
    }
  });
}

function loadPlansForEdit(selectedPlanId, selectedEstadoId) {
  $.getJSON('suscripciones_actions.php?action=plans', function(plans) {
    const planSelect = $('#editPlan');
    planSelect.empty();
    planSelect.append('<option value="">Seleccione plan</option>');
    
    plans.forEach(plan => {
      const selected = plan.id_plan == selectedPlanId ? 'selected' : '';
      planSelect.append(`<option value="${plan.id_plan}" ${selected}>${plan.nombre_plan}</option>`);
    });
    
    // Seleccionar estado
    $('#editEstado').val(selectedEstadoId || '1');
  });
}

// Manejar pago de suscripción
$(document).on('click', '.btn-pay', function(){
  const id = $(this).data('id');
  if (!confirm('¿Confirmar el pago de esta suscripción?')) return;
  
  const $btn = $(this);
  $btn.prop('disabled', true).text('Procesando...');
  
  $.post('suscripciones_actions.php?action=pay', { id }, function(r){
    console.log('pay response', r);
    if (r && r.success) { 
      loadTable(currentPage); 
      loadAlerts(); 
      alert(r.message || 'Pago registrado exitosamente.'); 
    } else {
      var msg = r && r.error ? r.error : 'Error al procesar el pago';
      alert(msg);
    }
    $btn.prop('disabled', false).text('Pagar');
  }, 'json').fail(function(xhr){ 
    alert('Error en la petición: ' + (xhr.responseText || 'Error desconocido'));
    $btn.prop('disabled', false).text('Pagar');
  });
});

// Manejar cambio de estado pagado
$(document).on('click', '.clickeable-pagado', function(){
  const id = $(this).data('id');
  const estadoActual = $(this).data('pagado');
  const nuevoEstado = estadoActual == 1 ? 0 : 1;
  const accion = nuevoEstado == 1 ? 'marcar como pagado' : 'marcar como no pagado';
  
  if (!confirm(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} esta suscripción?`)) return;
  
  const $badge = $(this);
  const originalText = $badge.html();
  $badge.html('<i class="fas fa-spinner fa-spin"></i> Procesando...');
  
  $.post('suscripciones_actions.php?action=toggle_payment', { id, estado: nuevoEstado }, function(r){
    console.log('toggle_payment response', r);
    if (r && r.success) { 
      loadTable(currentPage); 
      loadAlerts(); 
      if (r.message) {
        alert(r.message);
      }
    } else {
      var msg = r && r.error ? r.error : 'Error al actualizar estado de pago';
      alert(msg);
      $badge.html(originalText);
    }
  }, 'json').fail(function(xhr){ 
    alert('Error en la petición: ' + (xhr.responseText || 'Error desconocido'));
    $badge.html(originalText);
  });
});

$(document).on('click', '.btn-cancel', function(){
  const id = $(this).data('id');
  if (!confirm('¿Cancelar esta suscripción?')) return;
  $.post('suscripciones_actions.php?action=cancel', { id }, function(r){
    console.log('cancel response', r);
    if (r && r.success && r.affected_rows && r.affected_rows>0) { loadTable(currentPage); loadAlerts(); alert('Suscripción cancelada.'); }
    else {
      var msg = r && r.error ? r.error : 'Error al cancelar';
      if (r && typeof r.affected_rows !== 'undefined') msg += ' (affected_rows=' + r.affected_rows + ')';
      alert(msg);
    }
  }, 'json').fail(function(xhr){ alert(xhr.responseText || 'Error'); });
});

// Activar suscripción
$(document).on('click', '.btn-activate', function(){
  const id = $(this).data('id');
  if (!confirm('¿Activar esta suscripción?')) return;
  $.post('suscripciones_actions.php?action=activate', { id }, function(r){
    console.log('activate response', r);
    if (r && r.success && r.affected_rows && r.affected_rows>0) { loadTable(currentPage); loadAlerts(); alert('Suscripción activada.'); }
    else {
      var msg = r && r.error ? r.error : 'Error al activar';
      if (r && typeof r.affected_rows !== 'undefined') msg += ' (affected_rows=' + r.affected_rows + ')';
      alert(msg);
    }
  }, 'json').fail(function(xhr){ alert(xhr.responseText || 'Error'); });
});

$(document).on('click', '.btn-delete', function(){
  const id = $(this).data('id');
  if (!confirm('¿Eliminar completamente esta suscripción?')) return;
  $.post('suscripciones_actions.php?action=delete', { id }, function(r){
    console.log('delete response', r);
    if (r && r.success && r.affected_rows && r.affected_rows>0) { loadTable(currentPage); loadAlerts(); alert('Suscripción eliminada.'); }
    else {
      var msg = r && r.error ? r.error : 'Error al eliminar';
      if (r && typeof r.affected_rows !== 'undefined') msg += ' (affected_rows=' + r.affected_rows + ')';
      alert(msg);
    }
  }, 'json').fail(function(xhr){ alert(xhr.responseText || 'Error'); });
});

function drawChart(){
  const periodo = $('#periodoSelect').val();
  $.getJSON('suscripciones_actions.php?action=summary&period=' + periodo, function(data){
    const labels = data.map(d=>d.nombre_plan);
    const vals = data.map(d=>parseInt(d.total));
    const ctx = document.getElementById('planesChart').getContext('2d');
    if(planesChart) planesChart.destroy();
    planesChart = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: [{
        label: 'Suscriptores',
        data: vals,
        backgroundColor: 'rgba(255,122,24,0.85)',
        borderColor: 'rgba(230,109,16,1)',
        borderWidth: 1
      }]},
      options: { responsive:true, scales: { y: { beginAtZero: true } } }
    });
  });
}

function loadAlerts(){
  $.getJSON('suscripciones_actions.php?action=alerts', function(list){
    const $l = $('#alertsList'); $l.empty();
    if(list.length===0) $l.append('<li class="list-group-item">No hay alertas</li>');
    for(const it of list){
        const estado = it.nombre_estado || '';
        let estadoBadge = '';
        if (estado === 'VENCIDA') estadoBadge = `<span class="badge bg-danger ms-2">${estado}</span>`;
        else estadoBadge = `<span class="badge bg-warning text-dark ms-2">${it.nombre_plan}</span>`;
        $l.append(`<li class="list-group-item d-flex justify-content-between align-items-center">${it.nombre_cliente} — ${it.fecha_fin} ${estadoBadge}</li>`);
      }
  });
}
</script>

</body>
</html>
