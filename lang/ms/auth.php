<?php
/**
 * FreeITSM — auth strings (ms).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php ms
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'Log Masuk Meja Perkhidmatan',
    'heading' => 'Log Masuk ITSM',
    'username' => 'Nama Pengguna',
    'username_or_email' => 'Nama pengguna atau e-mel',
    'password' => 'Kata Laluan',
    'sign_in' => 'Log Masuk',
    'forgot' => 'Lupa kata laluan?',
    'email' => 'E-mel',
    'email_placeholder' => 'anda@contoh.com',
    'continue' => 'Teruskan',
    'or' => 'atau',
    'reveal_local_ldap' => 'Log masuk dengan nama pengguna dan kata laluan',
    'reveal_local_plain' => 'Log masuk dengan akaun tempatan',
    'mfa_heading' => 'Pengesahan',
    'mfa_prompt' => 'Masukkan kod 6-digit daripada aplikasi pengesah anda',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'Sahkan',
    'mfa_verifying' => 'Mengesahkan...',
    'mfa_failed' => 'Pengesahan gagal. Sila cuba lagi.',
    'mfa_cancel' => 'Batal dan kembali ke log masuk',
    'portal_link' => 'Pergi ke Portal Layan Diri',
    'err_missing' => 'Sila masukkan nama pengguna dan kata laluan',
    'err_invalid' => 'Nama pengguna atau kata laluan tidak sah',
    'err_exception' => 'Ralat log masuk: {message}',
    'err_throttled_hours_one' => 'Terlalu banyak percubaan gagal. Cuba lagi dalam masa 1 jam.',
    'err_throttled_hours_many' => 'Terlalu banyak percubaan gagal. Cuba lagi dalam masa {n} jam.',
    'err_throttled_minutes_one' => 'Terlalu banyak percubaan gagal. Cuba lagi dalam masa 1 minit.',
    'err_throttled_minutes_many' => 'Terlalu banyak percubaan gagal. Cuba lagi dalam masa {n} minit.',
    'err_locked_one' => 'Akaun dikunci. Cuba lagi dalam masa 1 minit.',
    'err_locked_many' => 'Akaun dikunci. Cuba lagi dalam masa {n} minit.',
    'err_sso_account' => 'Akaun ini log masuk menggunakan single sign-on. Sila gunakan butang log masuk di atas.',
    'err_not_analyst' => 'Akaun anda tidak mempunyai akses penganalisis. Sila gunakan portal layan diri.',
    'err_no_group' => 'Akaun anda bukan ahli mana-mana kumpulan yang memberikan akses kepada FreeITSM.',
    'js_need_email' => 'Sila masukkan e-mel anda.',
    'js_no_provider' => 'Tiada pembekal single sign-on disediakan untuk e-mel tersebut. Sila hubungi pentadbir anda.',
];
