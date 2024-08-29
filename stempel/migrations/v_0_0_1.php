<?php

namespace anszlus\stempel\migrations;

class v_0_0_1 extends \phpbb\db\migration\migration
{

    public function effectively_installed()
    {
        return isset($this->config['anszlus_stempel_enabled']) && $this->db_tools->sql_table_exists($this->table_prefix . 'stempel') && $this->db_tools->sql_column_exists($this->table_prefix . 'stempel', 'stempel_id');
    }

    public function update_schema()
    {
        if (!$this->db_tools->sql_table_exists($this->table_prefix . 'stempel')) {
            return [
                'add_tables' => [
                    $this->table_prefix . 'stempel' => [
                        'COLUMNS' => [
                            'user_id' => ['UINT', 0],
                            'stempel_id' => ['UINT', 0],
                        ],
                        'PRIMARY_KEY' => ['user_id', 'stempel_id'],
                    ],
                ],
            ];
        }
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [$this->table_prefix . 'stempel'],
        ];
    }

    public function update_data()
    {
        return array(
            // Add the config variable we want to be able to set
            ['config.add', ['anszlus_stempel_enabled', 0]],
            ['config.add', ['anszlus_stempel_api_key', 0]],
            ['config.add', ['anszlus_stempel_country_id', 0]],

            ['module.add',
                [
                    'acp',
                    'ACP_CAT_DOT_MODS',
                    'ACP_STEMPEL',
                ],
            ],

            ['module.add',
                ['acp', 'ACP_STEMPEL',
                    [
                        'module_basename' => '\anszlus\stempel\acp\acp_stempel_module',
                        'module_langname' => 'ACP_STEMPEL_SETTINGS',
                        'module_mode' => 'stempel',
                        'module_auth' => 'ext_anszlus/stempel && acl_a_board',
                    ],
                ],
            ],

        );

    }

}
