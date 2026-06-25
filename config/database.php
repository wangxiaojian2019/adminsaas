<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            // 生产标准：优先读取 .env 环境变量，若无则降级为空或默认值
            'host'        => getenv('DB_HOST') ?: '127.0.0.1',
            'port'        => getenv('DB_PORT') ?: 3306,
            'database'    => getenv('DB_DATABASE') ?: 'crmguanlixit',  
            'username'    => getenv('DB_USERNAME') ?: 'CRMGUANLIXIT',  
            'password'    => getenv('DB_PASSWORD') ?: '', // 严禁将密码 JRTk5XLYGsSC6thZ 写死在此处
            'unix_socket' => '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
        ],
    ],
];