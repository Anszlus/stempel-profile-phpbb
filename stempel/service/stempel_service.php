<?php

namespace anszlus\stempel\service;

class stempel_service
{

    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    protected $stempel_table;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\db\driver\driver_interface $db, 
        $stempel_table
    ) {
        $this->config = $config;
        $this->db = $db;
        $this->stempel_table = $stempel_table;
    }

    public function getStempelIdByUserId($user_id) {
        $user_id = (int) $user_id;
        $sql = 'SELECT `stempel_id` FROM ' . $this->stempel_table . ' WHERE user_id = ' . $user_id;
        $result = $this->db->sql_query($sql);
        $stempel_id = $this->db->sql_fetchfield('stempel_id');
        $this->db->sql_freeresult($result);

        return $stempel_id;
    }

    public function checkNewVerifiedStempelUsers($force_update = false)
    {
        // sprawdzam czy aktywne
        if (!$this->config['anszlus_stempel_enabled']) {
            return false;
        }

        // sprawdzam datę ostatniego sprawdzania, aby nie obciążać serwera
        $last_check = $this->config['anszlus_stempel_users_updated_at'];
        $toilet_time = 10800; // Czas co jaki ma sprawdzać
        $now = time();

        if (($last_check + $toilet_time) > $now) {
            // Jeżeli jeszcze nie cxas na automatyczne sprawdzenie, ale z jakiegoś powodu należy zaktualizować
            if(!$force_update) {
                return false;
            }
        }

        // sprawdzam czy wybrano ccn3 kraju
        $stempel_country_ccn3 = $this->config['anszlus_stempel_country_id'];
        if (!$stempel_country_ccn3) {
            return false;
        }

        // pobieram dane
        $status = $this->getNewVerifiedStempelUsers($stempel_country_ccn3);

        if (!$status) {
            return false;
        }

        $this->config->set('anszlus_stempel_users_updated_at', $now);
    }

    public function getNewVerifiedStempelUsers($cid) {
        $newDataUrl = 'https://stempel.org.pl/api/weryfikacje0.php?kraj=' . $cid;

        // Pobierz zawartość JSON z URL
        $jsonData = file_get_contents($newDataUrl);

        // Jeśli pobranie danych zakończyło się błędem
        if ($jsonData == false) {
            return false;
        }

        // Dekoduj dane JSON na tablicę lub obiekt PHP
        $data = json_decode($jsonData, true); // true oznacza dekodowanie do tablicy asocjacyjnej

        // Sprawdź, czy dekodowanie się nie powiodło
        if ($data == null) {
            // 'Błąd dekodowania danych JSON.';
            return false;
        }

        // usuwamy stare wpisy
        $deleteSql = 'DELETE FROM ' . $this->stempel_table . ' WHERE 1';
        $deleteResult = $this->db->sql_query($deleteSql);

        if ($data['blad']['kod'] !== 200) {
            // Błędne id kraju
            return false;
        }

        $insertData = [];
        foreach ($data['forum'] as $forumID => $stempelID) {
            $insertData[] = '(' . $forumID . ', ' . $stempelID . ')';
        }

        // dodajemy nowe
        $insertData = implode(', ', $insertData);
        $insertSql = 'INSERT INTO ' . $this->stempel_table . ' (`user_id`, `stempel_id`) VALUES ' . $insertData;
        $insertResult = $this->db->sql_query($insertSql);

        return true;
    }

    public function getStempelCountries()
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

        if (!isset($data['dane'])) {
            return [];
        }

        $data['dane'] = array_filter($data['dane'], function ($element) {
            return $element['kraj_stempel'] !== null;
        });

        return $data['dane'];
    }

    // Deszyfrowanie powiadomien z MUK
    public function mukEncode($text, $secret_key)
    {
        $ivlen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivlen);
        $coded_text = openssl_encrypt($text, 'aes-256-cbc', $secret_key, 0, $iv);
        $encode_info = base64_encode($iv . $coded_text);
        return $encode_info;
    }

    // Szyfrowanie powiadomień do MUK
    public function mukDecode($coded_info, $secret_key)
    {
        $coded_info = base64_decode($coded_info);
        $ivlen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($coded_info, 0, $ivlen);
        $coded_text = substr($coded_info, $ivlen);
        $tekst = openssl_decrypt($coded_text, 'aes-256-cbc', $secret_key, 0, $iv);
        return $tekst;
    }

    // Sprawdza poprawność daty
    public function checkValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') == $date;
    }
}
