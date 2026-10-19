<?php
// Налаштування генератора рецептів (bin/generate.php).
// Список пакетів береться з configs/packages.php (true = потрібен рецепт).
return
    [
        'vendor' => 'ufo-tech',                 // префікс для коротких імен з packages.php
        'repository' => 'UFO-Tech/recipes',     // GitHub-репозиторій з рецептами
        'branch' => 'main',
        'github_org' => 'UFO-Tech',             // для fallback на GitHub tags, якщо пакета немає на Packagist

        'configs_dir' => 'configs/recipes',     // configs/recipes/<vendor>.<name>/{X.Y,X,default}.json + files/
        'recipes_dir' => 'recipes',
        'index_file' => 'index.json',

        // Опції окремих пакетів (усі необов'язкові)
        'packages' => [
            // 'json-rpc-bundle' => [
            //     'min_version' => '4.5',               // нижчі minor-версії не потрапляють в index.json
            //     'prerelease' => false,                // брати теги на кшталт 1.2.0-beta
            //     'github' => 'UFO-Tech/json-rpc-bundle', // репозиторій для fallback, якщо назва відрізняється
            // ],
        ],
    ];
