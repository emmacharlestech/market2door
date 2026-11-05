<?php
/**
 * Copy to config.php and fill in. config.php is never committed.
 */
return [
    'debug'      => false,
    // Random secret used to hash visitor IPs for rate limiting and sign unsubscribe links. Any long random string.
    'app_secret' => 'replace-with-64-random-hex-characters',
    // Database and private files. Must be OUTSIDE public_html.
    'data_dir'   => dirname(__DIR__, 2) . '/market2door_data',
    'site_url'   => 'https://market2door.ng',
    // The first admin account, created the first time the database is set up.
    // password_hash: output of password_hash('your password', PASSWORD_DEFAULT)
    'admin'      => [
        'email'         => 'support@market2door.ng',
        'name'          => 'Market2Door Team',
        'password_hash' => '',
    ],
];
