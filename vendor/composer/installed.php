<?php return array(
    'root' => array(
        'name' => 'match2pay/match2pay-wc-gateway-plugin',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => NULL,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'appsero/client' => array(
            'pretty_version' => 'dev-develop',
            'version' => 'dev-develop',
            'reference' => '769b6177525fe1e3bcf297605441e6703f868eb4',
            'type' => 'library',
            'install_path' => __DIR__ . '/../appsero/client',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'match2pay/match2pay-wc-gateway-plugin' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => NULL,
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
