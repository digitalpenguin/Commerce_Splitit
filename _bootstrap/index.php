<?php
/* Get the core config */
$componentPath = dirname(__DIR__);
if (!file_exists($componentPath . '/config.core.php')) {
    die('ERROR: missing ' . $componentPath . '/config.core.php file defining the MODX core path.');
}

echo "<pre>";
/* Boot up MODX */
echo "Loading modX...\n";
require_once $componentPath . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$modx = new modX();
echo "Initializing manager...\n";
$modx->initialize('mgr');
$modx->getService('error', 'error.modError', '', '');
$modx->setLogTarget('HTML');


/* Namespace */
if (!createObject('modNamespace', [
    'name' => 'commerce_splitit',
    'path' => $componentPath . '/core/components/commerce_splitit/',
    'assets_path' => $componentPath . '/assets/components/commerce_splitit/',
], 'name', false)) {
    echo "Error creating namespace commerce_splitit.\n";
}

/* Path settings */
if (!createObject('modSystemSetting', [
    'key' => 'commerce_splitit.core_path',
    'value' => $componentPath . '/core/components/commerce_splitit/',
    'xtype' => 'textfield',
    'namespace' => 'commerce_splitit',
    'area' => 'Paths',
    'editedon' => time(),
], 'key', false)) {
    echo "Error creating commerce_splitit.core_path setting.\n";
}

if (!createObject('modSystemSetting', [
    'key' => 'commerce_splitit.assets_path',
    'value' => $componentPath . '/assets/components/commerce_splitit/',
    'xtype' => 'textfield',
    'namespace' => 'commerce_splitit',
    'area' => 'Paths',
    'editedon' => time(),
], 'key', false)) {
    echo "Error creating commerce_splitit.assets_path setting.\n";
}

/* Fetch assets url */
$requestUri = $_SERVER['REQUEST_URI'] ?: __DIR__ . '/_bootstrap/index.php';
$bootstrapPos = strpos($requestUri, '_bootstrap/');
$requestUri = rtrim(substr($requestUri, 0, $bootstrapPos), '/') . '/';
$assetsUrl = "{$requestUri}assets/components/commerce_splitit/";

if (!createObject('modSystemSetting', [
    'key' => 'commerce_splitit.assets_url',
    'value' => $assetsUrl,
    'xtype' => 'textfield',
    'namespace' => 'commerce_splitit',
    'area' => 'Paths',
    'editedon' => time(),
], 'key', false)) {
    echo "Error creating commerce_splitit.assets_url setting.\n";
}


$settings = include dirname(__DIR__) . '/_build/data/settings.php';
foreach ($settings as $key => $opts) {
    $val = $opts['value'];

    if (isset($opts['xtype'])) $xtype = $opts['xtype'];
    elseif (is_int($val)) $xtype = 'numberfield';
    elseif (is_bool($val)) $xtype = 'modx-combo-boolean';
    else $xtype = 'textfield';

    if (!createObject('modSystemSetting', [
        'key' => 'commerce_splitit.' . $key,
        'value' => $opts['value'],
        'xtype' => $xtype,
        'namespace' => 'commerce_splitit',
        'area' => $opts['area'],
        'editedon' => time(),
    ], 'key', false)) {
        echo "Error creating commerce_splitit." . $key . " setting.\n";
    }
}


$path = $modx->getOption('commerce.core_path', null, MODX_CORE_PATH . 'components/commerce/') . 'model/commerce/';
$params = ['mode' => $modx->getOption('commerce.mode')];
/** @var Commerce|null $commerce */
$commerce = $modx->getService('commerce', 'Commerce', $path, $params);
if (!($commerce instanceof Commerce)) {
    die("Couldn't load Commerce class");
}

// Make sure our module can be loaded. In this case we're using a composer-provided PSR4 autoloader.
include $componentPath . '/core/components/commerce_splitit/vendor/autoload.php';

// Grab the path to our namespaced files
$modulePath = $componentPath . '/core/components/commerce_splitit/src/Modules/';

// Instruct Commerce to load modules from our directory, providing the base namespace and module path twice
$commerce->loadModulesFromDirectory($modulePath, 'DigitalPenguin\\Commerce_Splitit\\Modules\\', $modulePath);

// Clear the cache
$modx->cacheManager->refresh();

echo "Done.";


/**
 * Creates an object.
 *
 * @param string $className
 * @param array $data
 * @param string $primaryField
 * @param bool $update
 * @return bool
 */
function createObject($className = '', array $data = [], $primaryField = '', $update = true)
{
    global $modx;
    /* @var xPDOObject $object */
    $object = null;

    /* Attempt to get the existing object */
    if (!empty($primaryField)) {
        if (is_array($primaryField)) {
            $condition = [];
            foreach ($primaryField as $key) {
                $condition[$key] = $data[$key];
            }
        } else {
            $condition = [$primaryField => $data[$primaryField]];
        }
        $object = $modx->getObject($className, $condition);
        if ($object instanceof $className) {
            if ($update) {
                $object->fromArray($data);
                return $object->save();
            } else {
                $condition = $modx->toJSON($condition);
                echo "Skipping {$className} {$condition}: already exists.\n";
                return true;
            }
        }
    }

    /* Create new object if it doesn't exist */
    if (!$object) {
        $object = $modx->newObject($className);
        $object->fromArray($data, '', true);
        return $object->save();
    }

    return false;
}
