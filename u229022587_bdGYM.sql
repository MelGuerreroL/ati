-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 26-11-2025 a las 16:05:10
-- Versión del servidor: 11.8.3-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u229022587_bdGYM`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`u229022587_usergym`@`127.0.0.1` PROCEDURE `autocalendario_procedure` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;

    DECLARE v_id_suscripcion INT;
    DECLARE v_id_plan INT;
    DECLARE v_fecha_inicio DATE;
    DECLARE v_dias_transcurridos INT;
    DECLARE v_id_cliente INT;

    DECLARE v_id_tipoNotificacion INT;
    DECLARE v_nombre_tipoNotificacion VARCHAR(255);
    DECLARE v_periodicidad INT;
    DECLARE v_valor_parametro INT;

    -- Definir cursor
    DECLARE cur CURSOR FOR 
        SELECT s.id_suscripcion, 
               s.id_plan, 
               s.fecha_inicio,
               s.id_cliente,
               DATEDIFF(CURDATE(), s.fecha_inicio) AS dias_transcurridos
        FROM tbl_suscripcion s
        WHERE s.fecha_inicio IS NOT NULL
        LIMIT 100;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_id_suscripcion, v_id_plan, v_fecha_inicio, v_id_cliente, v_dias_transcurridos;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Buscar tipo de notificación según el plan
        SET v_id_tipoNotificacion = NULL;
        SET v_periodicidad = NULL;
        SET v_nombre_tipoNotificacion = NULL;

        SELECT tn.id_tipoNotificacion, tn.periodicidad, tn.nombre_tipoNotificacion
        INTO v_id_tipoNotificacion, v_periodicidad, v_nombre_tipoNotificacion
        FROM tbl_tipo_notificacion tn
        WHERE tn.id_plan = v_id_plan
        LIMIT 1;

        IF v_id_tipoNotificacion IS NOT NULL THEN
            -- Buscar valor del parámetro según la periodicidad
            SET v_valor_parametro = NULL;

            SELECT p.valor
            INTO v_valor_parametro
            FROM tbl_ms_parametros p
            WHERE p.id_parametro = v_periodicidad
            LIMIT 1;

            -- Insertar solo si se cumple la condición
            IF v_valor_parametro IS NOT NULL AND v_dias_transcurridos = v_valor_parametro THEN
                IF NOT EXISTS (
                    SELECT 1
                    FROM tbl_calendario c
                    WHERE c.id_suscripcion = v_id_suscripcion
                      AND c.meta = 1
                ) THEN
                    INSERT INTO tbl_calendario (
                        id_suscripcion, 
                        id_cliente, 
                        fecha_notificacion, 
                        hora_notificacion, 
                        id_tipoNotificacion, 
                        contenido,
                        meta
                    ) VALUES (
                        v_id_suscripcion, 
                        v_id_cliente, 
                        CURDATE(), 
                        CURTIME(), 
                        v_id_tipoNotificacion, 
                        v_nombre_tipoNotificacion,
                        1
                    );
                END IF;
            END IF;
        END IF;
    END LOOP;

    CLOSE cur;
END$$

CREATE DEFINER=`u229022587_usergym`@`127.0.0.1` PROCEDURE `ejecutar_todo` ()   BEGIN
    CALL autocalendario_procedure();
    CALL generar_calendario_vencimientos();
END$$

CREATE DEFINER=`u229022587_usergym`@`127.0.0.1` PROCEDURE `generar_calendario_vencimientos` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;

    DECLARE v_id_suscripcion INT;
    DECLARE v_id_cliente INT;
    DECLARE v_id_plan INT;
    DECLARE v_fecha_inicio DATE;
    DECLARE v_fecha_fin DATE;
    DECLARE v_precio DECIMAL(10,2);
    DECLARE v_id_estadoSuscripcion INT;

    -- Cursor para recorrer suscripciones con estado 2 o 3
    DECLARE cur CURSOR FOR
        SELECT id_suscripcion
        FROM tbl_suscripcion
        WHERE id_estadoSuscripcion IN (2,3);

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_id_suscripcion;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Obtener detalle de la suscripción
        SELECT id_suscripcion, id_cliente, id_plan, fecha_inicio, fecha_fin, precio, id_estadoSuscripcion
        INTO v_id_suscripcion, v_id_cliente, v_id_plan, v_fecha_inicio, v_fecha_fin, v_precio, v_id_estadoSuscripcion
        FROM tbl_suscripcion
        WHERE id_suscripcion = v_id_suscripcion
        LIMIT 1;

        -- Verificar si ya existe un registro en calendario con meta=1
        IF NOT EXISTS (
            SELECT 1
            FROM tbl_calendario
            WHERE id_suscripcion = v_id_suscripcion
              AND meta = 1
        ) THEN
            -- Insertar notificación de vencimiento
            INSERT INTO tbl_calendario (
                id_suscripcion,
                id_cliente,
                fecha_notificacion,
                hora_notificacion,
                id_tipoNotificacion,
                contenido,
                meta
            ) VALUES (
                v_id_suscripcion,
                v_id_cliente,
                CURDATE(),
                CURTIME(),
                1,                -- id_tipoNotificacion fijo en 1
                'VENCIMIENTO',    -- contenido fijo
                1                 -- meta fijo en 1
            );
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

--
-- Volcado de datos para la tabla `tbl_calendario`
--

INSERT INTO `tbl_calendario` (`id_calendario`, `id_suscripcion`, `id_cliente`, `id_tipoNotificacion`, `id_estadoCalendario`, `fecha_notificacion`, `hora_notificacion`, `fecha_envio`, `intento_count`, `contenido`, `meta`, `creado_en`) VALUES
(5, 8, 4, 1, 2, '2025-11-25', '17:41:30', '2025-11-25 17:46:52', 1, 'VENCIMIENTO', '1', '2025-11-25 23:41:30'),
(19, 10, 7, 5, 2, '2025-11-26', '13:15:12', '2025-11-26 07:15:12', 1, 'AVISO MENSUAL', '1', '2025-11-26 13:15:12'),
(20, 11, 8, 1, 2, '2025-11-26', '13:19:17', '2025-11-26 07:19:17', 1, 'VENCIMIENTO', '1', '2025-11-26 13:19:17');

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
(2, 'Jose Carlos Ramos P', '', 'josec@gmail.com', '2003-01-05', '2025-11-04 02:06:05', 2, ''),
(3, 'DANIELA GARCIA', '9999-6789', 'marielasg1220@gmail.com', '1999-06-15', '2025-11-23 22:54:40', 1, NULL),
(4, 'JUAN LOBO', NULL, 'juan@gmail.com', '2000-12-12', '2025-11-25 21:10:40', 1, NULL),
(7, 'ROBERTO FLORES', NULL, 'forgertw01@gmail.com', '2001-06-12', '2025-11-26 12:38:24', 1, NULL),
(8, 'RUBEN GUERRERO', NULL, 'auditoressistematicos@gmail.com', '1999-11-10', '2025-11-26 13:17:45', 1, NULL);

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

--
-- Volcado de datos para la tabla `tbl_configuracion_notificaciones`
--

INSERT INTO `tbl_configuracion_notificaciones` (`id_configuracion`, `nombre_configuracion`, `parametros`, `activo`, `creado_en`) VALUES
(1, 'VENCIMIENTO', '{\"mensaje\": \"Te informamos que tu suscripción actual en GYM CLUB ha vencido.\\n\\nPara seguir disfrutando de nuestros servicios, te invitamos a renovar tu suscripción y continuar tu camino hacia una vida más activa y saludable.\\n\\nSi ya realizaste la renovación, por favor ignora este mensaje.\\n\\n¡Gracias por ser parte de nuestra comunidad!\\n\\nAtentamente, Equipo GYM CLUB.\"}', 1, '2025-11-23 17:15:38'),
(2, 'ALERTA', '{\"mensaje\": \"Queremos informarte que tu suscripción en GYM CLUB está próxima a vencer.\\n\\n¡Aún estás a tiempo de renovarla y seguir disfrutando de nuestros entrenamientos y beneficios!\\n\\nNo dejes que tu progreso se detenga. Renueva hoy y continúa tu camino hacia una vida más activa y saludable.\\n\\nSi ya realizaste la renovación, por favor ignora este mensaje.\\n\\nAtentamente, Equipo GYM CLUB.\"}', 1, '2025-11-23 17:16:44');

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
(3, 'CANCELADA', 'Suscripción cancelada', '2025-10-18 01:44:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_bitacora`
--

