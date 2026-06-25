<?php
return [
    // 全局中间件执行顺序 (由上至下穿透，由下至上返回)
    '' => [
        app\middleware\CorsMiddleware::class,      // 第 1 层：跨域放行 (最外层阻拦与修饰)
        app\middleware\RateLimitMiddleware::class, // 第 2 层：流量防刷
        app\middleware\AuthMiddleware::class,      // 第 3 层：JWT身份鉴权
        app\middleware\TenantCheck::class,         // 第 4 层：SaaS租户隔离
    ]
];