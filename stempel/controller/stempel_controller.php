<?php

namespace anszlus\stempel\controller;

class stempel_controller
{
    /** @var \phpbb\controller\helper */
    protected $controller_helper;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    protected $table_prefix;

    public function __construct(
        \phpbb\controller\helper $helper,
        \phpbb\request\request_interface $request,
        \phpbb\db\driver\driver_interface $db,
        $table_prefix
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->db = $db;
        $this->table_prefix = $table_prefix;
    }

    private function json_response($data) {
        $response = new \phpbb\json_response();
        $response->send($data);
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
        if (!$this->checkValidDate($data)) {
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
     * /stempel/integracja-ism?data=2025-09-06
     * Dane tylko dla kategorii
     * /stempel/integracja-ism?data=2025-09-06&subcategory_id=512
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

    

    // Sprawdza poprawność daty
    public function checkValidDate($date) {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') == $date;
    }
}
