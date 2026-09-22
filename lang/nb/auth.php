<?php
/**
 * FreeITSM — auth strings (nb).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php nb
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Pålogging til Service Desk',
    'heading' => 'ITSM-pålogging',
    'username' => 'Brukernavn',
    'username_or_email' => 'Brukernavn eller e-post',
    'password' => 'Passord',
    'sign_in' => 'Logg inn',
    'forgot' => 'Glemt passord?',
    'email' => 'E-post',
    'email_placeholder' => 'deg@eksempel.no',
    'continue' => 'Fortsett',
    'or' => 'eller',
    'reveal_local_ldap' => 'Logg inn med brukernavn og passord',
    'reveal_local_plain' => 'Logg inn med en lokal konto',
    'mfa_heading' => 'Verifisering',
    'mfa_prompt' => 'Skriv inn den 6-sifrede koden fra autentiseringsappen din',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Bekreft',
    'mfa_verifying' => 'Bekrefter...',
    'mfa_failed' => 'Verifiseringen mislyktes. Prøv igjen.',
    'mfa_cancel' => 'Avbryt og gå tilbake til innlogging',
    'portal_link' => 'Gå til selvbetjeningsportalen',
    'err_missing' => 'Skriv inn både brukernavn og passord',
    'err_invalid' => 'Ugyldig brukernavn eller passord',
    'err_exception' => 'Påloggingsfeil: {message}',
    'err_throttled_hours_one' => 'For mange mislykkede forsøk. Prøv igjen om 1 time.',
    'err_throttled_hours_many' => 'For mange mislykkede forsøk. Prøv igjen om {n} timer.',
    'err_throttled_minutes_one' => 'For mange mislykkede forsøk. Prøv igjen om 1 minutt.',
    'err_throttled_minutes_many' => 'For mange mislykkede forsøk. Prøv igjen om {n} minutter.',
    'err_locked_one' => 'Kontoen er låst. Prøv igjen om 1 minutt.',
    'err_locked_many' => 'Kontoen er låst. Prøv igjen om {n} minutter.',
    'err_sso_account' => 'Denne kontoen logger inn med SSO. Bruk påloggingsknappen ovenfor.',
    'err_not_analyst' => 'Kontoen din har ikke analytikertilgang. Bruk selvbetjeningsportalen.',
    'err_no_group' => 'Kontoen din er ikke medlem av en gruppe som gir tilgang til FreeITSM.',
    'js_need_email' => 'Skriv inn e-postadressen din.',
    'js_no_provider' => 'Ingen SSO-leverandør er satt opp for den e-postadressen. Kontakt administratoren din.',
];
