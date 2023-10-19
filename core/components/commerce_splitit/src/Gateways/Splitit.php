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
    protected $shopperData;

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
    public function view(comOrder $order): string
    {
        // Load sandbox version if Commerce is in test mode.
        $mode = $this->commerce->isTestMode() ? 'sandbox' : 'production';
        $jsUrl = 'https://flex-form.' . $mode . '.splitit.com/flex-form.js?v=' . round(microtime(true) / 100);
        $this->commerce->modx->regClientStartupScript($jsUrl);

        // Get a token from the Splitit API to render with the card form
        $installmentPlanNumber = $this->getToken($order);
        return $this->commerce->view()->render('frontend/gateways/splitit.twig', [
            'ipn'               =>  $installmentPlanNumber,
            'method'            =>  $this->method->get('id'),
            'billing_address'   =>  $this->billingAddress,
            'plan_data'         =>  $this->planData,
            'shopper_data'     =>  $this->shopperData
        ]);
    }

    /**
     * @param $order
     * @return array|false
     */
    protected function getToken($order) {
        // First get a session id by authenticating with the Splitit API
        $accessToken = $this->authenticate();

        // Now initiate the installment plan details to retrieve the token
        return $this->initiateInstallmentPlanRequest($accessToken, $order);
    }

    /**
     * @return false|mixed
     */
    protected function authenticate() {
        $loginClient = new SplititClient($this->commerce->isTestMode(), true);

        try {
            $response = $loginClient->request('connect/token', [
                'client_id' => $this->method->getProperty('apiUsername'),
                'client_secret' => $this->method->getProperty('apiPassword'),
                'scope' => 'api.v3',
                'grant_type' => 'client_credentials',
            ]);
//            $this->commerce->modx->log(MODX_LOG_LEVEL_ERROR, print_r($response, true));
            $data = $response->getData();

        }
        catch(\Exception $e){
            $this->adapter->log(MODX_LOG_LEVEL_ERROR, 'Error authenticating with Splitit: ' . $e->getMessage());
            return false;
        }

        if (!$data) {
            $this->adapter->log(MODX_LOG_LEVEL_ERROR, 'Error authenticating with Splitit: response data empty.');
            return false;
        }

        // Save Splitit SessionId value to $_SESSION
        $_SESSION['commerce_splitit']['access_token'] = $data['access_token'];

        return $data['access_token'];
    }

    /**
     * @param $sessionId
     * @param $order
     * @return array|false
     */
    protected function initiateInstallmentPlanRequest($accessToken, $order) {
        $total = $order->get('total') / 100;

        // Splitit will only accept "." as a decimal place so ensure locale settings have not done a switcheroo.
        $total = str_replace(',','.',(string)$total);

        $this->planData = [
            'TotalAmount' =>  $total,
            'RefOrderNumber' =>  $order->get('id'),
            'Currency' =>  $order->get('currency')
        ];

        // If "commerce_splitit.num_of_installments" system setting has been specified, set that here.
        $numOfInstallments = $this->commerce->adapter->getOption('commerce_splitit.num_of_installments');
        if (!empty($numOfInstallments)) {
            $this->planData = [
                'NumberOfInstallments' => $numOfInstallments
            ];
        }

        // Sets first amount to be percentage of the total. This is specified by the system setting.
        $firstInstallmentPercentage = $this->commerce->adapter->getOption('commerce_splitit.first_installment_percentage');
        if (!empty($firstInstallmentPercentage)) {
            // Formula for percentage
            $firstAmount = ($firstInstallmentPercentage / 100) * $total;
            // Round if more than two decimal places
            $firstAmount = round($firstAmount, 2);

            // This only gets applied if system setting "commerce_splitit.first_installment_percentage" has a value.
            $this->planData['FirstInstallmentAmount'] = [
                'Value' =>  $firstAmount,
                'CurrencyCode' => $order->get('currency')
            ];
        }

        $address = $order->getAddress('billing');
        if (!$address) {
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

        $this->shopperData = [
            'FullName'      =>  $address->get('fullname'),
            'Email'         =>  $address->get('email'),
            'PhoneNumber'   =>  $address->get('phone'),
            'CultureName'   =>  'en-US',//$this->adapter->getOption('cultureKey')
        ];

        $client = new SplititClient($this->commerce->isTestMode(), false, $accessToken);

        try {
            $response = $client->request('api/installmentplans/initiate', [
                'AutoCapture' =>  true,
                'PlanData' => $this->planData,
                'BillingAddress' => $this->billingAddress,
                'Shopper' => $this->shopperData,
            ]);
            $data = $response->getData();
//            $this->commerce->modx->log(MODX_LOG_LEVEL_ERROR, print_r($response,true));

            // Save installment plan number to session
            $_SESSION['commerce_splitit']['installment_plan_number'] = $data['InstallmentPlanNumber'];
        }
        catch (\Exception $e) {
            $this->adapter->log(MODX_LOG_LEVEL_ERROR, 'Error initiating installment plan with Splitit: ' . $e->getMessage());
            return false;
        }

        return $data['InstallmentPlanNumber'];
    }

    /**
     * Handle the payment submit, returning an up-to-date instance of the PaymentInterface.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return Order
     * @throws TransactionException
     */
    public function submit(comTransaction $transaction, array $data): Order
    {
        $data['splitit_data'] = json_decode($data['splitit_data'],true);

        $order = $transaction->getOrder();

        // Even though to reach this point the order should have been successful,
        // we're not going to trust the front-end data and verify the payment with the API directly.
        $accessToken = $_SESSION['commerce_splitit']['access_token'];
        $client = new SplititClient($this->commerce->isTestMode(), false, $accessToken);
        $transactionValue = $this->adapter->lexicon('commerce_splitit.payment_not_verified');
        $isPaid = false;

        $ipn = $_SESSION['commerce_splitit']['installment_plan_number'];

        $response = $client->request("api/installmentplans/$ipn/verifyauthorization", [], 'GET');
        if ($response->isSuccess()) {
            $verifyData = $response->getData();
            if ($verifyData['IsAuthorized']) {
                $isPaid = true;
                $transactionValue = $this->adapter->lexicon('commerce_splitit.payment_verified');
            }
            $transaction->setProperty('payment_verified', $transactionValue);
            $transaction->setProperty('is_paid', $isPaid);
            $transaction->save();
        }

        return new Order($order, $isPaid, $data, $response);
    }


    /**
     * Handle the customer returning to the shop, typically only called after returning from a redirect.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return Order
     * @throws TransactionException
     */
    public function returned(comTransaction $transaction, array $data): Order
    {
        //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($transaction->toArray(),true));
        $order = $transaction->getOrder();
        if (!empty($transaction->getProperty('is_paid'))) {
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
    public function getGatewayProperties(comPaymentMethod $method): array
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

        return $fields;
    }
}