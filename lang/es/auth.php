<?php
/**
 * FreeITSM — auth strings (es).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php es
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Inicio de sesión — Centro de servicio',
    'heading' => 'Inicio de sesión ITSM',
    'username' => 'Nombre de usuario',
    'username_or_email' => 'Nombre de usuario o correo electrónico',
    'password' => 'Contraseña',
    'sign_in' => 'Iniciar sesión',
    'forgot' => '¿Ha olvidado su contraseña?',
    'email' => 'Correo electrónico',
    'email_placeholder' => 'you@example.com',
    'continue' => 'Continuar',
    'or' => 'o',
    'reveal_local_ldap' => 'Iniciar sesión con nombre de usuario y contraseña',
    'reveal_local_plain' => 'Iniciar sesión con una cuenta local',
    'mfa_heading' => 'Verificación',
    'mfa_prompt' => 'Introduzca el código de 6 dígitos de su aplicación de autenticación',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Verificar',
    'mfa_verifying' => 'Verificando...',
    'mfa_failed' => 'La verificación ha fallado. Inténtelo de nuevo.',
    'mfa_cancel' => 'Cancelar y volver al inicio de sesión',
    'portal_link' => 'Ir al portal de autoservicio',
    'err_missing' => 'Introduzca su nombre de usuario y su contraseña',
    'err_invalid' => 'Nombre de usuario o contraseña incorrectos',
    'err_exception' => 'Error de inicio de sesión: {message}',
    'err_throttled_hours_one' => 'Demasiados intentos fallidos. Vuelva a intentarlo en 1 hora.',
    'err_throttled_hours_many' => 'Demasiados intentos fallidos. Vuelva a intentarlo en {n} horas.',
    'err_throttled_minutes_one' => 'Demasiados intentos fallidos. Vuelva a intentarlo en 1 minuto.',
    'err_throttled_minutes_many' => 'Demasiados intentos fallidos. Vuelva a intentarlo en {n} minutos.',
    'err_locked_one' => 'Cuenta bloqueada. Vuelva a intentarlo en 1 minuto.',
    'err_locked_many' => 'Cuenta bloqueada. Vuelva a intentarlo en {n} minutos.',
    'err_sso_account' => 'Esta cuenta inicia sesión mediante inicio de sesión único. Use el botón de inicio de sesión de arriba.',
    'err_not_analyst' => 'Su cuenta no tiene acceso de analista. Use el portal de autoservicio.',
    'err_no_group' => 'Su cuenta no pertenece a ningún grupo que conceda acceso a FreeITSM.',
    'js_need_email' => 'Introduzca su correo electrónico.',
    'js_no_provider' => 'No hay ningún proveedor de inicio de sesión único configurado para ese correo electrónico. Póngase en contacto con su administrador.',
];
