<?php
/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB')) {
    exit;
}

if (empty($lang) || !is_array($lang)) {
    $lang = [];
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, [
    'ACP_STEMPEL' => 'Stempel',
    'ACP_STEMPEL_SETTINGS' => 'Ustawienia Stempel',
    'ANSZLUS_STEMPEL_API_KEY' => 'Klucz Api do systemu Stempel',
    'ACP_STEMPEL_COUNTRY_ID' => 'ID Kraju w systemie stempel',
    'ACP_STEMPEL_SHOW_PROFILE_LINK' => 'Czy wyświetlać link do profilu stempel?',
    'ACP_STEMPEL_SETTINGS_SAVED' => 'Zapisano ustawienia stempel',
    'ACP_STEMPEL_SETTINGS_REFRESH_SAVED' => 'Pomyślnie zaktualizowane paszporty',
]);
