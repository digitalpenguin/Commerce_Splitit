<?php

namespace DigitalPenguin\Commerce_SplitIt\Gateways;

use Commerce;
use comOrder;
use comPaymentMethod;
use comTransaction;
use modmore\Commerce\Admin\Widgets\Form\Field;
use modmore\Commerce\Admin\Widgets\Form\PasswordField;
use modmore\Commerce\Admin\Widgets\Form\TExtField;
use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Interfaces\GatewayInterface;
use DigitalPenguin\Commerce_SplitIt\Gateways\Transactions\Order;

// TODO: Build own client instead of using SDK. Otherwise may be conflicts between composer versions.
use SplititSdkClient\Configuration;
use SplititSdkClient\ObjectSerializer;
use SplititSdkClient\FlexFields;

class SplitIt implements GatewayInterface {
    /** @var Commerce */
    protected $commerce;
    protected $adapter;

    /** @var comPaymentMethod */
    protected $method;

    public function __construct(Commerce $commerce, comPaymentMethod $method)
    {
        $this->commerce = $commerce;
        $this->adapter = $commerce->adapter;
        $this->method = $method;
    }

    /**
     * Render the payment gateway for the customer; this may show issuers or a card form, for example.
     *
     * @param comOrder $order
     * @return string
     * @throws \modmore\Commerce\Exceptions\ViewException
     */
    public function view(comOrder $order)
    {
        // Load sandbox version if Commerce is in test mode.
        $mode = 'production';
        if($this->commerce->isTestMode()) {
            $mode = 'sandbox';
        }
        $cssUrl = 'https://flex-fields.' . $mode . '.splitit.com/css/splitit.flex-fields.min.css?v='.round(microtime(true)/100);
        $jsUrl = 'https://flex-fields.' . $mode . '.splitit.com/js/dist/splitit.flex-fields.sdk.js?v='.round(microtime(true)/100);

        // Inject default Splitit CSS to page header unless system setting is set to false.
        if($this->adapter->getOption('commerce_splitit.use_default_css')) {
            $this->commerce->modx->regClientCSS($cssUrl);
        }

        // Get a token from the SplitIt API to render with the card form
        $token = $this->getToken();

        return $this->commerce->view()->render('frontend/gateways/splitit.twig', [
            'js_url'    =>  $jsUrl,
            'token'     =>  $token,
            'method'    =>  $this->method->get('id')
        ]);
    }

    protected function getToken() {

        Configuration::sandbox()->setApiKey('3b2163ea-fbbf-4f21-8d63-7fbe0c2239a4');
        //Configuration::production()->setApiKey('_YOUR_PRODUCTION_API_KEY_');

        try{
            $ff = FlexFields::authenticate(Configuration::sandbox(), 'APIUser000031364', 'wO6DWp3H');
            return $ff->getPublicToken(1000, "USD");
        } catch(\Exception $e){
            $this->adapter->log(MODX_LOG_LEVEL_ERROR,'Error authenticating with Splitit: '.$e->getMessage());
            return '';
        }

    }

    /**
     * Handle the payment submit, returning an up-to-date instance of the PaymentInterface.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return Order
     * @throws TransactionException
     */
    public function submit(comTransaction $transaction, array $data)
    {
        $value = 'This was a success';

        $transaction->setProperty('required_value', $value);
        $transaction->save();

        // ManualTransaction is used by the Manual payment gateway and has an always-successful response;
        // useful for testing but not quite for actual payments.
        return new Order($value);
    }

    /**
     * Handle the customer returning to the shop, typically only called after returning from a redirect.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return Order
     * @throws TransactionException
     */
    public function returned(comTransaction $transaction, array $data)
    {
        // called when the customer is viewing the payment page after a submit(); we can access stuff in the transaction
        $value = $transaction->getProperty('required_value');

        return new Order($value);
    }

    /**
     * Define the configuration options for this particular gateway instance.
     *
     * @param comPaymentMethod $method
     * @return Field[]
     */
    public function getGatewayProperties(comPaymentMethod $method)
    {

        $fields = [];

        $fields[] = new TextField($this->commerce, [
            'name' => 'properties[apiUsername]',
            'label' => 'API Username',
            'description' => 'Enter your SplitIt API username',
            'value' => $method->getProperty('apiUsername'),
        ]);

        $fields[] = new PasswordField($this->commerce, [
            'name' => 'properties[apiPassword]',
            'label' => 'API Password',
            'description' => 'Enter your SplitIt API password',
            'value' => $method->getProperty('apiPassword'),
        ]);

        $fields[] = new PasswordField($this->commerce, [
            'name' => 'properties[sandboxApiKey]',
            'label' => 'Sandbox (Testing) API Key',
            'description' => 'Enter the API Key for testing the payment gateway.',
            'value' => $method->getProperty('sandboxApiKey'),
        ]);

        $fields[] = new PasswordField($this->commerce, [
            'name' => 'properties[productionApiKey]',
            'label' => 'Production (Live Payments) API Key',
            'description' => 'Enter the API Key for the production payment gateway.',
            'value' => $method->getProperty('productionApiKey'),
        ]);

        return $fields;
    }
}