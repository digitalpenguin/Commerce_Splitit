<?php

namespace DigitalPenguin\Commerce_Splitit\Gateways;

use Commerce;
use comOrder;
use comPaymentMethod;
use comTransaction;
use DigitalPenguin\Commerce_Splitit\API\SplititClient;
use modmore\Commerce\Admin\Widgets\Form\Field;
use modmore\Commerce\Admin\Widgets\Form\PasswordField;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Interfaces\GatewayInterface;
use DigitalPenguin\Commerce_Splitit\Gateways\Transactions\Order;


class Splitit implements GatewayInterface {
    /** @var Commerce */
    protected $commerce;
    protected $adapter;

    /** @var comPaymentMethod */
    protected $method;

    protected $billingAddress;
    protected $planData;
    protected $consumerData;

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

        // Get a token from the Splitit API to render with the card form
        $token = $this->getToken($order);

        return $this->commerce->view()->render('frontend/gateways/splitit.twig', [
            'js_url'            =>  $jsUrl,
            'token'             =>  $token,
            'method'            =>  $this->method->get('id'),
            'billing_address'   =>  $this->billingAddress,
            'plan_data'         =>  $this->planData,
            'consumer_data'     =>  $this->consumerData
        ]);
    }

    /**
     * @param $order
     * @return array|false
     */
    protected function getToken($order) {
        // First get a session id by authenticating with the Splitit API
        $sessionId = $this->authenticate();

        // Now initiate the installment plan details to retrieve the token
        return $this->initiateInstallmentPlanRequest($sessionId,$order);
    }

    /**
     * @return false|mixed
     */
    protected function authenticate() {
        $loginClient = new SplititClient($this->commerce->isTestMode());
        try{
            $response = $loginClient->request('/api/Login?format=json',[
                'UserName'  =>  $this->method->getProperty('apiUsername'),
                'Password'  =>  $this->method->getProperty('apiPassword'),
            ]);
            //$this->adapter->log(MODX_LOG_LEVEL_ERROR,print_r($response->getData()));
            $data = $response->getData();

        } catch(\Exception $e){
            $this->adapter->log(MODX_LOG_LEVEL_ERROR,'Error authenticating with Splitit: '.$e->getMessage());
            return false;
        }

        if(!$data) return false;

        // Save Splitit SessionId value to $_SESSION
        $_SESSION['commerce_splitit']['session_id'] = $data['SessionId'];

        //$this->adapter->log(MODX_LOG_LEVEL_ERROR,$data['SessionId']);
        return $data['SessionId'];
    }

    /**
     * @param $sessionId
     * @param $order
     * @return array|false
     */
    protected function initiateInstallmentPlanRequest($sessionId,$order) {
        //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($order->toArray(),true));

        $client = new SplititClient($this->commerce->isTestMode());

        $this->planData = [
            'Amount'    =>  [
                'Value'         =>  $order->get('total') / 100,
                'CurrencyCode'  =>  $order->get('currency')
            ],
            'RefOrderNumber'    =>  $order->get('id'),
            'AutoCapture'       =>  true
        ];

        $address = $order->getAddress('billing');
        if(!$address) {
            $address = $order->getAddress('shipping');
        }

        $this->billingAddress = [
            'AddressLine'   =>  $address->get('address1'),
            'AddressLine2'  =>  $address->get('address2'),
            'City'          =>  $address->get('city'),
            'State'         =>  $address->get('state'),
            'Country'       =>  $address->get('country'),
            'Zip'           =>  $address->get('zip')
        ];


        $this->consumerData = [
            'FullName'      =>  $address->get('fullname'),
            'Email'         =>  $address->get('email'),
            'PhoneNumber'   =>  $address->get('phone'),
            'CultureName'   =>  $this->adapter->getOption('cultureKey')
        ];

        try{
            $response = $client->request('/api/InstallmentPlan/Initiate?format=json',[
                'RequestHeader' => [
                    'SessionId' =>  $sessionId,
                    'ApiKey'    =>  !$this->commerce->isTestMode() ? $this->method->getProperty('productionApiKey') : $this->method->getProperty('sandboxApiKey'),
                ],
                'PlanData'          =>  $this->planData,
                'BillingAddress'    =>  $this->billingAddress,
                'ConsumerData'      =>  $this->consumerData
            ]);
            $data = $response->getData();

            //Save installment plan number to session
            $_SESSION['commerce_splitit']['installment_plan_number'] = $data['InstallmentPlan']['InstallmentPlanNumber'];

            //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($data,true));
        } catch(\Exception $e){
            $this->adapter->log(MODX_LOG_LEVEL_ERROR,'Error initiating installment plan with Splitit: '.$e->getMessage());
            return false;
        }

        return $data['PublicToken'];
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
        $data['splitit_data'] = json_decode($data['splitit_data'],true);

        $order = $transaction->getOrder();

        // Even though to reach this point the order should have been successful,
        // we're not going to trust the front-end data and verify the payment with the API directly.
        $client = new SplititClient($this->commerce->isTestMode());
        $transactionValue = $this->adapter->lexicon('commerce_splitit.payment_not_verified');
        $isPaid = false;

        $response = $client->request('/api/InstallmentPlan/Get/VerifyPayment?format=json',[
            'RequestHeader' => [
                'SessionId' =>  $_SESSION['commerce_splitit']['session_id'],
            ],
            'InstallmentPlanNumber' => $_SESSION['commerce_splitit']['installment_plan_number']
        ]);

        if($response->isSuccess()) {
            $verifyData = $response->getData();
            if ($verifyData['IsPaid']) {
                $isPaid = true;
                $transactionValue = $this->adapter->lexicon('commerce_splitit.payment_verified');
            }
            $transaction->setProperty('payment_verified', $transactionValue);
            $transaction->setProperty('is_paid', $isPaid);
            $transaction->save();
        }

        return new Order($order,$isPaid,$data,$response);
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
        //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($transaction->toArray(),true));
        $order = $transaction->getOrder();
        if(!empty($transaction->getProperty('is_paid'))) {
            return new Order($order,true, $data);
        }

        return new Order($order,false, $data);
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
            'description' => 'Enter your Splitit API username',
            'value' => $method->getProperty('apiUsername'),
        ]);

        $fields[] = new PasswordField($this->commerce, [
            'name' => 'properties[apiPassword]',
            'label' => 'API Password',
            'description' => 'Enter your Splitit API password',
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