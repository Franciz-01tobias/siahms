<?php
/**
 * FreeITSM — auth strings (de).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php de
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Service Desk - Anmeldung',
    'heading' => 'ITSM-Anmeldung',
    'username' => 'Benutzername',
    'username_or_email' => 'Benutzername oder E-Mail',
    'password' => 'Passwort',
    'sign_in' => 'Anmelden',
    'forgot' => 'Passwort vergessen?',
    'email' => 'E-Mail',
    'email_placeholder' => 'name@beispiel.de',
    'continue' => 'Weiter',
    'or' => 'oder',
    'reveal_local_ldap' => 'Mit Benutzername und Passwort anmelden',
    'reveal_local_plain' => 'Mit einem lokalen Konto anmelden',
    'mfa_heading' => 'Bestätigung',
    'mfa_prompt' => 'Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Bestätigen',
    'mfa_verifying' => 'Wird überprüft...',
    'mfa_failed' => 'Überprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.',
    'mfa_cancel' => 'Abbrechen und zurück zur Anmeldung',
    'portal_link' => 'Zum Self-Service-Portal',
    'err_missing' => 'Bitte geben Sie Benutzername und Passwort ein',
    'err_invalid' => 'Benutzername oder Passwort falsch',
    'err_exception' => 'Anmeldefehler: {message}',
    'err_throttled_hours_one' => 'Zu viele fehlgeschlagene Versuche. Versuchen Sie es in 1 Stunde erneut.',
    'err_throttled_hours_many' => 'Zu viele fehlgeschlagene Versuche. Versuchen Sie es in {n} Stunden erneut.',
    'err_throttled_minutes_one' => 'Zu viele fehlgeschlagene Versuche. Versuchen Sie es in 1 Minute erneut.',
    'err_throttled_minutes_many' => 'Zu viele fehlgeschlagene Versuche. Versuchen Sie es in {n} Minuten erneut.',
    'err_locked_one' => 'Konto gesperrt. Versuchen Sie es in 1 Minute erneut.',
    'err_locked_many' => 'Konto gesperrt. Versuchen Sie es in {n} Minuten erneut.',
    'err_sso_account' => 'Dieses Konto meldet sich per Single Sign-on an. Bitte verwenden Sie die Anmeldeschaltfläche oben.',
    'err_not_analyst' => 'Ihr Konto hat keinen Analystenzugriff. Bitte verwenden Sie das Self-Service-Portal.',
    'err_no_group' => 'Ihr Konto ist nicht Mitglied einer Gruppe, die Zugriff auf FreeITSM gewährt.',
    'js_need_email' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
    'js_no_provider' => 'Für diese E-Mail-Adresse ist kein Single-Sign-on-Anbieter eingerichtet. Bitte wenden Sie sich an Ihren Administrator.',
];
