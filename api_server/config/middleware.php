<?php

return [
    // 全局中间件（作用于所有路由）
    '' => [
        // 跨域中间件必须放在第一位，确保最先执行和最后返回
        app\middleware\CorsMiddleware::class,
    ]
];