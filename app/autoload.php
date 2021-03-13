<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Composer\Autoload\ClassLoader;

/**
 * @var ClassLoader $loader
 */
$loader = require __DIR__.'/../vendor/autoload.php';
$loader->add('FOS',__DIR__.'/../vendor/bundles');
$loader->add('Stfalcon',__DIR__.'/../vendor/bundles');
//$loader->add('Ekino',__DIR__.'/../vendor/bundles');
$loader->add('BladeTester',__DIR__.'/../vendor/bundles');
$loader->add('PHPExcel',__DIR__.'/../vendor/bundles/PhpExcel/lib');
$loader->add('Ratchet',__DIR__.'/../vendor/bundles/Cboden');
$loader->add('React',__DIR__.'/../vendor/bundles');
$loader->add('Evenement',__DIR__.'/../vendor/bundles');
$loader->add('ADesigns',__DIR__.'/../vendor/bundles');
// intl
if (!function_exists('intl_get_error_code')) {
    require_once __DIR__.'/../vendor/symfony/symfony/src/Symfony/Component/Locale/Resources/stubs/functions.php';

    $loader->add('', __DIR__.'/../vendor/symfony/symfony/src/Symfony/Component/Locale/Resources/stubs');
}
AnnotationRegistry::registerLoader([$loader, 'loadClass']);

return $loader;
