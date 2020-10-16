<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitf954159303f231e3c43595becbd495b7
{
    public static $prefixLengthsPsr4 = array (
        'D' => 
        array (
            'DigitalPenguin\\Commerce_Splitit\\' => 32,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'DigitalPenguin\\Commerce_Splitit\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'DigitalPenguin\\Commerce_Splitit\\API\\Response' => __DIR__ . '/../..' . '/src/API/Response.php',
        'DigitalPenguin\\Commerce_Splitit\\API\\SplititClient' => __DIR__ . '/../..' . '/src/API/SplititClient.php',
        'DigitalPenguin\\Commerce_Splitit\\Gateways\\Splitit' => __DIR__ . '/../..' . '/src/Gateways/Splitit.php',
        'DigitalPenguin\\Commerce_Splitit\\Gateways\\Transactions\\Order' => __DIR__ . '/../..' . '/src/Gateways/Transactions/Order.php',
        'DigitalPenguin\\Commerce_Splitit\\Modules\\Splitit' => __DIR__ . '/../..' . '/src/Modules/Splitit.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitf954159303f231e3c43595becbd495b7::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitf954159303f231e3c43595becbd495b7::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitf954159303f231e3c43595becbd495b7::$classMap;

        }, null, ClassLoader::class);
    }
}