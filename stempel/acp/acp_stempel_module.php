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

        /** @var \anszlus\stempel\service\stempel_service $stempel_service */
        $stempel_service = $phpbb_container->get('anszlus.stempel.service');

        $this->tpl_name = 'acp_stempel';
        $this->page_title = 'Stempel';

        add_form_key('anszlus_stempel_settings');

        $status = true;
        if ($request->is_set_post('submit')) {
            if (!check_form_key('anszlus_stempel_settings')) {
                trigger_error('FORM_INVALID');
            }

            if ($request->variable('acp_stempel_refresh', 0) == 1) {
                $status = $stempel_service->checkNewVerifiedStempelUsers(true);
                trigger_error($language->lang('ACP_STEMPEL_SETTINGS_REFRESH_SAVED') . adm_back_link($this->u_action));
            } else {
                $config->set('anszlus_stempel_enabled', $request->variable('anszlus_stempel_enabled', 0));
                $config->set('anszlus_stempel_notification_enabled', $request->variable('anszlus_stempel_notification_enabled', 0));
                $config->set('anszlus_stempel_notification_api_key', $request->variable('anszlus_stempel_notification_api_key', ""));
                $config->set('anszlus_stempel_verification_api_key', $request->variable('anszlus_stempel_verification_api_key', ""));

                $oldConfigCountryId = $config['anszlus_stempel_country_id'];
                $newConfigCountryId = $request->variable('anszlus_stempel_country_id', 0);

                // Jeśli zmieniono ID kraju, pobierz nowe wartości
                if ($oldConfigCountryId !== $newConfigCountryId) {
                    // Pobiera dane z Api stempla i zapisuje w bazie
                    $status = $stempel_service->getNewVerifiedStempelUsers($newConfigCountryId);
                    if ($status) {
                        $config->set('anszlus_stempel_country_id', $newConfigCountryId);
                    } else {
                        $status = $stempel_service->getNewVerifiedStempelUsers($oldConfigCountryId);
                    }
                }

                trigger_error($language->lang('ACP_STEMPEL_SETTINGS_SAVED') . adm_back_link($this->u_action));
            }

        }


        $stempel_countries = $stempel_service->getStempelCountries();

        $template->assign_vars([
            'ACP_STEMPEL' => 'Stempel',
            'ANSZLUS_STEMPEL_ENABLED' => $config['anszlus_stempel_enabled'],
            'ANSZLUS_STEMPEL_API_KEY' => $config['anszlus_stempel_api_key'],
            'ANSZLUS_STEMPEL_NOTIFICATION_ENABLED' => $config['anszlus_stempel_notification_enabled'],
            'ANSZLUS_STEMPEL_NOTIFICATION_API_KEY' => $config['anszlus_stempel_notification_api_key'],
            'ANSZLUS_STEMPEL_VERIFICATION_API_KEY' => $config['anszlus_stempel_verification_api_key'],
            'ANSZLUS_STEMPEL_STEMPEL_ID' => $config['anszlus_stempel_country_id'],
            'U_ACTION' => $this->u_action,
            'STEMPEL' => $config,
            'STATUS' => $status,
            'STEMPEL_COUNTRIES' => $stempel_countries
        ]);

    }

    
}
