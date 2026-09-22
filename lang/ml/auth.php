<?php
/**
 * FreeITSM — auth strings (ml).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php ml
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'സർവീസ് ഡെസ്ക് ലോഗിൻ',
    'heading' => 'ITSM ലോഗിൻ',
    'username' => 'ഉപയോക്തൃനാമം',
    'username_or_email' => 'ഉപയോക്തൃനാമം അല്ലെങ്കിൽ ഇമെയിൽ',
    'password' => 'പാസ്‌വേഡ്',
    'sign_in' => 'സൈൻ ഇൻ',
    'forgot' => 'പാസ്‌വേഡ് മറന്നോ?',
    'email' => 'ഇമെയിൽ',
    'email_placeholder' => 'you@example.com',
    'continue' => 'തുടരുക',
    'or' => 'അല്ലെങ്കിൽ',
    'reveal_local_ldap' => 'ഉപയോക്തൃനാമവും പാസ്‌വേഡും ഉപയോഗിച്ച് സൈൻ ഇൻ ചെയ്യുക',
    'reveal_local_plain' => 'ലോക്കൽ അക്കൗണ്ട് ഉപയോഗിച്ച് സൈൻ ഇൻ ചെയ്യുക',
    'mfa_heading' => 'സ്ഥിരീകരണം',
    'mfa_prompt' => 'നിങ്ങളുടെ authenticator ആപ്പിൽ നിന്നുള്ള 6 അക്ക കോഡ് നൽകുക',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'സ്ഥിരീകരിക്കുക',
    'mfa_verifying' => 'സ്ഥിരീകരിക്കുന്നു...',
    'mfa_failed' => 'സ്ഥിരീകരണം പരാജയപ്പെട്ടു. വീണ്ടും ശ്രമിക്കുക.',
    'mfa_cancel' => 'റദ്ദാക്കി ലോഗിനിലേക്ക് മടങ്ങുക',
    'portal_link' => 'സെൽഫ്-സർവീസ് പോർട്ടലിലേക്ക് പോകുക',
    'err_missing' => 'ഉപയോക്തൃനാമവും പാസ്‌വേഡും നൽകുക',
    'err_invalid' => 'ഉപയോക്തൃനാമം അല്ലെങ്കിൽ പാസ്‌വേഡ് തെറ്റാണ്',
    'err_exception' => 'ലോഗിൻ പിശക്: {message}',
    'err_throttled_hours_one' => 'പരാജയപ്പെട്ട ശ്രമങ്ങൾ വളരെ കൂടുതൽ. 1 മണിക്കൂർ കഴിഞ്ഞ് വീണ്ടും ശ്രമിക്കുക.',
    'err_throttled_hours_many' => 'പരാജയപ്പെട്ട ശ്രമങ്ങൾ വളരെ കൂടുതൽ. {n} മണിക്കൂർ കഴിഞ്ഞ് വീണ്ടും ശ്രമിക്കുക.',
    'err_throttled_minutes_one' => 'പരാജയപ്പെട്ട ശ്രമങ്ങൾ വളരെ കൂടുതൽ. 1 മിനിറ്റ് കഴിഞ്ഞ് വീണ്ടും ശ്രമിക്കുക.',
    'err_throttled_minutes_many' => 'പരാജയപ്പെട്ട ശ്രമങ്ങൾ വളരെ കൂടുതൽ. {n} മിനിറ്റ് കഴിഞ്ഞ് വീണ്ടും ശ്രമിക്കുക.',
    'err_locked_one' => 'അക്കൗണ്ട് ലോക്ക് ചെയ്തു. 1 മിനിറ്റ് കഴിഞ്ഞ് വീണ്ടും ശ്രമിക്കുക.',
    'err_locked_many' => 'അക്കൗണ്ട് ലോക്ക് ചെയ്തു. {n} മിനിറ്റ് കഴിഞ്ഞ് വീണ്ടും ശ്രമിക്കുക.',
    'err_sso_account' => 'ഈ അക്കൗണ്ട് single sign-on ഉപയോഗിച്ചാണ് സൈൻ ഇൻ ചെയ്യുന്നത്. മുകളിലുള്ള സൈൻ-ഇൻ ബട്ടൺ ഉപയോഗിക്കുക.',
    'err_not_analyst' => 'നിങ്ങളുടെ അക്കൗണ്ടിന് അനലിസ്റ്റ് ആക്സസ് ഇല്ല. സെൽഫ്-സർവീസ് പോർട്ടൽ ഉപയോഗിക്കുക.',
    'err_no_group' => 'FreeITSM-ലേക്ക് ആക്സസ് നൽകുന്ന ഒരു ഗ്രൂപ്പിലും നിങ്ങളുടെ അക്കൗണ്ട് അംഗമല്ല.',
    'js_need_email' => 'നിങ്ങളുടെ ഇമെയിൽ നൽകുക.',
    'js_no_provider' => 'ആ ഇമെയിലിന് single sign-on provider സജ്ജീകരിച്ചിട്ടില്ല. നിങ്ങളുടെ അഡ്മിനിസ്ട്രേറ്ററെ ബന്ധപ്പെടുക.',
];
