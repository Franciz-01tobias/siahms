<?php
/**
 * FreeITSM — auth strings (nn).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php nn
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Service Desk – innlogging',
    'heading' => 'ITSM-innlogging',
    'username' => 'Brukarnamn',
    'username_or_email' => 'Brukarnamn eller e-post',
    'password' => 'Passord',
    'sign_in' => 'Logg inn',
    'forgot' => 'Gløymt passord?',
    'email' => 'E-post',
    'email_placeholder' => 'deg@eksempel.no',
    'continue' => 'Hald fram',
    'or' => 'eller',
    'reveal_local_ldap' => 'Logg inn med brukarnamn og passord',
    'reveal_local_plain' => 'Logg inn med ein lokal konto',
    'mfa_heading' => 'Stadfesting',
    'mfa_prompt' => 'Skriv inn den 6-sifra koden frå autentiseringsappen din',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Stadfest',
    'mfa_verifying' => 'Stadfester ...',
    'mfa_failed' => 'Stadfestinga feila. Prøv igjen.',
    'mfa_cancel' => 'Avbryt og gå tilbake til innlogging',
    'portal_link' => 'Gå til sjølvbetjeningsportalen',
    'err_missing' => 'Skriv inn både brukarnamn og passord',
    'err_invalid' => 'Ugyldig brukarnamn eller passord',
    'err_exception' => 'Innloggingsfeil: {message}',
    'err_throttled_hours_one' => 'For mange mislykka forsøk. Prøv igjen om 1 time.',
    'err_throttled_hours_many' => 'For mange mislykka forsøk. Prøv igjen om {n} timar.',
    'err_throttled_minutes_one' => 'For mange mislykka forsøk. Prøv igjen om 1 minutt.',
    'err_throttled_minutes_many' => 'For mange mislykka forsøk. Prøv igjen om {n} minutt.',
    'err_locked_one' => 'Kontoen er låst. Prøv igjen om 1 minutt.',
    'err_locked_many' => 'Kontoen er låst. Prøv igjen om {n} minutt.',
    'err_sso_account' => 'Denne kontoen loggar inn med SSO. Bruk innloggingsknappen over.',
    'err_not_analyst' => 'Kontoen din har ikkje analytikartilgang. Bruk sjølvbetjeningsportalen.',
    'err_no_group' => 'Kontoen din er ikkje medlem av ei gruppe som gjev tilgang til FreeITSM.',
    'js_need_email' => 'Skriv inn e-posten din.',
    'js_no_provider' => 'Ingen SSO-leverandør er sett opp for den e-posten. Kontakt administratoren din.',
];
