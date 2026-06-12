<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => '127.0.0.1',
            'port'        => 3306,
            'database'    => 'crmguanlixit',  // 你的数据库名
            'username'    => 'CRMGUANLIXIT',  // 你的数据库用户名（报错显示你用的是这个）
            'password'    => 'JRTk5XLYGsSC6thZ', // ！！！修改这里！！！
            'unix_socket' => '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
        ],
    ],
];