<?php

namespace anszlus\stempel\event;

/**
 * Event listener
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{

    public $notification_cache_time = 5;
    protected $stempel_users = [];

    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\auth\auth */
    protected $auth;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\cache\driver\driver_interface */
    protected $cache;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /** @var \phpbb\language\language */
    protected $language;

    /** @var string phpbb_root_path */
    protected $phpbb_root_path;

    /** @var string phpEx */
    protected $php_ext;

    /** @var \phpbb\controller\helper */
    protected $helper;

    protected $stempel_table;

    /** @var \anszlus\stempel\service\stempel_service */
    protected $stempel_service;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\auth\auth $auth,
        \phpbb\template\template $template,
        \phpbb\user $user,
        \phpbb\cache\driver\driver_interface $cache,
        \phpbb\request\request_interface $request,
        \phpbb\controller\helper $controller_helper,
        \phpbb\language\language $language,
        $phpbb_root_path, $php_ext, $stempel_table,
        \anszlus\stempel\service\stempel_service $stempel_service
    ) {
        global $phpbb_container;

        $this->config = $config;
        $this->db = $db;
        $this->auth = $auth;
        $this->template = $template;
        $this->user = $user;
        $this->cache = $cache;
        $this->request = $request;
        $this->helper = $controller_helper;
        $this->language = $language;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $php_ext;
        $this->stempel_table = $stempel_table;
        $this->stempel_service = $stempel_service;
    }

    public function viewtopic_modify_postrow($event)
    {
        $postrow = $event['post_row'];

        if (!isset($this->stempel_users[$postrow['POSTER_ID']])) {
            $stempel_id = $this->stempel_service->getStempelIdByUserId($postrow['POSTER_ID']);
        } else {
            $stempel_id = $this->stempel_users[$postrow['POSTER_ID']];
        }
        $postrow['POST_AUTHOR_STEMPEL_ID'] = $stempel_id;

        $this->stempel_users[$postrow['POSTER_ID']] = $stempel_id;

        $event['post_row'] = $postrow;

        $this->template->assign_vars([
            'STEMPEL_ENABLE' => $this->config['anszlus_stempel_enabled'],
        ]);
    }

    public static function getSubscribedEvents()
    {
        return array(
            'core.viewtopic_modify_post_row' => 'viewtopic_modify_postrow',
            'core.memberlist_view_profile' => 'memberlist_view_profile',
            'core.user_setup' => 'check_new_stempel_users',
            'core.page_header' => 'add_stempel_navlink',
        );
    }

    public function memberlist_view_profile($event)
    {
        $member = $event['member'];
        $stempel_id = 0;
        if (!isset($this->stempel_users[$member['user_id']])) {
            $stempel_id = $this->stempel_service->getStempelIdByUserId($member['user_id']);
        } else {
            $stempel_id = $this->stempel_users[$member['user_id']];
        }

        $this->stempel_users[$member['user_id']] = $stempel_id;

        $this->template->assign_vars([
            'STEMPEL_ENABLE' => $this->config['anszlus_stempel_enabled'],
            'STEMPEL_USER_ID' => $stempel_id,
        ]);
    }

    public function check_new_stempel_users($event)
    {
        // Sprawdzam czy można już pobrac nowych
        $this->stempel_service->checkNewVerifiedStempelUsers();
    }

    public function add_stempel_navlink($event)
    {
        if(!$this->config['anszlus_stempel_notification_enabled'] || empty($this->config['anszlus_stempel_notification_api_key'])) {
            return;
        }
        // Sprawdź, czy użytkownik ma przypisany stempel_id
        $user_id = $this->user->data['user_id'];
        $stempel_id = 0;
        $notifications = [];

        $stempel_country_ccn3 = $this->config['anszlus_stempel_country_id'];
        if (!$stempel_country_ccn3) {
            // teoretycznie niepowinno się zdarzyć
            $stempel_country_ccn3 = 0;
        }

        // Pobieram stempel_id dla użytkownika
        if (!isset($this->stempel_users[$user_id])) {
            $stempel_id = $this->stempel_service->getStempelIdByUserId($user_id);
        } else {
            $stempel_id = $this->stempel_users[$user_id];
        }

        // Pobieram dane z API
        $verification_link = false;
        $navigation_url = 'https://stempel.org.pl/powiadomienia/';
        if($stempel_id) {
            // Klucz cache zależny od usera
            $cache_key = 'stempel_notifications_' . $user_id;
            // Próbuj pobrać z cache
            $notifications = $this->cache->get($cache_key);

            if ($notifications === false) {
                $api_url = 'https://stempel.org.pl/api/powiadomienia.php?sid='
                    . urlencode($stempel_id)
                    . '&cid=' . urlencode($stempel_country_ccn3);

                // @file_get_contents tłumi ewentualne warningi
                $jsonData = @file_get_contents($api_url);

                // Domyślnie HTTP code = 0 (nie udało się połączyć)
                $httpCode = 0;
                if (isset($http_response_header) && preg_match('{HTTP/\S+ (\d{3})}', $http_response_header[0], $m)) {
                    $httpCode = (int)$m[1];
                }

                // Dekodowanie tylko jeśli wszystko OK
                if ($jsonData !== false && $httpCode === 200) {
                    $notifications = (array) json_decode(
                        $this->stempel_service->mukDecode($jsonData, $this->config['anszlus_stempel_notification_api_key']),
                        true
                    );
                } else {
                    $notifications = []; // brak danych lub błąd połączenia
                }


                // Zapisz do cache
                $this->cache->put($cache_key, $notifications, $this->notification_cache_time);
            }
        } else {
            // Niezweryfikowany, więc sprawdzmy czy jest user secret api key do weryfikacji
            $verification_api_key = $this->config['anszlus_stempel_verification_api_key'];

            if($verification_api_key) {
                $verification_link = true;
                $navigation_url = $this->helper->route('anszlus_stempel_verification');

            }            
        }

        // Przekaż zmienne do szablonu
        $this->template->assign_vars([
            'STEMPEL_USER_VERIFIED' => $stempel_id,
            'STEMPEL_VERIFICATION_LINK' => $verification_link,
            'STEMPEL_NOTIFICATION_ENABLED' => $this->config['anszlus_stempel_notification_enabled'],
            'STEMPEL_NOTIFICATION_URL' => $navigation_url,
            'STEMPEL_CHAT_URL' => 'https://stempel.org.pl/chat/',
            'STEMPEL_NOTIFICATIONS' => $notifications,
            'STEMPEL_NOTIFICATIONS_COUNT' => ($notifications) ? count($notifications) : 0
        ]);
    }

}
