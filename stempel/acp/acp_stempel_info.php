<?php

namespace anszlus\stempel\acp;

class acp_stempel_info
{
    function module()
    {
        return [
            'filename' => '\anszlus\stempel\acp\acp_stempel_module',
            'title' => 'ACP_STEMPEL_SETTINGS',
            'version' => '0.0.1',
            'modes' => [
                'stempel' => [
                    'title' => 'ACP_STEMPEL_SETTINGS',
                    'auth' => 'ext_anszlus/stempel && acl_a_board',
                    'cat' => ['ACP_STEMPEL']],
            ],
        ];
    }
}
