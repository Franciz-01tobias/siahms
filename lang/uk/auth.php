<?php
/**
 * FreeITSM — auth strings (uk).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php uk
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Вхід до служби підтримки',
    'heading' => 'Вхід до ITSM',
    'username' => 'Ім\'я користувача',
    'username_or_email' => 'Ім\'я користувача або електронна пошта',
    'password' => 'Пароль',
    'sign_in' => 'Увійти',
    'forgot' => 'Забули пароль?',
    'email' => 'Електронна пошта',
    'email_placeholder' => 'you@example.com',
    'continue' => 'Продовжити',
    'or' => 'або',
    'reveal_local_ldap' => 'Увійти з іменем користувача та паролем',
    'reveal_local_plain' => 'Увійти з локальним обліковим записом',
    'mfa_heading' => 'Перевірка',
    'mfa_prompt' => 'Введіть 6-значний код із застосунку автентифікатора',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Перевірити',
    'mfa_verifying' => 'Перевірка...',
    'mfa_failed' => 'Перевірка не вдалася. Спробуйте ще раз.',
    'mfa_cancel' => 'Скасувати й повернутися до входу',
    'portal_link' => 'Перейти до порталу самообслуговування',
    'err_missing' => 'Введіть ім\'я користувача та пароль',
    'err_invalid' => 'Неправильне ім\'я користувача або пароль',
    'err_exception' => 'Помилка входу: {message}',
    'err_throttled_hours_one' => 'Забагато невдалих спроб. Спробуйте знову через 1 годину.',
    'err_throttled_hours_many' => 'Забагато невдалих спроб. Спробуйте знову через {n} год.',
    'err_throttled_minutes_one' => 'Забагато невдалих спроб. Спробуйте знову через 1 хвилину.',
    'err_throttled_minutes_many' => 'Забагато невдалих спроб. Спробуйте знову через {n} хв.',
    'err_locked_one' => 'Обліковий запис заблоковано. Спробуйте знову через 1 хвилину.',
    'err_locked_many' => 'Обліковий запис заблоковано. Спробуйте знову через {n} хв.',
    'err_sso_account' => 'Цей обліковий запис входить через єдиний вхід. Скористайтеся кнопкою входу вище.',
    'err_not_analyst' => 'Ваш обліковий запис не має доступу аналітика. Скористайтеся порталом самообслуговування.',
    'err_no_group' => 'Ваш обліковий запис не входить до групи, яка надає доступ до FreeITSM.',
    'js_need_email' => 'Введіть свою електронну адресу.',
    'js_no_provider' => 'Для цієї електронної адреси не налаштовано жодного постачальника єдиного входу. Зверніться до адміністратора.',
];
