<?php
return
    [
        // Бандли яким потрібні рецепти
        'doctrine-behaviors' => true,
        'doctrine-eav-bundle' => true,
        'json-rpc-bundle' => true,
        'json-rpc-sdk-bundle' => true,
        'kafka-messenger' => true,
        'mcp-bundle' => true,
        'rest-bundle' => true,
        'rpc-mercure-transport' => true,
        'rpc-statistics-bundle' => true,

        // Оточення без рецептів
        'component-transport-amqp' => false,
        'component-transport-contracts' => false,
        'component-transport-kafka' => false,
        'doctrine-helper' => false,
        'dto-transformer' => false,
        'event-sourcing' => false,
        'json-rpc-client-sdk' => false,
        'rpc-objects' => false,
        'rpc-exceptions' => false,

        // приклад сторонніх вендорів
        // 'symfony/framework-bundle' => false,
        // 'nelmio/api-doc-bundle' => false,

    ];
