<?php

/**
 * Bootstrap all services for the app
 */

$Config = require "config.php";

/**
 * Autoload
 *
 * Composer's autoloader is included in index.php to allow Tracy to be loaded right away
 */
$Loader = new Phalcon\Autoload\Loader();
$Loader->setNamespaces(
    [
        "Controllers" => $Config->dirs->file->app . "/controllers",
        "Components" => $Config->dirs->file->app . "/components",
        "Helpers"     => $Config->dirs->file->app . "/helpers",
        "Models"      => $Config->dirs->file->app . "/models"
    ]
);
$Loader->register();

$Container = new Phalcon\Di\FactoryDefault();
$Container->setShared("config", $Config);

/**
 * Database
 */
$Container->setShared("db", function () {
    $connectionParams = require "db.php";
    $conn = new Phalcon\Db\Adapter\Pdo\Mysql($connectionParams);
    return $conn;
});

/**
 * Dispatcher
 */
$Container->set("dispatcher", function () {
    $Dispatcher = new Phalcon\Mvc\Dispatcher();
    $Dispatcher->setDefaultNamespace("Controllers");
    return $Dispatcher;
});

/**
 * Metadata cache
 */
// $Container->set("modelsMetadata", function () {
//     $serializerFactory = new Phalcon\Storage\SerializerFactory();
//     $adapterFactory = new Phalcon\Cache\AdapterFactory($serializerFactory);
//     $options = [
//         "servers" => [
//             0 => [
//                 "host" => "127.0.0.1",
//                 "port" => 11211,
//                 "weight" => 1
//             ]
//         ],
//         "lifetime" => 86400,
//         "prefix" => "photolib-2022-11-24-0"
//     ];

//     return new Phalcon\Mvc\Model\Metadata\Libmemcached($adapterFactory, $options);
// });

/**
 * Routes
 */
$Container->set("router", function () {
    $Router = new Phalcon\Mvc\Router();
    $routes = require "routes.php";
    foreach ($routes as $pattern => $properties) {
        $Router->add($pattern, $properties);
    }
    return $Router;
});

/**
 * View
 */
$Container->setShared("voltService", function (Phalcon\Mvc\View $View) use ($Container, $Config) {
    $Volt = new Phalcon\Mvc\View\Engine\Volt($View, $Container);
    $Volt->setOptions([
        "always" => $Config->view->compileAlways,
        "path" => function (string $path) use ($Config): string {
            $relative_path = substr($path, strlen($Config->dirs->file->views));

            $compile_dir = $Config->dirs->file->viewsCompiled . dirname($relative_path);
            $compile_path = $compile_dir . "/" . basename($path);
            if (!is_dir($compile_dir)) {
                mkdir($compile_dir, 0777, true);
            }
            return $compile_path;
        }
    ]);

    $Compiler = $Volt->getCompiler();
    $Compiler->addFunction("filesize", function($resolvedArgs, $exprArgs) use ($Compiler) {
        $size = $Compiler->expression($exprArgs[0]['expr']);

        return "\Helpers\ViewHelper::filesize(" . $size . ")";
    });

    $Compiler->addFunction("icon", function($resolvedArgs, $exprArgs) use ($Compiler) {
        $icon = $Compiler->expression($exprArgs[0]['expr']);
        return '$this->viewHelper->icon(' . $icon . ')';
    });

    return $Volt;
});
$Container->setShared("view", function () use ($Config) {
    $View = new Phalcon\Mvc\View();
    $View->setViewsDir($Config->dirs->file->views);
    $View->registerEngines(
        [
            ".phtml" => "voltService"
        ]
    );
    return $View;
});
$Container->setShared("viewHelper", function () use ($Container, $Config) {
    return new \Helpers\ViewHelper($Container->get("url"), $Config);
});

/**
 * Url
 */
 $Container->setShared("url", function () use ($Config) {
    $Url = new Phalcon\Mvc\Url();
    $Url->setBaseUri($Config->dirs->web->root);
    return $Url;
 });