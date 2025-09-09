<?php

namespace anszlus\stempel\controller;

class stempel_controller
{
    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\controller\helper */
    protected $helper;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\notification\manager */
    protected $notifications;

    /** @var \phpbb\template\template */
    protected $template;

    protected $table_prefix;

    /** @var \anszlus\stempel\service\stempel_service */
    protected $stempel_service;

    /** @var \phpbb\extension\manager $extension_manager */
    protected $extension_manager;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\user $user,
        \phpbb\controller\helper $helper,
        \phpbb\request\request_interface $request,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\notification\manager $notifications,
        \phpbb\template\template $template,
        $table_prefix,
        \anszlus\stempel\service\stempel_service $stempel_service
    ) {
        $this->config = $config;
        $this->user = $user;
        $this->helper = $helper;
        $this->request = $request;
        $this->db = $db;
        $this->notifications = $notifications;
        $this->template = $template;
        $this->table_prefix = $table_prefix;
        $this->stempel_service = $stempel_service;
    }

    // Zwraca dane jako json
    private function json_response($data) {
        header('Access-Control-Allow-Origin: *');
        
        $response = new \phpbb\json_response();
        $response->send($data);
        exit();
    }

    // Zwraca dane dla MUK
    private function muk_response($secret_data, $secret_key)
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');

        $data_to_encode = json_encode($secret_data);

        $encoded_data = $this->stempel_service->mukEncode($data_to_encode, $secret_key);
        echo $encoded_data;
        exit();
    }

    /**
     * Oczekiwany format 
     * /stempel/integracja-ism?data=2025-09-06
     * Dane tylko dla kategorii
     * /stempel/integracja-ism?data=2025-09-06&subcategory_id=512
     */
    public function integracja_ism()
    {
        // Deklarujemy zmienne
        $data = $this->request->variable('data', '', true); // domyślnie false
        $subcategory_id = $this->request->variable('subcategory_id', 0); // domyślnie false

        // Deklaruje zwracaną tablice
        $result_data = [];
        $result_data['wersja'] = 'p0.2';
        $result_data['status'] = 'BLAD';

        // sprawdzam czy jest data
        if (!$data) {
            $result_data['wynik'] = 'BRAK_DATY';
            $this->json_response($result_data);
        }

        // Sprawdzam poprawność daty
        if (!$this->stempel_service->checkValidDate($data)) {
            $result_data['wynik'] = 'BLEDNA_DATA' . $data;
            $this->json_response($result_data);
        }

        $data_poczatek_timestamp = strtotime($data);
        $data_koniec_timestamp = strtotime($data) + 86400;

        // Zapytanie SQL z rekurencyjnym CTE do pobrania wszystkich forów pod działem $subcategory_id
        if ($subcategory_id) {
            $sql = "
                WITH RECURSIVE SubForums AS (
                    SELECT forum_id
                    FROM {$this->table_prefix}forums
                    WHERE forum_id = $subcategory_id
                    UNION ALL
                    SELECT f.forum_id
                    FROM {$this->table_prefix}forums f
                    INNER JOIN SubForums sf ON sf.forum_id = f.parent_id
                )
                SELECT p.poster_id AS u, u.username AS n, COUNT(p.post_id) AS c
                FROM {$this->table_prefix}posts p
                LEFT JOIN {$this->table_prefix}users u ON p.poster_id = u.user_id
                WHERE p.post_time >= $data_poczatek_timestamp
                AND p.post_time < $data_koniec_timestamp
                AND p.forum_id IN (SELECT forum_id FROM SubForums)
                GROUP BY p.poster_id, u.username
                ORDER BY c DESC
            ";
        } else {
            $sql = "
                SELECT p.poster_id AS u, u.username AS n, COUNT(p.post_id) AS c
                FROM {$this->table_prefix}posts p
                LEFT JOIN {$this->table_prefix}users u ON p.poster_id = u.user_id
                WHERE p.post_time >= $data_poczatek_timestamp
                AND p.post_time < $data_koniec_timestamp
                GROUP BY p.poster_id, u.username
                ORDER BY c DESC
            ";
        }

        $users_result = $this->db->sql_query($sql);
        $wyniki_dla_dnia = [];
        while ($user = $this->db->sql_fetchrow($users_result)) {
            $wyniki_dla_dnia[] = $user;
        }
        $this->db->sql_freeresult($users_result);

        // Finalnie wyświetlamy dane
        $result_data['status'] = 'SUKCES';

        if ($wyniki_dla_dnia) {
            $result_data['wynik'] = $wyniki_dla_dnia;
        } else {
            $result_data['wynik'] = 'BRAK_WYNIKOW_DLA_DNIA';
        }

        $this->json_response($result_data);
    }

    /**
     * Oczekiwany format 
     * /stempel/integracja-nowiny
     * Dane tylko dla kategorii
     * /stempel/integracja-nowiny?limit=3
     */
    public function integracja_nowiny()
    {
        // Deklarujemy zmienne
        $limit_watkow = (int) $this->request->variable('limit', 20); // domyślnie 20

        // Deklaruje zwracaną tablice
        $zwracane_dane = array();
        $zwracane_dane['wersja'] = 'f0.2';
        $zwracane_dane['status'] = 'BLAD';

        // Pobieram liste for dostępnych dla botów (group_id = 1)
        $sql = 
            "SELECT GROUP_CONCAT(forum_id SEPARATOR ',') AS fora_dostepne_dla_botow
            FROM {$this->table_prefix}acl_groups
            WHERE group_id = 1
              AND forum_id > 0
        ";

        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        $publiczne_fora = $row ? $row['fora_dostepne_dla_botow'] : '';

        // Brak dostępnych for, więc zwracamy pustą tablice
        if (empty($publiczne_fora)) {
            $zwracane_dane['wynik'] = 'BRAK_PUBLICZNYCH_FOR';
            $this->json_response($zwracane_dane);
        }

        // Pobieram ostatnie wątki z publicznych for
        $sql = 
            "SELECT t.forum_id AS f, f.forum_name AS n,
                t.topic_last_post_subject AS s,
                t.topic_last_post_id AS p,
                t.topic_last_post_time AS t
            FROM {$this->table_prefix}topics t
            LEFT JOIN {$this->table_prefix}forums f
                ON t.forum_id = f.forum_id
            WHERE t.forum_id IN ($publiczne_fora)
            ORDER BY t.topic_last_post_time DESC
            LIMIT " . (int) $limit_watkow;

        $result = $this->db->sql_query($sql);

        $ostatnie_watki = [];

        while ($watek = $this->db->sql_fetchrow($result)) {
            $watek['s'] = str_replace('Re: ', '', $watek['s']);
            $ostatnie_watki[] = $watek;
        }
        
        if(!$ostatnie_watki) {
            $zwracane_dane['wynik'] = 'BRAK_OSTATNICH_WATKOW';
            $this->json_response($zwracane_dane);
        }

        // Wyświetlamy finalne dane
        $zwracane_dane['status'] = 'SUKCES';
        $zwracane_dane['wynik'] = $ostatnie_watki;
        $this->json_response($zwracane_dane);
    }

    /**
     * Oczekiwany format 
     * /stempel/integracja-muk?uid=2
     */
    public function integracja_muk()
    {
        $secret_key = $this->config['anszlus_stempel_notification_api_key'] ?? '';
        // Oczekuję id użytkownika na forum
        $uid = (int) $this->request->variable('uid', 0);

        $return_data = [];
        $return_data['count'] = 0;
        $return_data['notifications'] = [];

        // jeśli nie wybrano użytkownika, zwracam puste dane
        if($uid <= 0) {
            $this->muk_response($return_data, $secret_key);
        }

        // Pobieram powiadomienia dla uzytkownika o uid
        $notification = $this->notifications->load_notifications('notification.method.board', [
            'user_id' => $uid,
        ]);

        $return_notifications = [];
        foreach ($notification['notifications'] as $notify) {
            if($notify->notification_read == 1) continue;

            $notify_arr = (array) $notify->prepare_for_display();
            $notify_arr['TIMESTAMP'] = $notify->notification_time;
            
            $return_notifications[] = $notify_arr;
        }

        $return_data['count'] = count($return_notifications);
        $return_data['notifications'] = $return_notifications;

        $this->muk_response($return_data, $secret_key);
    }

    public function stempel_verification() {
        $verification_api_key = $this->config['anszlus_stempel_verification_api_key'];
        if(!$verification_api_key) {
            trigger_error("Weryfikacja poprzez API nie została włączona przez administratora forum.");
        }

        $user_id = $this->user->data['user_id'];
        if($user_id <= 1) {
            trigger_error("Zaloguj się, aby uzyskać dostęp do tej strony.");
        }

        // Aktualizujemy zweryfikowanych użytkowników, gdyby mąż zaufania zweryfikował
        $this->stempel_service->checkNewVerifiedStempelUsers(true);

        $stempel_id = $this->stempel_service->getStempelIdByUserId($user_id);
        if ($stempel_id) {
            trigger_error("Twój paszport jest już zweryfikowany. <a href='https://stempel.org.pl/paszport/$stempel_id' target='_blank'>Link do paszportu</a>");
        }

        $stempel_country_ccn3 = $this->config['anszlus_stempel_country_id'];
        if(!$stempel_country_ccn3) {
            trigger_error("Administrator forum, nie skonfigurował Identyfikatora kraju");
        }

        $submitted = $this->request->is_set_post('submit');
        // Wysłano formularz, więc sprawdzamy api
        if ($submitted) {
            $verifyCode = $this->request->variable('verify-code', '', true);
            
            $verification_api_url = "https://stempel.org.pl/api/weryfikacja0.php?kod=$verifyCode&klucz=$verification_api_key&forum=$user_id";

            // pobranie odpowiedzi API
            $json = @file_get_contents($verification_api_url);

            if ($json === false) {
                trigger_error('Nie udało się połączyć z API Stempel.');
            }

            $data = json_decode($json, true);

            if ($data === null) {
                trigger_error('Błąd dekodowania danych JSON.');
            }

            // sprawdzenie kodu błędu
            if ((int) $data['blad']['kod'] !== 200) {
                $error_msg = $data['blad']['komunikat'] ?? 'Nieznany błąd';
                trigger_error('Weryfikacja nieudana: ' . $error_msg);
            }

            // jeśli kod == 200, to weryfikacja poprawna
            $your_passport = $data['paszport'];

            // Aktualizujemy zweryfikowanych użytkowników
            $this->stempel_service->checkNewVerifiedStempelUsers(true);

            trigger_error('Weryfikacja przebiegła pomyślnie, twój numer paszportu stempel to <b>' . $your_passport . '</b>. Jeżeli uważasz, że dane są nieprawidłowe, zgłoś to.');
        }

        // Przekazanie zmiennych do szablonu
        $this->template->assign_vars([
            'S_FORM_ACTION' => $this->helper->route('anszlus_stempel_verification'),
            'S_USER_ID' => $user_id,
            'S_CCN3' => $stempel_country_ccn3
        ]);

        // Renderowanie strony wewnątrz szablonu phpBB
        return $this->helper->render('stempel_verification.html', 'Formularz weryfikacji');

    }
}
