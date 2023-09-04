<?php

namespace anszlus\stempel\event;

/**
 * Event listener
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{

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

    /** @var \phpbb\controller\helper */
    protected $controller_helper;

    /** @var \phpbb\language\language */
    protected $language;

    /** @var string phpbb_root_path */
    protected $phpbb_root_path;

    /** @var string phpEx */
    protected $php_ext;

    /** @var \gfksx\thanksforposts\core\helper */
    protected $helper;

    protected $stempel_table;

    /**
     * Constructor
     *
     * @param \phpbb\config\config                 $config                Config object
     * @param \phpbb\db\driver\driver_interface    $db                    DBAL object
     * @param \phpbb\auth\auth                     $auth                  Auth object
     * @param \phpbb\template\template             $template              Template object
     * @param \phpbb\user                          $user                  User object
     * @param \phpbb\cache\driver\driver_interface $cache                 Cache driver object
     * @param \phpbb\request\request_interface     $request               Request object
     * @param \phpbb\controller\helper             $controller_helper     Controller helper object
     * @param \phpbb\language\language             $language              Language object
     * @param string                               $phpbb_root_path       phpbb_root_path
     * @param string                               $php_ext               phpEx
     * @param rxu\PostsMerging\core\helper         $helper                The extension helper object
     * @access public
     */
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
        $phpbb_root_path, $php_ext, $stempel_table
    ) {
        global $phpbb_container;

        $this->config = $config;
        $this->db = $db;
        $this->auth = $auth;
        $this->template = $template;
        $this->user = $user;
        $this->cache = $cache;
        $this->request = $request;
        $this->controller_helper = $controller_helper;
        $this->language = $language;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $php_ext;
        $this->stempel_table = $stempel_table;
    }

    public function viewtopic_modify_postrow($event)
    {
        $row = $event['row'];
        $postrow = $event['post_row'];

        if (!isset($this->stempel_users[$postrow['POSTER_ID']])) {
            $sql = 'SELECT `stempel_id` FROM ' . $this->stempel_table . ' WHERE user_id = ' . $postrow['POSTER_ID'];
            $result = $this->db->sql_query($sql);
            $stempel_id = $this->db->sql_fetchfield('stempel_id');
            $this->db->sql_freeresult($result);
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
        );
    }

    public function memberlist_view_profile($event)
    {
        $member = $event['member'];
        $stempel_id = 0;
        if (!isset($this->stempel_users[$member['user_id']])) {
            $sql = 'SELECT `stempel_id` FROM ' . $this->stempel_table . ' WHERE user_id = ' . $member['user_id'];
            $result = $this->db->sql_query($sql);
            $stempel_id = $this->db->sql_fetchfield('stempel_id');
            $this->db->sql_freeresult($result);
        } else {
            $stempel_id = $this->stempel_users[$postrow['POSTER_ID']];
        }

        $this->stempel_users[$member['user_id']] = $stempel_id;

        $this->template->assign_vars([
            'STEMPEL_ENABLE' => $this->config['anszlus_stempel_enabled'],
            'STEMPEL_USER_ID' => $stempel_id,
        ]);
    }

}
