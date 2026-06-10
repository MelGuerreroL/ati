-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-10-2025 a las 08:25:17
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gestion_suscripciones`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generar_calendario_nocturno` ()   BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_id_suscripcion INT;
  DECLARE v_id_cliente INT;
  DECLARE v_fecha_fin DATE;

  DECLARE cur CURSOR FOR
    SELECT s.id_suscripcion, s.id_cliente, s.fecha_fin
    FROM tbl_suscripcion s
    JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion
    WHERE es.nombre_estado = 'ACTIVA';

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_id_suscripcion, v_id_cliente, v_fecha_fin;
    IF done THEN LEAVE read_loop; END IF;

    IF DATEDIFF(v_fecha_fin, CURDATE()) <= 3 THEN
      INSERT INTO tbl_calendario (
        id_suscripcion,
        id_cliente,
        id_tipoNotificacion,
        id_estadoCalendario,
        fecha_notificacion,
        hora_notificacion,
        contenido
      )
      VALUES (
        v_id_suscripcion,
        v_id_cliente,
        1,
        (SELECT id_estadoCalendario FROM tbl_estado_calendario WHERE nombre_estado = 'PENDIENTE' LIMIT 1),
        CURDATE(),
        '09:00:00',
        CONCAT('Su suscripción vence el ', DATE_FORMAT(v_fecha_fin, '%Y-%m-%d'))
      );
    END IF;
  END LOOP;
  CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_marcar_calendario_enviado` (IN `p_id_calendario` INT, IN `p_proveedor` VARCHAR(100), IN `p_referencia` VARCHAR(200))   BEGIN
  UPDATE tbl_calendario
    SET id_estadoCalendario = (
        SELECT id_estadoCalendario 
        FROM tbl_estado_calendario 
        WHERE nombre_estado = 'ENVIADO' LIMIT 1
    ),
        fecha_envio = CURRENT_TIMESTAMP,
        intento_count = intento_count + 1,
        meta = JSON_SET(COALESCE(meta, JSON_OBJECT()), '$.proveedor', p_proveedor, '$.referencia', p_referencia)
    WHERE id_calendario = p_id_calendario;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_marcar_calendario_fallido` (IN `p_id_calendario` INT, IN `p_mensaje` VARCHAR(255))   BEGIN
  UPDATE tbl_calendario
    SET id_estadoCalendario = (
        SELECT id_estadoCalendario 
        FROM tbl_estado_calendario 
        WHERE nombre_estado = 'FALLIDO' LIMIT 1
    ),
        intento_count = intento_count + 1,
        fecha_envio = CURRENT_TIMESTAMP,
        meta = JSON_SET(COALESCE(meta, JSON_OBJECT()), '$.error', p_mensaje)
    WHERE id_calendario = p_id_calendario;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_procesar_notificaciones_pendientes` ()   BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_id_calendario INT;
  DECLARE v_id_suscripcion INT;
  DECLARE v_id_cliente INT;
  DECLARE v_contenido TEXT;
  DECLARE v_intentos INT DEFAULT 0;
  DECLARE v_estado_pendiente INT;
  DECLARE v_estado_enviado INT;
  DECLARE v_estado_fallido INT;

  
  DECLARE cur CURSOR FOR
    SELECT id_calendario, id_suscripcion, id_cliente, contenido, intento_count
    FROM tbl_calendario
    WHERE id_estadoCalendario = v_estado_pendiente
      AND CONCAT(fecha_notificacion, ' ', hora_notificacion) <= NOW()
    FOR UPDATE;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  
  SELECT id_estadoCalendario INTO v_estado_pendiente 
  FROM tbl_estado_calendario WHERE nombre_estado='PENDIENTE' LIMIT 1;

  SELECT id_estadoCalendario INTO v_estado_enviado 
  FROM tbl_estado_calendario WHERE nombre_estado='ENVIADO' LIMIT 1;

  SELECT id_estadoCalendario INTO v_estado_fallido 
  FROM tbl_estado_calendario WHERE nombre_estado='FALLIDO' LIMIT 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_id_calendario, v_id_suscripcion, v_id_cliente, v_contenido, v_intentos;
    IF done THEN LEAVE read_loop; END IF;

    
    SET @v_result = 1;

    IF @v_result = 1 THEN
      CALL sp_marcar_calendario_enviado(v_id_calendario, 'SIMULADO', 'N/A');
    ELSE
      CALL sp_marcar_calendario_fallido(v_id_calendario, 'Error simulado');
    END IF;

    
    IF EXISTS (
      SELECT 1 FROM tbl_suscripcion s
      JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion
      WHERE s.id_suscripcion = v_id_suscripcion
        AND s.fecha_fin < CURDATE()
        AND es.nombre_estado <> 'MOROSA'
    ) THEN
      UPDATE tbl_suscripcion
      SET id_estadoSuscripcion = (
        SELECT id_estadoSuscripcion
        FROM tbl_estado_suscripcion
        WHERE nombre_estado='MOROSA' LIMIT 1
      )
      WHERE id_suscripcion = v_id_suscripcion;

      UPDATE tbl_cliente c
      JOIN tbl_suscripcion s ON s.id_cliente = c.id_cliente
      SET c.id_estadoCliente = (
        SELECT id_estadoCliente
        FROM tbl_estado_cliente
        WHERE nombre_estado='INACTIVO' LIMIT 1
      )
      WHERE s.id_suscripcion = v_id_suscripcion;
    END IF;
  END LOOP;
  CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_calendario`