CREATE TABLE `tbl_ms_bitacora` (
  `id_bitacora` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) NOT NULL,
  `id_objetos` int(11) NOT NULL,
  `accion` varchar(20) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_bitacora`
--

INSERT INTO `tbl_ms_bitacora` (`id_bitacora`, `fecha`, `id_usuario`, `id_objetos`, `accion`, `descripcion`) VALUES
(64, '2025-10-28 06:00:00', 20, 1, 'INSERT', 'Se registró un nuevo usuario: castilloemer26 (ID: 20).'),
(65, '2025-10-28 06:00:00', 20, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: castilloemer26'),
(66, '2025-10-28 06:00:00', 20, 2, 'LOGOUT', 'El usuario ID: 20 cerró sesión.'),
(105, '2025-11-23 21:38:02', 21, 1, 'INSERT', 'Se registró un nuevo usuario: marisanzx3 (ID: 21).'),
(106, '2025-11-23 21:38:10', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(107, '2025-11-23 21:39:20', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(108, '2025-11-23 21:39:31', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(109, '2025-11-23 21:39:35', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(110, '2025-11-23 21:40:24', 21, 5, 'CREAR', 'Creó un Parámetro'),
(111, '2025-11-23 21:42:30', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(112, '2025-11-23 21:54:15', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(113, '2025-11-23 21:54:44', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(114, '2025-11-23 21:54:54', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(115, '2025-11-23 21:54:59', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(116, '2025-11-23 21:55:40', 21, 5, 'EDITO', 'Actualizó el Valor de un Parámetro'),
(117, '2025-11-23 21:57:35', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(118, '2025-11-23 21:57:57', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(119, '2025-11-23 21:58:09', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(120, '2025-11-23 21:58:15', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(121, '2025-11-23 22:02:15', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(122, '2025-11-23 22:02:20', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(123, '2025-11-23 22:02:23', 21, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(124, '2025-11-23 22:02:27', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(125, '2025-11-23 22:22:44', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(126, '2025-11-23 22:24:39', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(127, '2025-11-23 22:25:24', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(128, '2025-11-23 22:25:38', 21, 5, 'CREAR', 'Creó un Parámetro'),
(129, '2025-11-23 22:25:53', 21, 5, 'CREAR', 'Creó un Parámetro'),
(130, '2025-11-23 22:37:07', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(131, '2025-11-23 22:37:10', 21, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(132, '2025-11-23 22:37:32', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(133, '2025-11-23 22:51:49', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(134, '2025-11-23 22:52:35', 21, 12, 'INSERT', 'Se creó el período \'BIMESTRAL\' con 60 días (ID: 7)'),
(135, '2025-11-23 22:53:47', 21, 6, 'INSERT', 'Se creó la suscripción ID 6 para cliente ID 2, plan ID 5, inicio 2025-11-08, fin 2026-05-07, precio '),
(136, '2025-11-23 22:54:40', 21, 11, 'INSERT', 'Registró nuevo cliente ID: 3 (Nombre: DANIELA GARCIA, Correo: marielasg1220@gmail.com)'),
(137, '2025-11-23 22:55:06', 21, 6, 'INSERT', 'Se creó la suscripción ID 7 para cliente ID 3, plan ID 3, inicio 2025-11-22, fin 2025-12-07, precio '),
(138, '2025-11-24 00:02:00', 21, 2, 'LOGIN_FAIL', 'Intento de login fallido para usuario: marisanzx3. Intento 1 de 3.'),
(139, '2025-11-24 00:02:25', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(140, '2025-11-24 00:02:29', 21, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(141, '2025-11-24 00:03:20', 21, 10, 'EMAIL', 'Comprobante enviado por correo - Pago #5 - Cliente: DANIELA GARCIA'),
(142, '2025-11-24 00:03:20', 21, 10, 'INSERT', 'Nuevo pago registrado #5 - Suscripción: 7, Monto: Lps 500.00, Método: Efectivo, Referencia: 7000'),
(143, '2025-11-24 00:04:57', 21, 6, 'UPDATE', 'Se editó la suscripción ID 6 (cliente ID 2). Cambios: plan ID: 5 → 4, fecha inicio: \'2025-11-08\' → \''),
(144, '2025-11-24 00:10:30', 21, 10, 'DELETE', 'Pago eliminado #5 - Suscripción: 7, Monto: Lps 500.00, Método: Efectivo, Referencia: 7000'),
(145, '2025-11-24 00:11:00', 21, 6, 'UPDATE', 'Se editó la suscripción ID 6 (cliente ID 2). Cambios: fecha inicio: \'2025-10-23\' → \'2025-10-30\', fec'),
(146, '2025-11-24 00:11:46', 21, 6, 'UPDATE', 'Se editó la suscripción ID 7 (cliente ID 3). Cambios: plan ID: 3 → 4, fecha inicio: \'2025-11-23\' → \''),
(147, '2025-11-24 00:18:17', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(148, '2025-11-24 00:18:22', 21, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(149, '2025-11-24 00:18:29', 21, 5, 'CREAR', 'Creó un Parámetro'),
(150, '2025-11-24 00:18:48', 21, 5, 'CREAR', 'Creó un Parámetro'),
(151, '2025-11-24 00:19:16', 21, 5, 'EDITO', 'Actualizó el Valor de un Parámetro'),
(152, '2025-11-24 17:58:10', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(153, '2025-11-24 17:58:31', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(154, '2025-11-24 17:59:23', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(155, '2025-11-24 18:10:13', 21, 2, 'LOGIN_FAIL', 'Intento de login fallido para usuario: marisanzx3. Intento 1 de 3.'),
(156, '2025-11-24 18:10:50', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(157, '2025-11-24 06:00:00', 21, 9, 'BackupBD', 'Realizó un Backup'),
(158, '2025-11-24 18:11:49', 21, 12, 'RESTORE_SUCCESS', 'BD restaurada exitosamente. Archivo: Backup_gestion_suscripciones_2025-11-24_12-11-17.sql. Sentencia'),
(159, '2025-11-24 23:10:51', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(160, '2025-11-24 23:11:22', 21, 6, 'UPDATE', 'Se editó la suscripción ID 7 (cliente ID 3). Cambios: fecha inicio: \'2025-10-26\' → \'2025-10-24\', fec'),
(161, '2025-11-24 23:12:02', 21, 6, 'UPDATE', 'Se editó la suscripción ID 7 (cliente ID 3). Cambios: fecha inicio: \'2025-10-24\' → \'2025-10-02\', fec'),
(162, '2025-11-25 06:06:38', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(163, '2025-11-25 06:00:00', 21, 9, 'BackupBD', 'Realizó un Backup'),
(164, '2025-11-25 06:00:00', 21, 9, 'BackupBD', 'Realizó un Backup'),
(165, '2025-11-25 06:15:39', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(166, '2025-11-25 06:18:19', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(167, '2025-11-25 06:18:30', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(168, '2025-11-25 06:18:38', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(169, '2025-11-25 06:19:03', 21, 2, 'LOGIN_FAIL', 'Intento de login fallido para usuario: marisanzx3. Intento 1 de 3.'),
(170, '2025-11-25 06:19:20', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(171, '2025-11-25 06:20:04', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(172, '2025-11-25 06:20:09', 21, 2, 'LOGIN_FAIL', 'Intento de login fallido para usuario: marisanzx3. Intento 1 de 3.'),
(173, '2025-11-25 06:20:20', 21, 2, 'LOGIN_FAIL', 'Intento de login fallido para usuario: marisanzx3. Intento 2 de 3.'),
(174, '2025-11-25 06:20:26', 21, 2, 'USER_LOCKED', 'Usuario marisanzx3 bloqueado por 3 intentos fallidos.'),
(175, '2025-11-25 06:21:27', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(176, '2025-11-25 06:00:00', 21, 9, 'BackupBD', 'Realizó un Backup'),
(182, '2025-11-25 20:59:08', 22, 1, 'INSERT', 'Se registró un nuevo usuario: cerratooscar15 (ID: 22).'),
(183, '2025-11-25 20:59:16', 22, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: cerratooscar15'),
(185, '2025-11-25 21:00:00', 20, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: castilloemer26'),
(186, '2025-11-25 21:00:18', 23, 1, 'INSERT', 'Se registró un nuevo usuario: gfigueroav (ID: 23).'),
(187, '2025-11-25 21:00:30', 22, 2, 'LOGOUT', 'El usuario ID: 22 cerró sesión.'),
(188, '2025-11-25 21:00:44', 23, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gfigueroav'),
(189, '2025-11-25 21:01:30', 22, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: cerratooscar15'),
(190, '2025-11-25 21:02:44', 23, 2, 'LOGOUT', 'El usuario ID: 23 cerró sesión.'),
(191, '2025-11-25 21:04:15', 20, 1, 'REPORT', 'Generó el reporte PDF de usuarios registrados'),
(192, '2025-11-25 21:04:16', 20, 1, 'REPORT', 'Generó el reporte PDF de usuarios registrados'),
(193, '2025-11-25 21:04:20', 20, 1, 'REPORT', 'Generó el reporte PDF de usuarios registrados'),
(194, '2025-11-25 21:06:06', 20, 1, 'UPDATE', 'El usuario ID: 20 actualizó al usuario ID: 23.'),
(195, '2025-11-25 21:07:46', 23, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gfigueroav'),
(196, '2025-11-25 21:08:55', 20, 1, 'REPORT', 'Generó el reporte PDF de usuarios registrados (filtro: ga)'),
(197, '2025-11-25 21:08:56', 20, 1, 'REPORT', 'Generó el reporte PDF de usuarios registrados (filtro: ga)'),
(198, '2025-11-25 21:10:40', 20, 11, 'INSERT', 'Registró nuevo cliente ID: 4 (Nombre: JUAN LOBO, Correo: juan@gmail.com)'),
(199, '2025-11-25 21:11:03', 20, 6, 'INSERT', 'Se creó la suscripción ID 8 para cliente ID 4, plan ID 4, inicio 2025-10-10, fin 2025-11-09, precio '),
(200, '2025-11-25 21:11:55', 20, 10, 'EMAIL', 'Comprobante enviado por correo - Pago #6 - Cliente: DANIELA GARCIA'),
(201, '2025-11-25 21:11:55', 20, 10, 'INSERT', 'Nuevo pago registrado #6 - Suscripción: 7, Monto: Lps 700.00, Método: Efectivo, Referencia: 7000'),
(202, '2025-11-25 21:12:01', 20, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(203, '2025-11-25 00:00:00', 20, 9, 'BackupBD', 'Realizó un Backup'),
(204, '2025-11-25 21:29:24', 23, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(205, '2025-11-25 21:29:27', 23, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(206, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(207, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(208, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(209, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(210, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(211, '2025-11-25 21:55:05', 23, 10, 'REPORT', 'Usuario generó reporte general de pagos en PDF'),
(212, '2025-11-25 21:55:16', 23, 3, 'REPORT', 'Generó el reporte PDF de clientes registrados'),
(213, '2025-11-25 21:55:23', 23, 1, 'REPORT', 'Generó el reporte PDF de usuarios registrados'),
(214, '2025-11-25 21:55:32', 23, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(215, '2025-11-25 21:55:34', 23, 2, 'PDF', 'Descargó el PDF de la Bitácora'),
(216, '2025-11-25 21:55:43', 23, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(217, '2025-11-25 21:55:47', 23, 5, 'PDF', 'Descargó el PDF de Parámetros'),
(218, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(219, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(220, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(221, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(222, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(223, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(224, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(226, '2025-11-25 23:18:33', 23, 1, 'INSERT', 'El admin ID: 23 registró al usuario ID: 24 (gfigueroa).'),
(229, '2025-11-25 23:34:03', 26, 1, 'INSERT', 'Se registró un nuevo usuario: gfigueroa123 (ID: 26).'),
(230, '2025-11-25 23:34:03', 23, 1, 'INSERT', 'El admin ID: 23 registró al usuario ID: 26 (gfigueroa123).'),
(231, '2025-11-25 23:40:50', 23, 11, 'INSERT', 'Registró nuevo cliente ID: 5 (Nombre: DIEGO, Correo: diego@example.com)'),
(232, '2025-11-25 23:41:12', 23, 6, 'INSERT', 'Se creó la suscripción ID 9 para cliente ID 5, plan ID 4, inicio 2025-10-10, fin 2025-11-09, precio '),
(233, '2025-11-25 23:42:35', 23, 10, 'EMAIL', 'Comprobante enviado por correo - Pago #7 - Cliente: DIEGO'),
(234, '2025-11-25 23:42:35', 23, 10, 'INSERT', 'Nuevo pago registrado #7 - Suscripción: 9, Monto: Lps 700.00, Método: Tarjeta de Débito, Referencia:'),
(235, '2025-11-25 23:42:47', 23, 10, 'REPORT', 'Usuario generó reporte de pagos filtrado por: \'dieg\''),
(236, '2025-11-25 23:42:59', 23, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(237, '2025-11-25 23:43:04', 23, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(238, '2025-11-25 23:44:55', 23, 12, 'INSERT', 'Se creó el período \'BONO\' con 60 días (ID: 8)'),
(239, '2025-11-25 00:00:00', 23, 9, 'BackupBD', 'Realizó un Backup'),
(240, '2025-11-26 01:56:36', 20, 2, 'LOGOUT', 'El usuario ID: 20 cerró sesión.'),
(242, '2025-11-26 01:57:02', 20, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: castilloemer26'),
(244, '2025-11-26 01:57:51', 27, 1, 'INSERT', 'Se registró un nuevo usuario: gabrielafigueroa006 (ID: 27).'),
(245, '2025-11-26 01:58:05', 27, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gabrielafiguero'),
(246, '2025-11-26 01:58:22', 27, 2, 'LOGOUT', 'El usuario ID: 27 cerró sesión.'),
(247, '2025-11-26 01:58:58', 20, 1, 'DELETE', 'El usuario ID: 20 eliminó al usuario ID: 13.'),
(248, '2025-11-26 01:59:49', 28, 1, 'INSERT', 'Se registró un nuevo usuario: castilloemer2002 (ID: 28).'),
(249, '2025-11-26 01:59:49', 20, 1, 'INSERT', 'El admin ID: 20 registró al usuario ID: 28 (castilloemer2002).'),
(250, '2025-11-26 02:00:12', 29, 1, 'INSERT', 'Se registró un nuevo usuario: mvguerrero150 (ID: 29).'),
(251, '2025-11-26 02:00:35', 29, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mvguerrero150'),
(252, '2025-11-26 02:00:37', 20, 2, 'LOGOUT', 'El usuario ID: 20 cerró sesión.'),
(253, '2025-11-26 02:00:43', 28, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: castilloemer200'),
(254, '2025-11-26 02:01:18', 27, 2, 'LOGOUT', 'El usuario ID: 27 cerró sesión.'),
(255, '2025-11-26 02:01:29', 27, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gabrielafiguero'),
(256, '2025-11-26 02:02:02', 28, 2, 'LOGOUT', 'El usuario ID: 28 cerró sesión.'),
(257, '2025-11-26 02:02:08', 29, 2, 'LOGOUT', 'El usuario ID: 29 cerró sesión.'),
(258, '2025-11-26 02:02:14', 20, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: castilloemer26'),
(259, '2025-11-26 02:02:25', 29, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mvguerrero150'),
(260, '2025-11-26 02:02:35', 27, 2, 'LOGOUT', 'El usuario ID: 27 cerró sesión.'),
(261, '2025-11-26 02:02:44', 20, 1, 'UPDATE', 'El usuario ID: 20 actualizó al usuario ID: 28.'),
(262, '2025-11-26 02:02:47', 27, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gabrielafiguero'),
(263, '2025-11-26 02:02:54', 20, 2, 'LOGOUT', 'El usuario ID: 20 cerró sesión.'),
(264, '2025-11-26 02:03:05', 28, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: castilloemer200'),
(265, '2025-11-26 02:13:02', 27, 2, 'LOGOUT', 'El usuario ID: 27 cerró sesión.'),
(266, '2025-11-26 02:15:23', 30, 1, 'INSERT', 'Se registró un nuevo usuario: gymclub882 (ID: 30).'),
(267, '2025-11-26 02:15:36', 30, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gymclub882'),
(268, '2025-11-26 02:16:13', 30, 2, 'LOGOUT', 'El usuario ID: 30 cerró sesión.'),
(269, '2025-11-26 02:16:42', 30, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gymclub882'),
(270, '2025-11-26 02:19:42', 31, 1, 'INSERT', 'Se registró un nuevo usuario: diegoR (ID: 31).'),
(271, '2025-11-26 02:19:42', 30, 1, 'INSERT', 'El admin ID: 30 registró al usuario ID: 31 (diegoR).'),
(272, '2025-11-26 02:23:47', 32, 1, 'INSERT', 'Se registró un nuevo usuario: glerer (ID: 32).'),
(273, '2025-11-26 02:23:47', 30, 1, 'INSERT', 'El admin ID: 30 registró al usuario ID: 32 (glerer).'),
(274, '2025-11-26 02:55:41', 29, 2, 'LOGOUT', 'El usuario ID: 29 cerró sesión.'),
(275, '2025-11-26 04:14:07', 30, 2, 'LOGOUT', 'El usuario ID: 30 cerró sesión.'),
(276, '2025-11-26 04:14:52', 27, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gabrielafiguero'),
(277, '2025-11-26 04:16:12', 27, 2, 'LOGOUT', 'El usuario ID: 27 cerró sesión.'),
(278, '2025-11-26 04:16:27', 27, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gabrielafiguero'),
(279, '2025-11-26 04:20:53', 27, 11, 'INSERT', 'Registró nuevo cliente ID: 6 (Nombre: MANUEL, Correo: manuel@example.com)'),
(280, '2025-11-26 05:03:01', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(281, '2025-11-26 05:04:16', 21, 2, 'LOGOUT', 'El usuario ID: 21 cerró sesión.'),
(282, '2025-11-26 05:05:55', 33, 1, 'INSERT', 'Se registró un nuevo usuario: mariela.gg1204 (ID: 33).'),
(283, '2025-11-26 05:09:05', 21, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: marisanzx3'),
(284, '2025-11-26 05:09:20', 21, 1, 'UPDATE', 'El usuario ID: 21 actualizó al usuario ID: 33.'),
(285, '2025-11-26 05:10:47', 33, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mariela.gg1204'),
(286, '2025-11-26 05:11:33', 33, 1, 'REPORT', 'Generó el reporte PDF de usuarios registrados'),
(287, '2025-11-26 05:21:30', 34, 1, 'INSERT', 'Se registró un nuevo usuario: yakelinsosa24 (ID: 34).'),
(288, '2025-11-26 05:21:40', 34, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: yakelinsosa24'),
(289, '2025-11-26 05:23:25', 34, 3, 'REPORT', 'Generó el reporte PDF de clientes registrados'),
(290, '2025-11-26 05:24:06', 34, 2, 'LOGOUT', 'El usuario ID: 34 cerró sesión.'),
(291, '2025-11-26 11:03:57', 29, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mvguerrero150'),
(292, '2025-11-26 11:04:23', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(293, '2025-11-26 11:04:43', 29, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(294, '2025-11-26 11:06:23', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(295, '2025-11-26 12:14:27', 29, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mvguerrero150'),
(296, '2025-11-26 12:16:31', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(297, '2025-11-26 12:16:57', 29, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(298, '2025-11-26 12:18:05', 29, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(299, '2025-11-26 12:18:11', 29, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(300, '2025-11-26 12:18:19', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(301, '2025-11-26 12:18:33', 29, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(302, '2025-11-26 12:19:20', 29, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(303, '2025-11-26 12:28:30', 29, 2, 'LOGOUT', 'El usuario ID: 29 cerró sesión.'),
(304, '2025-11-26 12:28:46', 29, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mvguerrero150'),
(305, '2025-11-26 12:28:54', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(306, '2025-11-26 12:34:07', 29, 2, 'ACCESO', 'Ingresó a la pantalla de Bitácora'),
(307, '2025-11-26 12:34:15', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(308, '2025-11-26 12:38:24', 29, 11, 'INSERT', 'Registró nuevo cliente ID: 7 (Nombre: ROBERTO FLORES, Correo: forgertw01@gmail.com)'),
(309, '2025-11-26 12:40:31', 29, 6, 'INSERT', 'Se creó la suscripción ID 10 para cliente ID 7, plan ID 4, inicio 2025-11-03, fin 2025-12-03, precio'),
(310, '2025-11-26 12:56:50', 27, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gabrielafiguero'),
(311, '2025-11-26 13:17:45', 29, 11, 'INSERT', 'Registró nuevo cliente ID: 8 (Nombre: RUBEN GUERRERO, Correo: auditoressistematicos@gmail.com)'),
(312, '2025-11-26 13:18:17', 29, 6, 'INSERT', 'Se creó la suscripción ID 11 para cliente ID 8, plan ID 2, inicio 2025-11-26, fin 2025-12-03, precio'),
(313, '2025-11-26 13:18:58', 29, 6, 'UPDATE', 'Se editó la suscripción ID 11 (cliente ID 8). Cambios: fecha inicio: \'2025-11-26\' → \'2025-10-15\', fe'),
(314, '2025-11-26 13:25:02', 29, 2, 'LOGOUT', 'El usuario ID: 29 cerró sesión.'),
(315, '2025-11-26 14:24:18', 29, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mvguerrero150'),
(316, '2025-11-26 14:24:29', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(318, '2025-11-26 14:55:32', 29, 1, 'INSERT', 'El admin ID: 29 registró al usuario ID: 35 (Luiscas).'),
(319, '2025-11-26 14:55:47', 29, 1, 'DELETE', 'El usuario ID: 29 eliminó al usuario ID: 35.'),
(320, '2025-11-26 15:23:52', 29, 5, 'ACCESO', 'Ingresó a la pantalla de Parámetros'),
(322, '2025-11-26 15:25:34', 29, 1, 'INSERT', 'El admin ID: 29 registró al usuario ID: 36 (Luist).'),
(323, '2025-11-26 15:43:18', 29, 1, 'DELETE', 'El usuario ID: 29 eliminó al usuario ID: 36.'),
(325, '2025-11-26 15:44:38', 29, 1, 'INSERT', 'El admin ID: 29 registró al usuario ID: 37 (Luist).'),
(326, '2025-11-26 15:45:23', 29, 1, 'DELETE', 'El usuario ID: 29 eliminó al usuario ID: 37.'),
(328, '2025-11-26 15:46:38', 29, 1, 'INSERT', 'El admin ID: 29 registró al usuario ID: 38 (Luist).'),
(329, '2025-11-26 15:48:20', 29, 1, 'UPDATE', 'El usuario ID: 29 actualizó al usuario ID: 27.'),
(330, '2025-11-26 15:49:16', 27, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: gabrielafiguero'),
(331, '2025-11-26 15:49:17', 29, 1, 'DELETE', 'El usuario ID: 29 eliminó al usuario ID: 38.'),
(332, '2025-11-26 15:49:29', 29, 2, 'LOGOUT', 'El usuario ID: 29 cerró sesión.'),
(333, '2025-11-26 15:50:46', 39, 1, 'INSERT', 'Se registró un nuevo usuario: forgertw01 (ID: 39).'),
(335, '2025-11-26 15:51:00', 27, 1, 'INSERT', 'El admin ID: 27 registró al usuario ID: 40 (DiegoAy).'),
(336, '2025-11-26 15:51:07', 39, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: forgertw01'),
(337, '2025-11-26 15:52:24', 39, 2, 'LOGOUT', 'El usuario ID: 39 cerró sesión.'),
(338, '2025-11-26 15:52:40', 29, 2, 'LOGIN_SUCCESS', 'Inicio de sesión exitoso para el usuario: mvguerrero150'),
(339, '2025-11-26 15:55:02', 27, 1, 'DELETE', 'El usuario ID: 27 eliminó al usuario ID: 40.'),
(340, '2025-11-26 15:55:52', 27, 2, 'LOGOUT', 'El usuario ID: 27 cerró sesión.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_hist_contraseña`
--

