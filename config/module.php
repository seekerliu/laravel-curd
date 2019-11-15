<?php
/**
 * author: seekerliu
 * createTime: 2017/8/15 下午3:46
 * Description:
 */
return [
    'controller' => [
        'directory_path' => app_path('Http'.DIRECTORY_SEPARATOR.'Controllers'),
        'namespace' => 'App\Http\Controllers',
        'repository_namespace' => 'App\Repositories',
        'name_format' => function($module){
            return 1;
        },
        'stubs' => [
            'controller',
        ],
    ],
    'repository' => [
        'directory_path' => app_path('Repositories'),
        'namespace' => 'App\Repositories',
        'stubs' => [
            'repository',
        ],
    ],
    'criteria' => [
        'directory_path' => app_path('Repositories'.DIRECTORY_SEPARATOR.'Criteria'),
        'namespace' => 'App\Repositories\Criteria',
        'stubs' => [
            'criteria',
        ],
    ],
    'view' => [
        'directory_path' => resource_path('views'),
        'stubs' => [
            'create', 'edit', 'index', 'list', 'modify',
            'search', 'show',
        ],
    ],
    'route' => [
        'directory_path' => base_path('routes/web'),
        'stubs' => [
            'route',
        ]
    ],
    'policy' => [
        'directory_path' => app_path('Policies'),
        'model_namespace' => 'App',
        'stubs' => [
            'policy',
        ]
    ]
];