--

CREATE TABLE `tbl_calendario` (
  `id_calendario` int(11) NOT NULL,
  `id_suscripcion` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_tipoNotificacion` int(11) NOT NULL,
  `id_estadoCalendario` int(11) NOT NULL DEFAULT 1,
  `fecha_notificacion` date NOT NULL,
  `hora_notificacion` time DEFAULT '00:00:00',
  `fecha_envio` timestamp NULL DEFAULT NULL,
  `intento_count` int(11) NOT NULL DEFAULT 0,
  `contenido` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_cliente`
--

CREATE TABLE `tbl_cliente` (
  `id_cliente` int(11) NOT NULL,
  `nombre_cliente` varchar(150) NOT NULL,
  `telefono_cliente` varchar(30) DEFAULT NULL,
  `correo_electronico` varchar(150) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_estadoCliente` int(11) NOT NULL DEFAULT 1,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_cliente`
--

INSERT INTO `tbl_cliente` (`id_cliente`, `nombre_cliente`, `telefono_cliente`, `correo_electronico`, `fecha_nacimiento`, `fecha_registro`, `id_estadoCliente`, `observaciones`) VALUES
(1, 'David', '98273748', 'David@gmail.com', '2003-02-01', '2025-10-27 04:18:29', 2, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_configuracion_notificaciones`
--

CREATE TABLE `tbl_configuracion_notificaciones` (
  `id_configuracion` int(11) NOT NULL,
  `nombre_configuracion` varchar(100) DEFAULT NULL,
  `parametros` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parametros`)),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_estado_calendario`
--

CREATE TABLE `tbl_estado_calendario` (
  `id_estadoCalendario` int(11) NOT NULL,
  `nombre_estado` varchar(45) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_estado_calendario`
--

INSERT INTO `tbl_estado_calendario` (`id_estadoCalendario`, `nombre_estado`, `descripcion`) VALUES
(1, 'PENDIENTE', 'Por enviar'),
(2, 'ENVIADO', 'Enviado correctamente'),
(3, 'FALLIDO', 'Intentos fallidos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_estado_cliente`
--

CREATE TABLE `tbl_estado_cliente` (
  `id_estadoCliente` int(11) NOT NULL,
  `nombre_estado` varchar(45) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_estado_cliente`
--

INSERT INTO `tbl_estado_cliente` (`id_estadoCliente`, `nombre_estado`, `descripcion`, `creado_en`) VALUES
(1, 'ACTIVO', 'Cliente con membresía activa', '2025-10-18 01:44:45'),
(2, 'INACTIVO', 'Cliente inactivo', '2025-10-18 01:44:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_estado_suscripcion`
--

CREATE TABLE `tbl_estado_suscripcion` (
  `id_estadoSuscripcion` int(11) NOT NULL,
  `nombre_estado` varchar(45) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_estado_suscripcion`
--

INSERT INTO `tbl_estado_suscripcion` (`id_estadoSuscripcion`, `nombre_estado`, `descripcion`, `creado_en`) VALUES
(1, 'ACTIVA', 'Suscripción vigente', '2025-10-18 01:44:45'),
(2, 'VENCIDA', 'Suscripción vencida', '2025-10-18 01:44:45'),
(3, 'MOROSA', 'Pendiente de pago (mora)', '2025-10-18 01:44:45'),
(4, 'CANCELADA', 'Suscripción cancelada', '2025-10-18 01:44:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_bitacora`
--

CREATE TABLE `tbl_ms_bitacora` (
  `id_bitacora` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_objetos` int(11) NOT NULL,
  `accion` varchar(20) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_bitacora`
--

INSERT INTO `tbl_ms_bitacora` (`id_bitacora`, `fecha`, `id_usuario`, `id_objetos`, `accion`, `descripcion`) VALUES
(75, '2025-10-27', 24, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: cerratooscar36'),
(76, '2025-10-27', 24, 1, 'DELETE', 'El admin ID: 24 eliminó al usuario ID: 19.'),
(77, '2025-10-27', 24, 2, 'LOGOUT', 'El usuario ID: 24 cerró sesión.'),
(78, '2025-10-27', 24, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: cerratooscar36'),
(79, '2025-10-27', 24, 2, 'LOGOUT', 'El usuario ID: 24 cerró sesión.'),
(80, '2025-10-27', 24, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: cerratooscar36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_hist_contraseña`
--

CREATE TABLE `tbl_ms_hist_contraseña` (
  `id_hist` int(11) NOT NULL,
  `contraseña` text NOT NULL,
  `tbl_ms_usuario_id_usuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_hist_contraseña`
--

INSERT INTO `tbl_ms_hist_contraseña` (`id_hist`, `contraseña`, `tbl_ms_usuario_id_usuario`) VALUES
(14, '1df56a98fbaa1f6dbbb28b44e17bf5afdb8ed15499e6467596bb7fa7bc4f5911bad98b70029a44971a371e69636c296ea6af37a95b34bf77940f1db84ce73fdb', 24);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_parametros`
--

CREATE TABLE `tbl_ms_parametros` (
  `id_parametro` int(11) NOT NULL,
  `parametro` varchar(50) NOT NULL,
  `valor` varchar(100) NOT NULL,
  `fecha_creacion` date NOT NULL,
  `fecha_modificacion` date NOT NULL,
  `tbl_ms_usuario_id_usuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_parametros`
--

INSERT INTO `tbl_ms_parametros` (`id_parametro`, `parametro`, `valor`, `fecha_creacion`, `fecha_modificacion`, `tbl_ms_usuario_id_usuario`) VALUES
(0, 'MAX_LOGIN_ATTEMPTS', '3', '2025-10-27', '2025-10-27', 24);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_roles`
--

CREATE TABLE `tbl_ms_roles` (
  `id_rol` int(11) NOT NULL,
  `rol` varchar(30) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_roles`
--

INSERT INTO `tbl_ms_roles` (`id_rol`, `rol`, `descripcion`) VALUES
(2, 'ADMINISTRADOR', 'Tiene control total del sistema y acceso a todos los módulos de configuración.'),
(3, 'CLIENTE', 'Rol estándar, solo tiene acceso a la información de su cuenta y sus suscripciones.'),
(4, 'administrador', 'Crear, Eliminar y Modificar');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_usuario`
--

CREATE TABLE `tbl_ms_usuario` (
  `id_usuario` int(11) NOT NULL,
  `usuario` varchar(15) NOT NULL,
  `nombre_usuario` varchar(100) NOT NULL,
  `estado_usuario` varchar(100) NOT NULL,
  `contraseña` varchar(1000) NOT NULL,
  `fecha_ultima_conexion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `primer_ingreso` int(11) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `correo_electronico` varchar(60) NOT NULL,
  `tbl_ms_roles_id_rol` int(11) NOT NULL,
  `totp_secret` varchar(100) DEFAULT NULL,
  `is_2fa_enabled` tinyint(1) DEFAULT 0,
  `intentos_fallidos` int(1) DEFAULT 0,
  `estado_bloqueo` enum('ACTIVO','BLOQUEADO') DEFAULT 'ACTIVO',
  `fecha_bloqueo` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_usuario`
--

INSERT INTO `tbl_ms_usuario` (`id_usuario`, `usuario`, `nombre_usuario`, `estado_usuario`, `contraseña`, `fecha_ultima_conexion`, `primer_ingreso`, `fecha_vencimiento`, `correo_electronico`, `tbl_ms_roles_id_rol`, `totp_secret`, `is_2fa_enabled`, `intentos_fallidos`, `estado_bloqueo`, `fecha_bloqueo`) VALUES
(24, 'cerratooscar36', 'OSCAR BLADIMIR CERRATO MARTINEZ', '', '1df56a98fbaa1f6dbbb28b44e17bf5afdb8ed15499e6467596bb7fa7bc4f5911bad98b70029a44971a371e69636c296ea6af37a95b34bf77940f1db84ce73fdb', '2025-10-27 07:22:44', 1, '2026-10-27', 'cerratooscar36@gmail.com', 2, 'ZXGIYJH5VEXM4LSK', 1, 0, 'ACTIVO', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_objetos`
--

CREATE TABLE `tbl_objetos` (
  `id_objetos` int(11) NOT NULL,
  `objeto` text NOT NULL,
  `descripcion` text NOT NULL,
  `tipo_objeto` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tbl_objetos`
--

INSERT INTO `tbl_objetos` (`id_objetos`, `objeto`, `descripcion`, `tipo_objeto`) VALUES
(1, 'MANTENIMIENTO_USUARIOS', 'Formulario para crear, editar y eliminar usuarios.', 'Formulario'),
(2, 'DASHBOARD', 'Panel principal con indicadores generales.', 'Módulo'),
(3, 'MANTENIMIENTO_CLIENTES', 'Formulario para gestionar la información de los clientes.', 'Formulario'),
(4, 'GESTION_SUSCRIPCIONES', 'Formulario para crear y administrar suscripciones y pagos.', 'Módulo'),
(5, 'CONFIGURACION_PARAMETROS', 'Formulario para modificar parámetros globales del sistema (contraseñas, intentos de login, etc.).', 'Módulo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_pago`
--

CREATE TABLE `tbl_pago` (
  `id_pago` int(11) NOT NULL,
  `id_suscripcion` int(11) NOT NULL,
  `fecha_pago` timestamp NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(60) DEFAULT NULL,
  `referencia_pago` varchar(120) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_periodo`
--

CREATE TABLE `tbl_periodo` (
  `id_periodo` int(11) NOT NULL,
  `nombre_periodo` varchar(45) NOT NULL,
  `dias` int(11) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_periodo`
--

INSERT INTO `tbl_periodo` (`id_periodo`, `nombre_periodo`, `dias`, `descripcion`, `creado_en`) VALUES
(1, 'SEMANAL', 7, 'Periodo de 7 días', '2025-10-18 01:44:45'),
(2, 'QUINCENAL', 15, 'Periodo de 15 días', '2025-10-18 01:44:45'),
(3, 'MENSUAL', 30, 'Periodo aproximado de 30 días', '2025-10-18 01:44:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_permisos`
--

CREATE TABLE `tbl_permisos` (
  `id_permisos` int(11) NOT NULL,
  `permiso_insercion` char(1) NOT NULL,
  `permiso_eliminacion` char(1) NOT NULL,
  `permiso_actualizacion` char(1) NOT NULL,
  `permiso_consultar` char(1) NOT NULL,
  `tbl_ms_roles_id_rol` int(11) NOT NULL,
  `tbl_objetos_id_objeto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_plan`
--

CREATE TABLE `tbl_plan` (
  `id_plan` int(11) NOT NULL,
  `nombre_plan` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_periodo` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_reset_tokens`
--

CREATE TABLE `tbl_reset_tokens` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expiracion` datetime NOT NULL,
  `usado` tinyint(4) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tbl_reset_tokens`
--

INSERT INTO `tbl_reset_tokens` (`id`, `email`, `token`, `expiracion`, `usado`, `fecha_creacion`) VALUES
(1, 'yakelinsosa24@gmail.com', '816626c9014f1aa703a0315dda04e98fdba20890c385e8cfc2e300c8a32ac563', '2025-10-21 06:54:22', 0, '2025-10-21 03:54:22'),
(2, 'castilloemer2002@gmail.com', '494594a17125502cab0d912d1678da2941008b766ead33ad62e05b5ccfca4e2c', '2025-10-21 07:02:03', 1, '2025-10-21 04:02:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_suscripcion`
--

CREATE TABLE `tbl_suscripcion` (
  `id_suscripcion` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_plan` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `id_estadoSuscripcion` int(11) NOT NULL,
  `duracion_dias` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_tipo_notificacion`
--

CREATE TABLE `tbl_tipo_notificacion` (
  `id_tipoNotificacion` int(11) NOT NULL,
  `nombre_tipoNotificacion` varchar(100) NOT NULL,
  `periodicidad` tinyint(1) NOT NULL DEFAULT 0,
  `template` text NOT NULL,
  `id_plan` int(11) DEFAULT NULL,
  `id_configuracion` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_verificacion_codigos`
--

CREATE TABLE `tbl_verificacion_codigos` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `codigo` varchar(6) NOT NULL,
  `expiracion` datetime NOT NULL,
  `usado` tinyint(4) DEFAULT 0,
  `intentos` tinyint(4) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `tbl_verificacion_codigos`
--

INSERT INTO `tbl_verificacion_codigos` (`id`, `email`, `codigo`, `expiracion`, `usado`, `intentos`, `fecha_creacion`) VALUES
(1, 'yakelinsosa24@gmail.com', '272220', '2025-10-21 06:27:47', 0, 0, '2025-10-21 04:17:47'),
(2, 'yakelinsosa24@gmail.com', '379680', '2025-10-21 06:30:00', 1, 0, '2025-10-21 04:20:00'),
(3, 'castilloemer2002@gmail.com', '780197', '2025-10-21 06:35:07', 1, 0, '2025-10-21 04:25:07'),
(4, 'mvguerrero150@gmail.com', '539354', '2025-10-21 06:42:20', 0, 0, '2025-10-21 04:32:20'),
(5, 'gabrielafigueroa006@gmail.com', '026226', '2025-10-23 03:47:49', 1, 0, '2025-10-23 01:37:49'),
(6, 'gabrielafigueroa006@gmail.com', '481019', '2025-10-23 03:51:10', 0, 0, '2025-10-23 01:41:10'),
(7, 'gfigueroav@unah.hn', '421741', '2025-10-23 03:57:51', 1, 0, '2025-10-23 01:47:51'),
(8, 'cerratooscar36@gmail.com', '644519', '2025-10-27 06:00:35', 0, 0, '2025-10-27 04:50:35'),
(9, 'cerratooscar36@gmail.com', '174213', '2025-10-27 06:02:24', 0, 0, '2025-10-27 04:52:24'),
(10, 'cerratooscar36@gmail.com', '532504', '2025-10-27 06:03:08', 0, 0, '2025-10-27 04:53:08'),
(11, 'cerratooscar36@gmail.com', '765729', '2025-10-27 06:15:28', 0, 0, '2025-10-27 05:05:28'),
(12, 'cerratooscar36@gmail.com', '153560', '2025-10-27 06:19:09', 0, 0, '2025-10-27 05:09:09'),
(13, 'cerratooscar36@gmail.com', '349393', '2025-10-27 06:20:28', 0, 0, '2025-10-27 05:10:28'),
(14, 'fotitosjuntos93@gmail.com', '781689', '2025-10-27 06:35:04', 1, 0, '2025-10-27 05:25:04'),
(15, 'David@gmail.com', '745958', '2025-10-27 07:10:01', 0, 0, '2025-10-27 06:00:01'),
(16, 'cerratooscar15@gmail.com', '809691', '2025-10-27 07:19:42', 1, 1, '2025-10-27 06:09:42'),
(17, 'cerratooscar15@gmail.com', '599066', '2025-10-27 07:24:23', 1, 0, '2025-10-27 06:14:23'),
(18, 'cerratooscar15@mail.com', '941172', '2025-10-27 07:30:32', 0, 0, '2025-10-27 06:20:32'),
(19, 'cerratooscar36@gmail.com', '055716', '2025-10-27 07:35:44', 0, 0, '2025-10-27 06:25:44'),
(20, 'cerratooscar15@gmail.com', '759487', '2025-10-27 07:39:38', 0, 0, '2025-10-27 06:29:38'),
(21, 'cerratooscar36@gmail.com', '381390', '2025-10-27 07:42:31', 0, 0, '2025-10-27 06:32:31'),
(22, 'cerratooscar36@gmail.com', '048320', '2025-10-27 07:44:13', 1, 0, '2025-10-27 06:34:13'),
(23, 'cerratooscar36@gmail.com', '269866', '2025-10-27 08:11:29', 1, 1, '2025-10-27 07:01:29'),
(24, 'cerratooscar36@gmail.com', '899164', '2025-10-27 08:21:40', 1, 0, '2025-10-27 07:11:40');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `tbl_calendario`
--
ALTER TABLE `tbl_calendario`
  ADD PRIMARY KEY (`id_calendario`),
  ADD KEY `id_suscripcion` (`id_suscripcion`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_tipoNotificacion` (`id_tipoNotificacion`),
  ADD KEY `idx_calendario_fecha` (`fecha_notificacion`,`hora_notificacion`),
  ADD KEY `idx_calendario_estado` (`id_estadoCalendario`);

--
-- Indices de la tabla `tbl_cliente`
--
ALTER TABLE `tbl_cliente`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `uq_cliente_correo` (`correo_electronico`),
  ADD KEY `idx_cliente_telefono` (`telefono_cliente`),
  ADD KEY `id_estadoCliente` (`id_estadoCliente`);

--
-- Indices de la tabla `tbl_configuracion_notificaciones`
--
ALTER TABLE `tbl_configuracion_notificaciones`
  ADD PRIMARY KEY (`id_configuracion`);

--
-- Indices de la tabla `tbl_estado_calendario`
--
ALTER TABLE `tbl_estado_calendario`
  ADD PRIMARY KEY (`id_estadoCalendario`);

--
-- Indices de la tabla `tbl_estado_cliente`
--
ALTER TABLE `tbl_estado_cliente`
  ADD PRIMARY KEY (`id_estadoCliente`);

--
-- Indices de la tabla `tbl_estado_suscripcion`
--
ALTER TABLE `tbl_estado_suscripcion`
  ADD PRIMARY KEY (`id_estadoSuscripcion`);

--
-- Indices de la tabla `tbl_ms_bitacora`
--
ALTER TABLE `tbl_ms_bitacora`
  ADD PRIMARY KEY (`id_bitacora`),
  ADD KEY `fk_bitacora_usuario` (`id_usuario`),
  ADD KEY `fk_bitacora_objetos` (`id_objetos`);

--
-- Indices de la tabla `tbl_ms_hist_contraseña`
--
ALTER TABLE `tbl_ms_hist_contraseña`
  ADD PRIMARY KEY (`id_hist`),
  ADD KEY `fk_hist_contrasena_usuario` (`tbl_ms_usuario_id_usuario`);

--
-- Indices de la tabla `tbl_ms_parametros`
--
ALTER TABLE `tbl_ms_parametros`
  ADD KEY `fk_parametro_usuario` (`tbl_ms_usuario_id_usuario`);

--
-- Indices de la tabla `tbl_ms_roles`
--
ALTER TABLE `tbl_ms_roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `tbl_ms_usuario`
--
ALTER TABLE `tbl_ms_usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `fk_usuario_rol` (`tbl_ms_roles_id_rol`);

--
-- Indices de la tabla `tbl_objetos`
--
ALTER TABLE `tbl_objetos`
  ADD PRIMARY KEY (`id_objetos`);

--
-- Indices de la tabla `tbl_pago`
--
ALTER TABLE `tbl_pago`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `idx_pago_suscripcion` (`id_suscripcion`),
  ADD KEY `idx_pago_fecha` (`fecha_pago`);

--
-- Indices de la tabla `tbl_periodo`
--
ALTER TABLE `tbl_periodo`
  ADD PRIMARY KEY (`id_periodo`);

--
-- Indices de la tabla `tbl_permisos`
--
ALTER TABLE `tbl_permisos`
  ADD PRIMARY KEY (`id_permisos`),
  ADD KEY `fk_permisos_rol` (`tbl_ms_roles_id_rol`),
  ADD KEY `fk_tbl_permisos_objetos` (`tbl_objetos_id_objeto`);

--
-- Indices de la tabla `tbl_plan`
--
ALTER TABLE `tbl_plan`
  ADD PRIMARY KEY (`id_plan`),
  ADD KEY `id_periodo` (`id_periodo`);

--
-- Indices de la tabla `tbl_reset_tokens`
--
ALTER TABLE `tbl_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expiracion` (`expiracion`);

--
-- Indices de la tabla `tbl_suscripcion`
--
ALTER TABLE `tbl_suscripcion`
  ADD PRIMARY KEY (`id_suscripcion`),
  ADD KEY `id_estadoSuscripcion` (`id_estadoSuscripcion`),
  ADD KEY `idx_suscripcion_cliente` (`id_cliente`),
  ADD KEY `idx_suscripcion_plan` (`id_plan`),
  ADD KEY `idx_suscripcion_fecha_fin` (`fecha_fin`);

--
-- Indices de la tabla `tbl_tipo_notificacion`
--
ALTER TABLE `tbl_tipo_notificacion`
  ADD PRIMARY KEY (`id_tipoNotificacion`),
  ADD KEY `id_configuracion` (`id_configuracion`),
  ADD KEY `idx_tipo_notificacion_plan` (`id_plan`);

--
-- Indices de la tabla `tbl_verificacion_codigos`
--
ALTER TABLE `tbl_verificacion_codigos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_expiracion` (`expiracion`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `tbl_calendario`
--
ALTER TABLE `tbl_calendario`
  MODIFY `id_calendario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_cliente`
--
ALTER TABLE `tbl_cliente`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tbl_configuracion_notificaciones`
--
ALTER TABLE `tbl_configuracion_notificaciones`
  MODIFY `id_configuracion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_estado_calendario`
--
ALTER TABLE `tbl_estado_calendario`
  MODIFY `id_estadoCalendario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tbl_estado_cliente`
--
ALTER TABLE `tbl_estado_cliente`
  MODIFY `id_estadoCliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tbl_estado_suscripcion`
--
ALTER TABLE `tbl_estado_suscripcion`
  MODIFY `id_estadoSuscripcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_bitacora`
--
ALTER TABLE `tbl_ms_bitacora`
  MODIFY `id_bitacora` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_hist_contraseña`
--
ALTER TABLE `tbl_ms_hist_contraseña`
  MODIFY `id_hist` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_roles`
--
ALTER TABLE `tbl_ms_roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_usuario`
--
ALTER TABLE `tbl_ms_usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `tbl_objetos`
--
ALTER TABLE `tbl_objetos`
  MODIFY `id_objetos` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `tbl_pago`
--
ALTER TABLE `tbl_pago`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_periodo`
--
ALTER TABLE `tbl_periodo`
  MODIFY `id_periodo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tbl_permisos`
--
ALTER TABLE `tbl_permisos`
  MODIFY `id_permisos` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_plan`
--
ALTER TABLE `tbl_plan`
  MODIFY `id_plan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_reset_tokens`
--
ALTER TABLE `tbl_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tbl_suscripcion`
--
ALTER TABLE `tbl_suscripcion`
  MODIFY `id_suscripcion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_tipo_notificacion`
--
ALTER TABLE `tbl_tipo_notificacion`
  MODIFY `id_tipoNotificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_verificacion_codigos`
--
ALTER TABLE `tbl_verificacion_codigos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `tbl_calendario`
--
ALTER TABLE `tbl_calendario`
  ADD CONSTRAINT `tbl_calendario_ibfk_1` FOREIGN KEY (`id_suscripcion`) REFERENCES `tbl_suscripcion` (`id_suscripcion`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_calendario_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `tbl_cliente` (`id_cliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_calendario_ibfk_3` FOREIGN KEY (`id_tipoNotificacion`) REFERENCES `tbl_tipo_notificacion` (`id_tipoNotificacion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_calendario_ibfk_4` FOREIGN KEY (`id_estadoCalendario`) REFERENCES `tbl_estado_calendario` (`id_estadoCalendario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_cliente`
--
ALTER TABLE `tbl_cliente`
  ADD CONSTRAINT `tbl_cliente_ibfk_1` FOREIGN KEY (`id_estadoCliente`) REFERENCES `tbl_estado_cliente` (`id_estadoCliente`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_ms_bitacora`
--
ALTER TABLE `tbl_ms_bitacora`
  ADD CONSTRAINT `fk_bitacora_objetos` FOREIGN KEY (`id_objetos`) REFERENCES `tbl_objetos` (`id_objetos`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_ms_usuario` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_ms_hist_contraseña`
--
ALTER TABLE `tbl_ms_hist_contraseña`
  ADD CONSTRAINT `fk_hist_contrasena_usuario` FOREIGN KEY (`tbl_ms_usuario_id_usuario`) REFERENCES `tbl_ms_usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_ms_parametros`
--
ALTER TABLE `tbl_ms_parametros`
  ADD CONSTRAINT `fk_parametro_usuario` FOREIGN KEY (`tbl_ms_usuario_id_usuario`) REFERENCES `tbl_ms_usuario` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_ms_usuario`
--
ALTER TABLE `tbl_ms_usuario`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`tbl_ms_roles_id_rol`) REFERENCES `tbl_ms_roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_pago`
--
ALTER TABLE `tbl_pago`
  ADD CONSTRAINT `tbl_pago_ibfk_1` FOREIGN KEY (`id_suscripcion`) REFERENCES `tbl_suscripcion` (`id_suscripcion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_permisos`
--
ALTER TABLE `tbl_permisos`
  ADD CONSTRAINT `fk_permisos_rol` FOREIGN KEY (`tbl_ms_roles_id_rol`) REFERENCES `tbl_ms_roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tbl_permisos_objetos` FOREIGN KEY (`tbl_objetos_id_objeto`) REFERENCES `tbl_objetos` (`id_objetos`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_plan`
--
ALTER TABLE `tbl_plan`
  ADD CONSTRAINT `tbl_plan_ibfk_1` FOREIGN KEY (`id_periodo`) REFERENCES `tbl_periodo` (`id_periodo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_suscripcion`
--
ALTER TABLE `tbl_suscripcion`
  ADD CONSTRAINT `tbl_suscripcion_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `tbl_cliente` (`id_cliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_suscripcion_ibfk_2` FOREIGN KEY (`id_plan`) REFERENCES `tbl_plan` (`id_plan`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_suscripcion_ibfk_3` FOREIGN KEY (`id_estadoSuscripcion`) REFERENCES `tbl_estado_suscripcion` (`id_estadoSuscripcion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_tipo_notificacion`
--
ALTER TABLE `tbl_tipo_notificacion`
  ADD CONSTRAINT `tbl_tipo_notificacion_ibfk_1` FOREIGN KEY (`id_plan`) REFERENCES `tbl_plan` (`id_plan`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_tipo_notificacion_ibfk_2` FOREIGN KEY (`id_configuracion`) REFERENCES `tbl_configuracion_notificaciones` (`id_configuracion`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
