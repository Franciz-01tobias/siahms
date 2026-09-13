<?php
/**
 * FreeITSM — auth strings (gu).
 *
 * Keys mirror lang/en/auth.php exactly. A key absent here falls back to
 * English at runtime, so this file may be incomplete without breaking
 * anything. Check coverage with: php scripts/i18n_audit.php gu
 *
 * ⚠️ Placeholders like {name} and %d are substituted at runtime — printf
 * tokens substitute BY POSITION, so their order must match English.
 */

return [
    'browser_title' => 'સેવા ડેસ્ક લોગિન',
    'heading' => 'ITSM લોગિન',
    'username' => 'યુઝરનેમ',
    'username_or_email' => 'યુઝરનેમ અથવા ઈમેલ',
    'password' => 'પાસવર્ડ',
    'sign_in' => 'સાઈન ઈન',
    'forgot' => 'પાસવર્ડ ભૂલી ગયા?',
    'email' => 'ઈમેલ',
    'email_placeholder' => 'you@example.com',
    'continue' => 'ચાલુ રાખો',
    'or' => 'અથવા',
    'reveal_local_ldap' => 'યુઝરનેમ અને પાસવર્ડ સાથે સાઈન ઈન કરો',
    'reveal_local_plain' => 'લોકલ એકાઉન્ટ સાથે સાઈન ઈન કરો',
    'mfa_heading' => 'ચકાસણી',
    'mfa_prompt' => 'તમારી ઓથેન્ટિકેટર એપમાંથી 6-અંકનો કોડ દાખલ કરો',
    'mfa_placeholder' => '------',
    'mfa_verify' => 'ચકાસો',
    'mfa_verifying' => 'ચકાસી રહ્યું છે...',
    'mfa_failed' => 'ચકાસણી નિષ્ફળ. કૃપા કરીને ફરી પ્રયાસ કરો.',
    'mfa_cancel' => 'રદ કરો અને લોગિન પર પાછા જાઓ',
    'portal_link' => 'સેલ્ફ-સર્વિસ પોર્ટલ પર જાઓ',
    'err_missing' => 'કૃપા કરીને યુઝરનેમ અને પાસવર્ડ બંને દાખલ કરો',
    'err_invalid' => 'અમાન્ય યુઝરનેમ અથવા પાસવર્ડ',
    'err_exception' => 'લોગિન ભૂલ: {message}',
    'err_throttled_hours_one' => 'ઘણા બધા નિષ્ફળ પ્રયાસો. 1 કલાકમાં ફરી પ્રયાસ કરો.',
    'err_throttled_hours_many' => 'ઘણા બધા નિષ્ફળ પ્રયાસો. {n} કલાકમાં ફરી પ્રયાસ કરો.',
    'err_throttled_minutes_one' => 'ઘણા બધા નિષ્ફળ પ્રયાસો. 1 મિનિટમાં ફરી પ્રયાસ કરો.',
    'err_throttled_minutes_many' => 'ઘણા બધા નિષ્ફળ પ્રયાસો. {n} મિનિટમાં ફરી પ્રયાસ કરો.',
    'err_locked_one' => 'એકાઉન્ટ લૉક થયું. 1 મિનિટમાં ફરી પ્રયાસ કરો.',
    'err_locked_many' => 'એકાઉન્ટ લૉક થયું. {n} મિનિટમાં ફરી પ્રયાસ કરો.',
    'err_sso_account' => 'આ એકાઉન્ટ સિંગલ સાઈન-ઓન સાથે સાઈન ઈન થાય છે. કૃપા કરીને ઉપરના સાઈન-ઈન બટનનો ઉપયોગ કરો.',
    'err_not_analyst' => 'તમારા એકાઉન્ટ પાસે એનાલિસ્ટ ઍક્સેસ નથી. કૃપા કરીને સેલ્ફ-સર્વિસ પોર્ટલનો ઉપયોગ કરો.',
    'err_no_group' => 'તમારું એકાઉન્ટ FreeITSM ની ઍક્સેસ આપતા કોઈ ગ્રુપનું સભ્ય નથી.',
    'js_need_email' => 'કૃપા કરીને તમારો ઈમેલ દાખલ કરો.',
    'js_no_provider' => 'તે ઈમેલ માટે કોઈ સિંગલ સાઈન-ઓન પ્રદાતા ગોઠવેલ નથી. કૃપા કરીને તમારા એડમિનિસ્ટ્રેટરનો સંપર્ક કરો.',
];
