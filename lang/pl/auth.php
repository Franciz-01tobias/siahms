<?php
/**
 * FreeITSM — auth strings (pl).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php pl
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Centrum obsługi - logowanie',
    'heading' => 'Logowanie ITSM',
    'username' => 'Nazwa użytkownika',
    'username_or_email' => 'Nazwa użytkownika lub e-mail',
    'password' => 'Hasło',
    'sign_in' => 'Zaloguj się',
    'forgot' => 'Nie pamiętasz hasła?',
    'email' => 'E-mail',
    'email_placeholder' => 'you@example.com',
    'continue' => 'Dalej',
    'or' => 'lub',
    'reveal_local_ldap' => 'Zaloguj się przy użyciu nazwy użytkownika i hasła',
    'reveal_local_plain' => 'Zaloguj się przy użyciu konta lokalnego',
    'mfa_heading' => 'Weryfikacja',
    'mfa_prompt' => 'Wprowadź 6-cyfrowy kod z aplikacji uwierzytelniającej',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Zweryfikuj',
    'mfa_verifying' => 'Weryfikowanie...',
    'mfa_failed' => 'Weryfikacja nie powiodła się. Spróbuj ponownie.',
    'mfa_cancel' => 'Anuluj i wróć do logowania',
    'portal_link' => 'Przejdź do Portalu samoobsługowego',
    'err_missing' => 'Podaj nazwę użytkownika i hasło',
    'err_invalid' => 'Nieprawidłowa nazwa użytkownika lub hasło',
    'err_exception' => 'Błąd logowania: {message}',
    'err_throttled_hours_one' => 'Zbyt wiele nieudanych prób. Spróbuj ponownie za 1 godzinę.',
    'err_throttled_hours_many' => 'Zbyt wiele nieudanych prób. Spróbuj ponownie za {n} godzin.',
    'err_throttled_minutes_one' => 'Zbyt wiele nieudanych prób. Spróbuj ponownie za 1 minutę.',
    'err_throttled_minutes_many' => 'Zbyt wiele nieudanych prób. Spróbuj ponownie za {n} minut.',
    'err_locked_one' => 'Konto zablokowane. Spróbuj ponownie za 1 minutę.',
    'err_locked_many' => 'Konto zablokowane. Spróbuj ponownie za {n} minut.',
    'err_sso_account' => 'To konto loguje się przez SSO. Użyj przycisku logowania powyżej.',
    'err_not_analyst' => 'Twoje konto nie ma dostępu analityka. Skorzystaj z portalu samoobsługowego.',
    'err_no_group' => 'Twoje konto nie należy do żadnej grupy uprawniającej do dostępu do FreeITSM.',
    'js_need_email' => 'Podaj swój adres e-mail.',
    'js_no_provider' => 'Dla tego adresu e-mail nie skonfigurowano dostawcy SSO. Skontaktuj się z administratorem.',
];
