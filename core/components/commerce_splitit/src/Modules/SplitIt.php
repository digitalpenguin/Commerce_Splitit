<?php
namespace DigitalPenguin\Commerce_SplitIt\Modules;

use modmore\Commerce\Admin\Configuration\About\ComposerPackages;
use modmore\Commerce\Admin\Sections\SimpleSection;
use modmore\Commerce\Events\Admin\PageEvent;
use modmore\Commerce\Events\Gateways;
use modmore\Commerce\Modules\BaseModule;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class SplitIt extends BaseModule {

    public function getName()
    {
        $this->adapter->loadLexicon('commerce_splitit:default');
        return $this->adapter->lexicon('commerce_splitit');
    }

    public function getAuthor()
    {
        return 'Murray Wood';
    }

    public function getDescription()
    {
        return $this->adapter->lexicon('commerce_splitit.description');
    }

    public function initialize(EventDispatcher $dispatcher)
    {
        // Load our lexicon
        $this->adapter->loadLexicon('commerce_splitit:default');

        // Add template path to twig
        $root = dirname(__DIR__, 2);
        $this->commerce->view()->addTemplatesPath($root . '/templates/');

        // Add composer libraries to the about section (v0.12+)
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_LOAD_ABOUT, [$this, 'addLibrariesToAbout']);

        // Register the gateway
        //$dispatcher->addListener(\Commerce::EVENT_GET_PAYMENT_GATEWAYS, [$this, 'registerGateway']);
    }

    /**
     * @param Gateways $event
     */
    public function registerGateway(Gateways $event)
    {
        // Add the GatewayName gateway, and log an error if the class couldn't be found.
        if (!$event->addGateway(SplitItGateway::class, 'SplitIt')) {
            $this->adapter->log(MODX_LOG_LEVEL_ERROR, 'Could not add the SplitIt gateway - the class was probably not found');
        }
    }

    public function getModuleConfiguration(\comModule $module)
    {
        $fields = [];

        // A more detailed description to be shown in the module configuration. Note that the module description
        // ({@see self:getDescription}) is automatically shown as well.
//        $fields[] = new DescriptionField($this->commerce, [
//            'description' => $this->adapter->lexicon('commerce_splitit.module_description'),
//        ]);

        return $fields;
    }

    public function addLibrariesToAbout(PageEvent $event)
    {
        $lockFile = dirname(__DIR__, 2) . '/composer.lock';
        if (file_exists($lockFile)) {
            $section = new SimpleSection($this->commerce);
            $section->addWidget(new ComposerPackages($this->commerce, [
                'lockFile' => $lockFile,
                'heading' => $this->adapter->lexicon('commerce.about.open_source_libraries') . ' - ' . $this->adapter->lexicon('commerce_splitit'),
                'introduction' => '', // Could add information about how libraries are used, if you'd like
            ]));

            $about = $event->getPage();
            $about->addSection($section);
        }
    }
}