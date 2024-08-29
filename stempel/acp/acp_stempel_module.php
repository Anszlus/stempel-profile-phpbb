<?php

namespace anszlus\stempel\acp;

/**
 * @package acp
 */
class acp_stempel_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    function main($id, $mode)
    {
        global $phpbb_container;

        /** @var \phpbb\language\language $language Language object */
        $language = $phpbb_container->get('language');

        /** @var \phpbb\template\template $template Template object */
        $template = $phpbb_container->get('template');

        /** @var \phpbb\request\request $request Request object */
        $request = $phpbb_container->get('request');

        /** @var \phpbb\config\config $config Config object */
        $config = $phpbb_container->get('config');

        /** @var \phpbb\db\driver\driver_interface $db DBAL object */
        $db = $phpbb_container->get('dbal.conn');

        $this->tpl_name = 'acp_stempel';
        $this->page_title = 'Stempel';

        add_form_key('anszlus_stempel_settings');

        $status = true;
        if ($request->is_set_post('submit')) {
            if (!check_form_key('anszlus_stempel_settings')) {
                trigger_error('FORM_INVALID');
            }

            if ($request->variable('acp_stempel_refresh', 0) == 1) {
                $status = $this->updateUsersStempelId($config['anszlus_stempel_country_id']);
                trigger_error($language->lang('ACP_STEMPEL_SETTINGS_REFRESH_SAVED') . adm_back_link($this->u_action));
            } else {
                $config->set('anszlus_stempel_enabled', $request->variable('anszlus_stempel_enabled', 0));
                // $config->set('anszlus_stempel_api_key', $request->variable('anszlus_stempel_api_key', 0));

                $oldConfigCountryId = $config['anszlus_stempel_country_id'];
                $newConfigCountryId = $request->variable('anszlus_stempel_country_id', 0);

                // Jeśli zmieniono ID kraju, pobierz nowe wartości
                if ($oldConfigCountryId !== $newConfigCountryId) {
                    // Pobiera dane z Api stempla i zapisuje w bazie
                    $status = $this->updateUsersStempelId($newConfigCountryId);
                    if ($status) {
                        $config->set('anszlus_stempel_country_id', $newConfigCountryId);
                    } else {
                        $status = $this->updateUsersStempelId($oldConfigCountryId);
                    }
                }

                trigger_error($language->lang('ACP_STEMPEL_SETTINGS_SAVED') . adm_back_link($this->u_action));
            }

        }


        $stempel_countries = $this->getStempelCountries();

        $template->assign_vars([
            'ACP_STEMPEL' => 'Stempel',
            'ANSZLUS_STEMPEL_ENABLED' => $config['anszlus_stempel_enabled'],
            'ANSZLUS_STEMPEL_API_KEY' => $config['anszlus_stempel_api_key'],
            'ANSZLUS_STEMPEL_STEMPEL_ID' => $config['anszlus_stempel_country_id'],
            'U_ACTION' => $this->u_action,
            'STEMPEL' => $config,
            'STATUS' => $status,
            'STEMPEL_COUNTRIES' => $stempel_countries
        ]);

    }

    private function getStempelCountries() 
    {
        // Ustawienie URL API
        $stempel_countries_api_url = 'https://stempel.org.pl/api/kraje0.php';

        // Inicjalizacja sesji cURL
        $ch = curl_init($stempel_countries_api_url);

        // Ustawienie opcji cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Zwracaj wynik zamiast go wyświetlać
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); // Ustawienie nagłówków
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Limit czasu w sekundach

        // Pobieranie danych z API
        $response = curl_exec($ch);

        if ($response === FALSE) {
            // coś poszło nie tak
            return [];
        }

        // Zamknięcie sesji cURL
        curl_close($ch);

        // Dekodowanie danych JSON
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // błąd dekodowania JSON
            return [];
        }

        if(!isset($data['dane']))
        {
            return [];
        }

        $data['dane'] = array_filter($data['dane'], function($element) {
            return $element['kraj_stempel'] !== null;
        });

        return $data['dane'];
    }

    private function updateUsersStempelId($id)
    {
        global $phpbb_container;

        /** @var \phpbb\db\driver\driver_interface $db DBAL object */
        $db = $phpbb_container->get('dbal.conn');

        /** @var string $thanks_table _thanks database table */
        $stempel_table = $phpbb_container->getParameter('stempel.table');

        $url = 'https://stempel.org.pl/api/weryfikacje0.php?kraj=' . $id;

        // Pobierz zawartość JSON z URL
        $jsonData = file_get_contents($url);

        // Jeśli pobranie danych zakończyło się błędem
        if ($jsonData == false) {
            return false;
        }

        // usuwamy stare wpisy
        $deleteSql = 'DELETE FROM ' . $stempel_table . ' WHERE 1';
        $deleteResult = $db->sql_query($deleteSql);

        // Dekoduj dane JSON na tablicę lub obiekt PHP
        $data = json_decode($jsonData, true); // true oznacza dekodowanie do tablicy asocjacyjnej

        // Sprawdź, czy dekodowanie się nie powiodło
        if ($data == null) {
            // 'Błąd dekodowania danych JSON.';
            return false;
        }

        if ($data['blad']['kod'] !== 200) {
            // Błędne id kraju
            return false;
        }
        // Możesz teraz użyć $data do dostępu do otrzymanych danych

        $insertData = [];
        foreach ($data['forum'] as $forumID => $stempelID) {
            $insertData[] = '(' . $forumID . ', ' . $stempelID . ')';
        }

        // dodajemy nowe
        $insertData = implode(', ', $insertData);
        $insertSql = 'INSERT INTO ' . $stempel_table . ' (`user_id`, `stempel_id`) VALUES ' . $insertData;
        $insertResult = $db->sql_query($insertSql);

        return true;
    }
}
