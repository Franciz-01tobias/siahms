<?php
/**
 * FreeITSM — auth strings (fr).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php fr
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Connexion au service desk',
    'heading' => 'Connexion ITSM',
    'username' => 'Nom d\'utilisateur',
    'username_or_email' => 'Nom d\'utilisateur ou e-mail',
    'password' => 'Mot de passe',
    'sign_in' => 'Se connecter',
    'forgot' => 'Mot de passe oublié ?',
    'email' => 'E-mail',
    'email_placeholder' => 'vous@exemple.com',
    'continue' => 'Continuer',
    'or' => 'ou',
    'reveal_local_ldap' => 'Se connecter avec un nom d\'utilisateur et un mot de passe',
    'reveal_local_plain' => 'Se connecter avec un compte local',
    'mfa_heading' => 'Vérification',
    'mfa_prompt' => 'Saisissez le code à 6 chiffres de votre application d\'authentification',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Vérifier',
    'mfa_verifying' => 'Vérification en cours...',
    'mfa_failed' => 'Échec de la vérification. Veuillez réessayer.',
    'mfa_cancel' => 'Annuler et retourner à la connexion',
    'portal_link' => 'Aller au portail libre-service',
    'err_missing' => 'Veuillez saisir le nom d\'utilisateur et le mot de passe',
    'err_invalid' => 'Nom d\'utilisateur ou mot de passe incorrect',
    'err_exception' => 'Erreur de connexion : {message}',
    'err_throttled_hours_one' => 'Trop de tentatives échouées. Réessayez dans 1 heure.',
    'err_throttled_hours_many' => 'Trop de tentatives échouées. Réessayez dans {n} heures.',
    'err_throttled_minutes_one' => 'Trop de tentatives échouées. Réessayez dans 1 minute.',
    'err_throttled_minutes_many' => 'Trop de tentatives échouées. Réessayez dans {n} minutes.',
    'err_locked_one' => 'Compte verrouillé. Réessayez dans 1 minute.',
    'err_locked_many' => 'Compte verrouillé. Réessayez dans {n} minutes.',
    'err_sso_account' => 'Ce compte se connecte via l\'authentification unique. Veuillez utiliser le bouton de connexion ci-dessus.',
    'err_not_analyst' => 'Votre compte n\'a pas d\'accès analyste. Veuillez utiliser le portail libre-service.',
    'err_no_group' => 'Votre compte n\'est membre d\'aucun groupe donnant accès à FreeITSM.',
    'js_need_email' => 'Veuillez saisir votre e-mail.',
    'js_no_provider' => 'Aucun fournisseur d\'authentification unique n\'est configuré pour cet e-mail. Veuillez contacter votre administrateur.',
];
