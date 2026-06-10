<?php
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();
session_start();
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

require_permission('suscripciones', PERM_READ);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Gestión de Suscripciones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<style>
    :root {
        --blue-primary: #1E88E5;   /* Azul elegante principal */
        --blue-dark: #1565C0;      /* Azul profundo para hover */
        --text-dark: #333;
        --text-light: #fff;
        --background-light: #f4f8fb; /* Fondo claro azulado */
    }
    body {
        font-family: 'Segoe UI', sans-serif;
        background: var(--background-light);
        margin: 0;
        padding: 20px;
        color: var(--text-dark);
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }
    .container {
        text-align: center;
    }
    .page-header h1 {
        color: var(--text-dark);
        font-size: 2.5em;
        margin-bottom: 40px;
    }
    .button-grid {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 25px;
    }
    .big-button {
        background: var(--blue-primary);
        color: var(--text-light);
        border: none;
        cursor: pointer;
        font-weight: 600;
        padding: 30px 40px;
        border-radius: 8px;
        font-size: 1.5em;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        transition: all 0.3s ease;
        width: 280px;
    }
    .big-button:hover {
        background: var(--blue-dark);
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    .big-button i {
        font-size: 3em;
    }
    .big-button.secondary {
        background: #ffffff;
        color: var(--blue-dark);
        border: 2px solid var(--blue-dark);
    }
    .big-button.secondary:hover {
        background: var(--blue-dark);
        color: var(--text-light);
    }
    .big-button.neutral {
        background: #ffffff;
        color: var(--text-dark);
        border: 2px solid #ccc;
    }
    .big-button.neutral:hover {
        background: #f0f0f0;
        color: var(--text-dark);
        border-color: var(--blue-primary);
    }
</style>

</head>
<body>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-receipt"></i> Gestión de Suscripciones</h1>
    </div>

    <div class="button-grid">
        <a href="suscripciones_agregar.php" class="big-button">
            <i class="fas fa-plus-circle"></i>
            <span>Agregar Suscripción</span>
        </a>
        <a href="suscripciones_registradas.php" class="big-button secondary">
            <i class="fas fa-list"></i>
            <span>Suscripciones Registradas</span>
        </a>
        <a href="suscripciones_alertas.php" class="big-button tertiary">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Alertas</span>
        </a>
        <a href="menuprincipal.php" class="big-button neutral">
            <i class="fas fa-home"></i>
            <span>Volver al Menú Principal</span>
        </a>
    </div>
</div>

</body>
</html>