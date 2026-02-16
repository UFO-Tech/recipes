<?php
return
    [
        // Бандли яким потрібні рецепти
        'doctrine-eav-bundle' => true,
        'json-rpc-bundle' => true,
        'json-rpc-sdk-bundle' => true,
        'rest-bundle' => true,
        'rpc-mercure-transport' => true,
        'rpc-statistics-bundle' => true,

        // Оточення без рецептів
        'dto-transformer' => false,
        'rpc-objects' => false,
        'rpc-exceptions' => false,
        'event-sourcing' => false,

        // приклад сторонніх вендорів
        // 'symfony/framework-bundle' => false,
        // 'nelmio/api-doc-bundle' => false,

    ];