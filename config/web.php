<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$cookieValidationKey = getenv('COOKIE_VALIDATION_KEY');
if ($cookieValidationKey !== false) {
    $cookieValidationKey = trim($cookieValidationKey);
}

if ($cookieValidationKey === false || $cookieValidationKey === '') {
    $cookieValidationKeyFile = dirname(__DIR__) . '/runtime/cookie-validation-key';

    if (is_file($cookieValidationKeyFile)) {
        $storedCookieValidationKey = file_get_contents($cookieValidationKeyFile);
        if ($storedCookieValidationKey === false) {
            throw new RuntimeException('Unable to read the local cookie validation key.');
        }
        $cookieValidationKey = trim($storedCookieValidationKey);
    }

    if ($cookieValidationKey === false || $cookieValidationKey === '') {
        $cookieValidationKey = bin2hex(random_bytes(32));
        if (file_put_contents($cookieValidationKeyFile, $cookieValidationKey, LOCK_EX) === false) {
            throw new RuntimeException('Unable to save the local cookie validation key.');
        }
    }
}

$config = [
    'id' => 'sitepro-marketing',
    'basePath' => dirname(__DIR__),
    'defaultRoute' => 'marketing/index',
    'name' => 'Site.pro Marketing Studio',
    'language' => 'en-US',
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => $cookieValidationKey,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