CREATE TABLE `tbl_ms_hist_contraseña` (
  `id_hist` int(11) NOT NULL,
  `contraseña` text NOT NULL,
  `tbl_ms_usuario_id_usuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_hist_contraseña`
--

INSERT INTO `tbl_ms_hist_contraseña` (`id_hist`, `contraseña`, `tbl_ms_usuario_id_usuario`) VALUES
(13, 'fd9735347b111d9c6b746839768cd00f301fdd37423a08df5b1fe825a05c57d555f9eec21828f248e29de495e158ee386f8421ae25ab3d86b79d70425fac9bb1', 20),
(14, 'b32e45ce225e490eb6bb75bb29746eec244a6a9dbd61f782db0f8a6cfd2f82eefc090e93c98c28858914bca8b1cd4ae28fb7fd5d12caef88b3906ea6efaab876', 21),
(15, '822b608d6afbd03dfaca27da7b9c258f42fbc9f2e2a6202b7f6c514af73ac6ba68cc37cd27e79878dbcea953ce29dcd396fd7d1a1347b326f38de27d8e857edc', 22),
(16, '60bde3741cd1c581d2777daed875051e25f3d5e143aa7982505588f23725a619001f0b4dd6cfe6f065f4b1a12ce09961233034d42e1180781230fcb8a9b38167', 23),
(19, '60bde3741cd1c581d2777daed875051e25f3d5e143aa7982505588f23725a619001f0b4dd6cfe6f065f4b1a12ce09961233034d42e1180781230fcb8a9b38167', 26),
(20, '60bde3741cd1c581d2777daed875051e25f3d5e143aa7982505588f23725a619001f0b4dd6cfe6f065f4b1a12ce09961233034d42e1180781230fcb8a9b38167', 27),
(21, '13d0f6e7e5b046afb1c6b7b279c3d197a4f1f5cbd909c2e139add0ca69bb46bee25f7c7baf99ee2eb1010242fa5a6156efccfcd51acef0fd2129ed493cf35678', 28),
(22, 'd02e0c038ddbacea5efac7c49acd4a328ca8d5da5095c33a581525090e230cf166ce839eaa928172aa301023e5795911bf8e57b83f7ffbdd63b086ded0c5b0ca', 29),
(23, '8e24f236ee8ab9932bdf4bf89254bd351a23ad6612d31bed224c2dba20ffe2d13b31c5cc5759c8c22fc1a2fe440210b9d5b2f0c438467e32094229b803b3a96f', 30),
(24, '8e24f236ee8ab9932bdf4bf89254bd351a23ad6612d31bed224c2dba20ffe2d13b31c5cc5759c8c22fc1a2fe440210b9d5b2f0c438467e32094229b803b3a96f', 31),
(25, '8e24f236ee8ab9932bdf4bf89254bd351a23ad6612d31bed224c2dba20ffe2d13b31c5cc5759c8c22fc1a2fe440210b9d5b2f0c438467e32094229b803b3a96f', 32),
(26, 'b32e45ce225e490eb6bb75bb29746eec244a6a9dbd61f782db0f8a6cfd2f82eefc090e93c98c28858914bca8b1cd4ae28fb7fd5d12caef88b3906ea6efaab876', 33),
(27, '36026f726740aed03c349c9aca8caa20cf7727da1e6688f6914f28bf32b542df2cc158187b75c6c610a4aee745d0e65b7bb8d467d78fef6ef35828229432282e', 34),
(32, 'd02e0c038ddbacea5efac7c49acd4a328ca8d5da5095c33a581525090e230cf166ce839eaa928172aa301023e5795911bf8e57b83f7ffbdd63b086ded0c5b0ca', 39);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_parametros`
--

CREATE TABLE `tbl_ms_parametros` (
  `id_parametro` int(11) NOT NULL,
  `parametro` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `valor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_creado` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_modificado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_parametros`
--

INSERT INTO `tbl_ms_parametros` (`id_parametro`, `parametro`, `valor`, `id_usuario`, `fecha_creado`, `fecha_modificado`) VALUES
(1, 'MAX_LOGIN_ATTEMPTS', '3', 13, '2025-10-27 00:00:00', '2025-10-27 00:00:00'),
(2, 'MAX_SUSCRIPCIONES_POR_CLIENTE', '1', 13, '2025-11-12 21:33:11', '2025-11-12 21:33:11'),
(3, 'DEFAULT_PAGE_SIZE', '10', 13, '2025-11-12 21:33:11', '2025-11-12 21:33:11'),
(4, 'ENVIAR_RECORDATORIO_DIARIO', '10', 13, '2025-11-14 08:40:18', '2025-11-14 08:40:18'),
(5, 'AVISO SEMANAL', '5', 13, '2025-11-14 08:40:18', '2025-11-14 08:40:18'),
(6, 'AVISO QUINCENAL', '10', 13, '2025-11-14 08:39:21', '2025-11-14 08:39:21'),
(7, 'AVISO_MENSUAL', '23', 13, '2025-11-14 08:39:21', '2025-11-14 08:39:21'),
(8, 'AVISO SEMESTRAL', '170', 13, '2025-11-14 08:39:21', '2025-11-14 08:39:21'),
(9, 'AVISO ANUAL', '300', 13, '2025-11-14 08:39:21', '2025-11-14 08:39:21'),
(12, 'AVISO', '9', 21, '2025-11-23 18:18:29', '2025-11-23 18:18:29'),
(13, 'AVISPOOO', '89', 21, '2025-11-23 18:18:48', '2025-11-23 18:19:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_roles`
--

CREATE TABLE `tbl_ms_roles` (
  `id_rol` int(11) NOT NULL,
  `rol` varchar(30) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_roles`
--

INSERT INTO `tbl_ms_roles` (`id_rol`, `rol`, `descripcion`) VALUES
(1, 'Super Administrador', 'Tiene control total del sistema y acceso a todos los módulos.'),
(2, 'Usuario', 'Rol estándar, solo tiene acceso a la información de suscripciones. ');

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
  `fecha_bloqueo` datetime DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_usuario`
--

INSERT INTO `tbl_ms_usuario` (`id_usuario`, `usuario`, `nombre_usuario`, `estado_usuario`, `contraseña`, `fecha_ultima_conexion`, `primer_ingreso`, `fecha_vencimiento`, `correo_electronico`, `tbl_ms_roles_id_rol`, `totp_secret`, `is_2fa_enabled`, `intentos_fallidos`, `estado_bloqueo`, `fecha_bloqueo`, `foto_perfil`, `fecha_creacion`) VALUES
(20, 'castilloemer26', 'ARIEL CASTILLO CASTILLO', '', 'fd9735347b111d9c6b746839768cd00f301fdd37423a08df5b1fe825a05c57d555f9eec21828f248e29de495e158ee386f8421ae25ab3d86b79d70425fac9bb1', '2025-11-25 21:00:24', 1, '2026-10-28', 'castilloemer26@gmail.com', 1, 'KDOZ2WP2D3OT5XUR', 1, 0, 'ACTIVO', NULL, NULL, '2025-11-21 22:38:19'),
(21, 'marisanzx3', 'MARIELA SANCHEZ', '', 'b32e45ce225e490eb6bb75bb29746eec244a6a9dbd61f782db0f8a6cfd2f82eefc090e93c98c28858914bca8b1cd4ae28fb7fd5d12caef88b3906ea6efaab876', '2025-11-25 06:21:27', 1, '2026-11-23', 'marisanzx3@gmail.com', 1, NULL, 0, 0, 'ACTIVO', NULL, NULL, '2025-11-23 15:37:40'),
(22, 'cerratooscar15', 'OSCAR BLADIMIR CERRATO MARTINEZ', '', '822b608d6afbd03dfaca27da7b9c258f42fbc9f2e2a6202b7f6c514af73ac6ba68cc37cd27e79878dbcea953ce29dcd396fd7d1a1347b326f38de27d8e857edc', '2025-11-26 01:55:14', 1, '2026-11-25', 'cerratooscar15@gmail.com', 1, NULL, 0, 0, 'ACTIVO', NULL, NULL, '2025-11-25 14:58:51'),
(27, 'gabrielafiguero', 'ALEJANDRA VIDELA', '', '60bde3741cd1c581d2777daed875051e25f3d5e143aa7982505588f23725a619001f0b4dd6cfe6f065f4b1a12ce09961233034d42e1180781230fcb8a9b38167', '2025-11-26 15:48:20', 2, '2026-11-25', 'gabrielafigueroa006@gmail.com', 1, 'DCCRA6OP5ZXM2JOK', 1, 0, 'ACTIVO', NULL, NULL, '2025-11-25 19:57:29'),
(28, 'castilloemer200', 'EMERSON ARIEL CASTILLO CASTILLO', 'ACTIVO', '13d0f6e7e5b046afb1c6b7b279c3d197a4f1f5cbd909c2e139add0ca69bb46bee25f7c7baf99ee2eb1010242fa5a6156efccfcd51acef0fd2129ed493cf35678', '2025-11-26 02:03:58', 1, '2026-02-23', 'castilloemer2002@gmail.com', 1, 'TMK2F5QCQA5OEKJ6', 1, 0, 'ACTIVO', NULL, NULL, '2025-11-25 00:00:00'),
(29, 'mvguerrero150', 'MELVIN RUBEN GUERRERO', '', 'd02e0c038ddbacea5efac7c49acd4a328ca8d5da5095c33a581525090e230cf166ce839eaa928172aa301023e5795911bf8e57b83f7ffbdd63b086ded0c5b0ca', '2025-11-26 02:02:43', 1, '2026-11-25', 'mvguerrero150@gmail.com', 1, NULL, 0, 0, 'ACTIVO', NULL, NULL, '2025-11-25 19:59:31'),
(30, 'gymclub882', 'GYM CLUB', '', '8e24f236ee8ab9932bdf4bf89254bd351a23ad6612d31bed224c2dba20ffe2d13b31c5cc5759c8c22fc1a2fe440210b9d5b2f0c438467e32094229b803b3a96f', '2025-11-26 02:16:26', 1, '2026-11-25', 'gymclub882@gmail.com', 1, NULL, 0, 0, 'ACTIVO', NULL, NULL, '2025-11-25 20:14:55'),
(33, 'mariela.gg1204', 'MARIELA SANCHEZ GARCIA', '', 'b32e45ce225e490eb6bb75bb29746eec244a6a9dbd61f782db0f8a6cfd2f82eefc090e93c98c28858914bca8b1cd4ae28fb7fd5d12caef88b3906ea6efaab876', '2025-11-26 05:11:15', 1, '2026-11-25', 'mariela.gg1204@gmail.com', 1, '7OQCSEWUSJWM6ROU', 1, 0, 'ACTIVO', NULL, NULL, '2025-11-25 23:05:32'),
(34, 'yakelinsosa24', 'ISIS NUEZ', '', '36026f726740aed03c349c9aca8caa20cf7727da1e6688f6914f28bf32b542df2cc158187b75c6c610a4aee745d0e65b7bb8d467d78fef6ef35828229432282e', '2025-11-26 05:22:51', 1, '2026-11-25', 'yakelinsosa24@gmail.com', 2, '6LXNTK4SRIQKJL63', 1, 0, 'ACTIVO', NULL, NULL, '2025-11-25 23:21:07'),
(39, 'forgertw01', 'LUIS TORRES', '', 'd02e0c038ddbacea5efac7c49acd4a328ca8d5da5095c33a581525090e230cf166ce839eaa928172aa301023e5795911bf8e57b83f7ffbdd63b086ded0c5b0ca', '2025-11-26 15:52:16', 1, '2026-11-26', 'forgertw01@gmail.com', 2, NULL, 0, 0, 'ACTIVO', NULL, NULL, '2025-11-26 09:50:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_ms_usuario_flags`
--

CREATE TABLE `tbl_ms_usuario_flags` (
  `id_usuario` int(11) NOT NULL,
  `skip_2fa_prompt` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_ms_usuario_flags`
--

INSERT INTO `tbl_ms_usuario_flags` (`id_usuario`, `skip_2fa_prompt`, `updated_at`) VALUES
(20, 0, '2025-11-25 21:00:24'),
(22, 1, '2025-11-25 21:02:42'),
(23, 0, '2025-11-25 21:01:57'),
(27, 0, '2025-11-26 01:58:37'),
(28, 0, '2025-11-26 02:03:58'),
(29, 1, '2025-11-26 02:02:43'),
(30, 1, '2025-11-26 02:16:02'),
(33, 0, '2025-11-26 05:11:15'),
(34, 0, '2025-11-26 05:22:51'),
(39, 1, '2025-11-26 15:52:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_objetos`
--

CREATE TABLE `tbl_objetos` (
  `id_objetos` int(11) NOT NULL,
  `objeto` text NOT NULL,
  `descripcion` text NOT NULL,
  `tipo_objeto` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_objetos`
--

INSERT INTO `tbl_objetos` (`id_objetos`, `objeto`, `descripcion`, `tipo_objeto`) VALUES
(1, 'MANTENIMIENTO_USUARIOS', 'Formulario para crear, editar y eliminar usuarios.', 'Formulario'),
(2, 'DASHBOARD', 'Panel principal con indicadores generales.', 'Módulo'),
(3, 'MANTENIMIENTO_CLIENTES', 'Formulario para gestionar la información de los clientes.', 'Formulario'),
(4, 'GESTION_SUSCRIPCIONES', 'Formulario para crear y administrar suscripciones y pagos.', 'Módulo'),
(5, 'CONFIGURACION_PARAMETROS', 'Formulario para modificar parámetros globales del sistema (contraseñas, intentos de login, etc.).', 'Módulo'),
(6, 'Suscripciones', 'Gestion de suscripciones del sistema', 'Modulo'),
(7, 'NOTIFICACIONES', 'Envia notificaciones a los clientes previamente procesados', 'Módulo'),
(8, 'CALENDARIO', 'Inserta en la tabla calendario a partir de suscripciones', 'Módulo'),
(9, 'Backup', 'Respaldo de la base de datos', 'Seguridad'),
(10, 'OBJETO_PAGOS', 'registro pagos', 'Modulo'),
(11, 'CLIENTES', 'Gestión de clientes del sistema', 'Módulo'),
(12, 'PERIODOS', '', '');

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

--
-- Volcado de datos para la tabla `tbl_pago`
--

INSERT INTO `tbl_pago` (`id_pago`, `id_suscripcion`, `fecha_pago`, `monto`, `metodo_pago`, `referencia_pago`, `creado_en`) VALUES
(6, 7, '2025-11-25 15:11:53', 700.00, 'Efectivo', '7000', '2025-11-25 21:11:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_periodo`
--

CREATE TABLE `tbl_periodo` (
  `id_periodo` int(11) NOT NULL,
  `nombre_periodo` varchar(45) NOT NULL,
  `dias` int(11) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_periodo`
--

INSERT INTO `tbl_periodo` (`id_periodo`, `nombre_periodo`, `dias`, `descripcion`, `precio`, `creado_en`) VALUES
(1, 'DIARIO', 1, 'Periodo de 1 día', 70.00, '2025-10-18 01:44:45'),
(2, 'SEMANAL', 7, 'Periodo de 7 días', 400.00, '2025-10-18 01:44:45'),
(3, 'QUINCENAL', 15, 'Periodo de 15 días', 500.00, '2025-10-18 01:44:45'),
(4, 'MENSUAL', 30, 'Periodo aproximado de 30 días', 700.00, '2025-11-23 17:38:39'),
(5, 'SEMESTRAL', 180, 'Periodo de 180 dias', 3500.00, '2025-11-23 17:38:39'),
(6, 'ANUAL', 365, 'Periodo aproximado de 365 dias.', 7000.00, '2025-11-23 17:39:19'),
(7, 'BIMESTRAL', 60, 'aQDSQ', 1000.00, '2025-11-23 22:52:35'),
(8, 'BONO', 60, 'Periodo de 60 días', 800.00, '2025-11-25 23:44:55');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_permisos`
--

INSERT INTO `tbl_permisos` (`id_permisos`, `permiso_insercion`, `permiso_eliminacion`, `permiso_actualizacion`, `permiso_consultar`, `tbl_ms_roles_id_rol`, `tbl_objetos_id_objeto`) VALUES
(25, 'S', 'S', 'S', 'S', 1, 1),
(26, 'S', 'S', 'S', 'S', 1, 2),
(27, 'S', 'S', 'S', 'S', 1, 3),
(28, 'S', 'S', 'S', 'S', 1, 4),
(29, 'S', 'S', 'S', 'S', 1, 5),
(30, 'S', 'S', 'S', 'S', 1, 6),
(31, 'S', 'S', 'S', 'S', 1, 7),
(32, 'S', 'S', 'S', 'S', 1, 8),
(33, 'S', 'S', 'S', 'S', 1, 9),
(34, 'S', 'S', 'S', 'S', 1, 10),
(35, 'S', 'S', 'S', 'S', 1, 11),
(36, 'N', 'N', 'N', 'S', 2, 2),
(37, 'N', 'N', 'N', 'S', 2, 6),
(38, 'N', 'N', 'N', 'S', 2, 10),
(49, 'N', 'N', 'N', 'S', 2, 9),
(50, 'N', 'N', 'N', 'S', 2, 8);

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

--
-- Volcado de datos para la tabla `tbl_plan`
--

INSERT INTO `tbl_plan` (`id_plan`, `nombre_plan`, `descripcion`, `id_periodo`, `activo`, `creado_en`) VALUES
(1, 'Vencimiento', 'vencimiento', 1, 1, '2025-11-23 18:01:01'),
(2, 'Semanal', NULL, 2, 1, '2025-11-26 13:18:17'),
(3, 'Quincenal', NULL, 3, 1, '2025-11-23 22:55:06'),
(4, 'Mensual', NULL, 4, 1, '2025-11-24 00:04:57'),
(5, 'Semestral', NULL, 5, 1, '2025-11-23 22:53:47'),
(123, 'Mensual', 'mensual', 4, 1, '2025-11-23 18:03:32'),
(131, 'Quincenal', 'Quincenal', 3, 1, '2025-11-23 18:04:54'),
(147, 'Semanal', 'Semanal', 2, 1, '2025-11-23 18:04:54'),
(148, 'Anual', 'Anual', 6, 1, '2025-11-23 18:06:05'),
(149, 'Diario', 'Diario', 1, 1, '2025-11-23 18:06:05'),
(150, 'Semestral', 'Semestral', 5, 1, '2025-11-23 18:06:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_recuperacion_codigos`
--

CREATE TABLE `tbl_recuperacion_codigos` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `codigo` varchar(6) NOT NULL,
  `expiracion` datetime NOT NULL,
  `usado` tinyint(4) DEFAULT 0,
  `intentos` tinyint(4) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Volcado de datos para la tabla `tbl_recuperacion_codigos`
--

INSERT INTO `tbl_recuperacion_codigos` (`id`, `email`, `codigo`, `expiracion`, `usado`, `intentos`, `fecha_creacion`) VALUES
(1, 'castilloemer26@gmail.com', '529411', '2025-10-28 04:37:35', 1, 0, '2025-10-28 03:22:35'),
(2, 'castilloemer26@gmail.com', '436959', '2025-10-28 04:44:16', 1, 0, '2025-10-28 03:29:16'),
(3, 'castilloemer26@gmail.com', '357573', '2025-10-28 05:00:44', 1, 0, '2025-10-28 03:45:44'),
(4, 'castilloemer26@gmail.com', '131955', '2025-10-28 05:20:48', 1, 0, '2025-10-28 04:05:48'),
(5, 'castilloemer26@gmail.com', '297666', '2025-10-28 05:23:35', 1, 0, '2025-10-28 04:08:35'),
(6, 'castilloemer26@gmail.com', '097598', '2025-10-28 05:31:20', 1, 0, '2025-10-28 04:16:20'),
(7, 'castilloemer26@gmail.com', '009127', '2025-10-28 06:00:43', 0, 1, '2025-10-28 04:45:43'),
(8, 'castilloemer26@gmail.com', '111180', '2025-10-28 06:01:18', 1, 0, '2025-10-28 04:46:18'),
(9, 'castilloemer26@gmail.com', '180039', '2025-10-28 06:07:51', 1, 0, '2025-10-28 04:52:51'),
(10, 'castilloemer26@gmail.com', '177220', '2025-10-28 06:22:09', 1, 0, '2025-10-28 05:07:09'),
(11, 'castilloemer2002@gmail.com', '312707', '2025-10-28 06:25:26', 1, 0, '2025-10-28 05:10:26');

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

--
-- Volcado de datos para la tabla `tbl_suscripcion`
--

INSERT INTO `tbl_suscripcion` (`id_suscripcion`, `id_cliente`, `id_plan`, `fecha_inicio`, `fecha_fin`, `precio`, `id_estadoSuscripcion`, `duracion_dias`, `observaciones`, `creado_en`) VALUES
(6, 2, 4, '2025-10-30', '2025-11-29', 700.00, 1, 30, '', '2025-11-23 22:53:47'),
(7, 3, 4, '2025-11-25', '2025-12-25', 700.00, 1, 30, '', '2025-11-23 22:55:06'),
(8, 4, 4, '2025-10-10', '2025-11-09', 700.00, 2, 30, '', '2025-11-25 21:11:03'),
(10, 7, 4, '2025-11-03', '2025-12-03', 700.00, 1, 30, '', '2025-11-26 12:40:31'),
(11, 8, 2, '2025-10-15', '2025-10-22', 400.00, 2, 7, '', '2025-11-26 13:18:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_tipo_notificacion`
--

CREATE TABLE `tbl_tipo_notificacion` (
  `id_tipoNotificacion` int(11) NOT NULL,
  `nombre_tipoNotificacion` varchar(100) NOT NULL,
  `periodicidad` smallint(6) NOT NULL DEFAULT 0,
  `template` text NOT NULL,
  `id_plan` int(11) DEFAULT NULL,
  `id_configuracion` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_tipo_notificacion`
--

INSERT INTO `tbl_tipo_notificacion` (`id_tipoNotificacion`, `nombre_tipoNotificacion`, `periodicidad`, `template`, `id_plan`, `id_configuracion`, `activo`, `creado_en`) VALUES
(1, 'VENCIMIENTO', 0, 'VENCIMIENTO', 1, 1, 1, '2025-11-23 18:09:34'),
(2, 'AVISO DIARIO', 1, 'AVISO DIARIO', 7, 2, 1, '2025-11-23 18:09:34'),
(3, 'AVISO SEMANAL', 5, 'AVISO SEMANAL', 2, 2, 1, '2025-11-23 18:11:48'),
(4, 'AVISO QUINCENAL', 6, 'AVISO QUINCENAL', 3, 2, 1, '2025-11-23 18:11:48'),
(5, 'AVISO MENSUAL', 7, 'AVISO MENSUAL', 4, 2, 1, '2025-11-23 18:13:29'),
(6, 'AVISO SEMESTRAL', 8, 'AVISO SEMESTRAL', 5, 2, 1, '2025-11-23 18:13:29'),
(7, 'AVISO ANUAL', 9, 'AVISO ANUAL', 6, 2, 1, '2025-11-23 18:14:09');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

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
(8, 'castilloemer26@gmail.com', '982062', '2025-10-28 04:08:56', 1, 0, '2025-10-28 02:58:56'),
(9, 'castilloemer26@gmail.com', '449142', '2025-10-28 04:30:57', 1, 0, '2025-10-28 03:20:57'),
(10, 'castilloemer26@gmail.com', '702774', '2025-10-28 05:04:15', 1, 0, '2025-10-28 03:54:15'),
(11, 'castilloemer26@gmail.com', '456289', '2025-10-29 00:11:13', 1, 0, '2025-10-28 23:01:13'),
(12, 'marisanzx3@gmail.com', '524723', '2025-11-23 15:47:38', 1, 0, '2025-11-23 21:37:38'),
(13, 'gabrielafigueroa006@gmail.com', '206973', '2025-11-23 17:52:16', 1, 0, '2025-11-23 23:42:16'),
(14, 'ale.figueroa3118@gmail.com', '557664', '2025-11-24 22:32:24', 0, 0, '2025-11-25 04:22:24'),
(15, 'ale.figueroa3118@gmail.com', '298182', '2025-11-24 22:34:46', 0, 0, '2025-11-25 04:24:46'),
(16, 'ale.figueroa3118@gmail.com', '218631', '2025-11-24 22:35:55', 0, 0, '2025-11-25 04:25:55'),
(17, 'ale.figueroa3118@gmail.com', '788861', '2025-11-24 22:37:31', 0, 0, '2025-11-25 04:27:31'),
(18, 'ale.figueroa3118@gmail.com', '027468', '2025-11-24 22:38:44', 0, 0, '2025-11-25 04:28:44'),
(19, 'ale.figueroa3118@gmail.com', '289653', '2025-11-24 22:39:39', 1, 0, '2025-11-25 04:29:39'),
(20, 'castilloemer2003@gmail.com', '386240', '2025-11-24 23:09:56', 1, 0, '2025-11-25 04:59:56'),
(21, 'yakelinsosa24@gmail.com', '406028', '2025-11-24 23:13:53', 0, 0, '2025-11-25 05:03:53'),
(22, 'mvguerrero150@gmail.com', '099951', '2025-11-24 23:36:34', 1, 0, '2025-11-25 05:26:34'),
(23, 'yakelinsosa24@gmail.com', '791877', '2025-11-24 23:41:23', 0, 0, '2025-11-25 05:31:23'),
(24, 'yakelinsosa24@gmail.com', '770887', '2025-11-24 23:42:08', 1, 0, '2025-11-25 05:32:08'),
(25, 'cerratooscar15@gmail.com', '853578', '2025-11-25 15:08:50', 1, 0, '2025-11-25 20:58:50'),
(26, 'gfigueroav@unah.hn', '160391', '2025-11-25 15:09:23', 1, 0, '2025-11-25 20:59:23'),
(27, 'ale.figueroa3118@gmail.com', '403722', '2025-11-25 17:28:13', 1, 0, '2025-11-25 23:18:13'),
(28, 'gabrielafigueroa006@gmail.com', '832723', '2025-11-25 17:33:48', 1, 0, '2025-11-25 23:23:48'),
(29, 'gabrielafigueroa006@gmail.com', '341236', '2025-11-25 17:43:47', 1, 0, '2025-11-25 23:33:47'),
(30, 'gabrielafigueroa006@gmail.com', '457513', '2025-11-25 20:07:28', 1, 0, '2025-11-26 01:57:28'),
(31, 'mvguerrero150@gmail.com', '168819', '2025-11-25 20:09:30', 1, 0, '2025-11-26 01:59:30'),
(32, 'castilloemer2002@gmail.com', '235952', '2025-11-25 20:09:36', 1, 0, '2025-11-26 01:59:36'),
(33, 'gymclub882@gmail.com', '642628', '2025-11-25 20:24:55', 1, 0, '2025-11-26 02:14:55'),
(34, 'diegoalejandroayalarodriguez@gmail.com', '598889', '2025-11-25 20:27:46', 0, 0, '2025-11-26 02:17:46'),
(35, 'diegoalejandrorodriguezayala@gmail.com', '594704', '2025-11-25 20:29:11', 1, 0, '2025-11-26 02:19:11'),
(36, 'diegoalejandrorodriguezayala@gmail.com', '064194', '2025-11-25 20:33:22', 1, 0, '2025-11-26 02:23:22'),
(37, 'mariela.gg1204@gmail.com', '948179', '2025-11-25 23:15:30', 1, 0, '2025-11-26 05:05:30'),
(38, 'yakelinsosa24@gmail.com', '816526', '2025-11-25 23:31:05', 1, 0, '2025-11-26 05:21:05'),
(39, 'forgertw01@gmail.com', '223447', '2025-11-26 09:05:04', 1, 0, '2025-11-26 14:55:04'),
(40, 'forgertw01@gmail.com', '466084', '2025-11-26 09:35:04', 1, 0, '2025-11-26 15:25:04'),
(41, 'forgertw01@gmail.com', '428050', '2025-11-26 09:54:21', 1, 0, '2025-11-26 15:44:21'),
(42, 'forgertw01@gmail.com', '652946', '2025-11-26 09:56:20', 1, 0, '2025-11-26 15:46:20'),
(43, 'forgertw01@gmail.com', '839517', '2025-11-26 10:00:26', 1, 0, '2025-11-26 15:50:26'),
(44, 'ra3307012024@unab.edu.sv', '622560', '2025-11-26 10:00:27', 1, 0, '2025-11-26 15:50:27');

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
-- Indices de la tabla `tbl_ms_usuario_flags`
--
ALTER TABLE `tbl_ms_usuario_flags`
  ADD PRIMARY KEY (`id_usuario`);

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
-- Indices de la tabla `tbl_recuperacion_codigos`
--
ALTER TABLE `tbl_recuperacion_codigos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_codigo` (`codigo`),
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
  ADD KEY `id_configuracion` (`id_configuracion`);

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
  MODIFY `id_calendario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `tbl_cliente`
--
ALTER TABLE `tbl_cliente`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `tbl_configuracion_notificaciones`
--
ALTER TABLE `tbl_configuracion_notificaciones`
  MODIFY `id_configuracion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id_bitacora` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=341;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_hist_contraseña`
--
ALTER TABLE `tbl_ms_hist_contraseña`
  MODIFY `id_hist` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_roles`
--
ALTER TABLE `tbl_ms_roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_usuario`
--
ALTER TABLE `tbl_ms_usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `tbl_ms_usuario_flags`
--
ALTER TABLE `tbl_ms_usuario_flags`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `tbl_objetos`
--
ALTER TABLE `tbl_objetos`
  MODIFY `id_objetos` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `tbl_pago`
--
ALTER TABLE `tbl_pago`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `tbl_periodo`
--
ALTER TABLE `tbl_periodo`
  MODIFY `id_periodo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `tbl_permisos`
--
ALTER TABLE `tbl_permisos`
  MODIFY `id_permisos` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `tbl_plan`
--
ALTER TABLE `tbl_plan`
  MODIFY `id_plan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT de la tabla `tbl_recuperacion_codigos`
--
ALTER TABLE `tbl_recuperacion_codigos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `tbl_suscripcion`
--
ALTER TABLE `tbl_suscripcion`
  MODIFY `id_suscripcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `tbl_tipo_notificacion`
--
ALTER TABLE `tbl_tipo_notificacion`
  MODIFY `id_tipoNotificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `tbl_verificacion_codigos`
--
ALTER TABLE `tbl_verificacion_codigos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

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
-- Filtros para la tabla `tbl_ms_usuario`
--
ALTER TABLE `tbl_ms_usuario`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`tbl_ms_roles_id_rol`) REFERENCES `tbl_ms_roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_ms_usuario_flags`
--
ALTER TABLE `tbl_ms_usuario_flags`
  ADD CONSTRAINT `tbl_ms_usuario_flags_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_ms_usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

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

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`u229022587_usergym`@`127.0.0.1` EVENT `autocalendario_event` ON SCHEDULE EVERY 1 DAY STARTS '2025-11-19 08:00:00' ON COMPLETION PRESERVE ENABLE DO CALL ejecutar_todo()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
