<?php
/**
 * FreeITSM — auth strings (pt-BR).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php pt-BR
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Login do Service Desk',
    'heading' => 'Login do ITSM',
    'username' => 'Nome de usuário',
    'username_or_email' => 'Nome de usuário ou e-mail',
    'password' => 'Senha',
    'sign_in' => 'Entrar',
    'forgot' => 'Esqueceu a senha?',
    'email' => 'E-mail',
    'email_placeholder' => 'voce@exemplo.com',
    'continue' => 'Continuar',
    'or' => 'ou',
    'reveal_local_ldap' => 'Entrar com um nome de usuário e senha',
    'reveal_local_plain' => 'Entrar com uma conta local',
    'mfa_heading' => 'Verificação',
    'mfa_prompt' => 'Digite o código de 6 dígitos do seu aplicativo autenticador',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Verificar',
    'mfa_verifying' => 'Verificando...',
    'mfa_failed' => 'Falha na verificação. Tente novamente.',
    'mfa_cancel' => 'Cancelar e voltar ao login',
    'portal_link' => 'Ir para o Portal de Autoatendimento',
    'err_missing' => 'Digite o nome de usuário e a senha',
    'err_invalid' => 'Usuário ou senha inválidos',
    'err_exception' => 'Erro de login: {message}',
    'err_throttled_hours_one' => 'Muitas tentativas malsucedidas. Tente novamente em 1 hora.',
    'err_throttled_hours_many' => 'Muitas tentativas malsucedidas. Tente novamente em {n} horas.',
    'err_throttled_minutes_one' => 'Muitas tentativas malsucedidas. Tente novamente em 1 minuto.',
    'err_throttled_minutes_many' => 'Muitas tentativas malsucedidas. Tente novamente em {n} minutos.',
    'err_locked_one' => 'Conta bloqueada. Tente novamente em 1 minuto.',
    'err_locked_many' => 'Conta bloqueada. Tente novamente em {n} minutos.',
    'err_sso_account' => 'Esta conta faz login por SSO. Use o botão de login acima.',
    'err_not_analyst' => 'Sua conta não tem acesso de analista. Use o portal de autoatendimento.',
    'err_no_group' => 'Sua conta não é membro de um grupo que concede acesso ao FreeITSM.',
    'js_need_email' => 'Digite seu e-mail.',
    'js_no_provider' => 'Nenhum provedor de SSO está configurado para esse e-mail. Entre em contato com o administrador.',
];
