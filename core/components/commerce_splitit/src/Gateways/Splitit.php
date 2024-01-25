<?php

namespace DigitalPenguin\Commerce_Splitit\Gateways;

use Commerce;
use comOrder;
use comPaymentMethod;
use comTransaction;
use comTransactionLog;
use DigitalPenguin\Commerce_Splitit\API\SplititClient;
use modmore\Commerce\Adapter\AdapterInterface;
use modmore\Commerce\Admin\Widgets\Form\CheckboxField;
use modmore\Commerce\Admin\Widgets\Form\Field;
use modmore\Commerce\Admin\Widgets\Form\PasswordField;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Helpers\GatewayHelper;
use modmore\Commerce\Gateways\Interfaces\GatewayInterface;
use DigitalPenguin\Commerce_Splitit\Gateways\Transactions\Order;


class Splitit implements GatewayInterface {
    /** @var Commerce */
    protected $commerce;
    /** @var AdapterInterface  */
    protected $adapter;
    /** @var comPaymentMethod */
    protected $method;
    protected array $billingAddress;
    protected array $planData;
    protected array $shopperData;

    public function __construct(Commerce $commerce, comPaymentMethod $method)
    {
        $this->commerce = $commerce;
        $this->adapter = $commerce->adapter;
        $this->method = $method;
    }

    /**
     * Most of the work for Splitit needs to occur here in the view method.
     * 1. Authenticate with the API
     * 2. If 3DSecure is being used, create a draft transaction, so we can get a transaction id to send to Splitit
     * 3. Initiate an installment plan via the API (send all order data) and get an IPN
     * 4. Configure the hosted fields widget with the IPN and order data
     *
     * When the widget submit button is pressed, it will send everything directly to Splitit.
     * On the Commerce end (see submit() below) we then just verify the installment plan was created successfully.
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

        $placeholders = [
            'ipn' => $installmentPlanNumber,
            'method' => $this->method->get('id'),
            'billing_address' => $this->billingAddress,
            'plan_data' => $this->planData,
            'consumer_data' => $this->shopperData,
        ];
        if (!empty($this->planData['NumberOfInstallments'])) {
            $placeholders['number_of_installments'] = $this->planData['NumberOfInstallments'];
        }

        return $this->commerce->view()->render('frontend/gateways/splitit.twig', $placeholders);
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

        // Save Splitit access_token value to $_SESSION
        $_SESSION['commerce_splitit']['access_token'] = $data['access_token'];

        return $data['access_token'];
    }

    /**
     * @param string $accessToken
     * @param comOrder $order
     * @param comTransaction|null $transaction
     * @return array|false
     */
    protected function initiateInstallmentPlanRequest(string $accessToken, comOrder $order, comTransaction $transaction = null) {
        $total = $order->get('total') / 100;

        // Splitit will only accept "." as a decimal place so ensure locale settings have not done a switcheroo.
        $total = str_replace(',', '.', (string)$total);

        $this->planData = [
            'TotalAmount' =>  $total,
            'RefOrderNumber' =>  $order->get('id'),
            'TerminalId' => $this->method->getProperty('apiKey'),
            'Currency' =>  $order->get('currency'),
            'FirstInstallmentAmount' => null,
            'PurchaseMethod' => 'Ecommerce',
        ];

        // If "commerce_splitit.num_of_installments" system setting has been specified, set that here.
        $numOfInstallments = $this->commerce->adapter->getOption('commerce_splitit.num_of_installments');
        if (!empty($numOfInstallments)) {
            $this->planData['NumberOfInstallments'] = $numOfInstallments;
        }

        // Sets first amount to be percentage of the total. This is specified by the system setting.
        $firstInstallmentPercentage = $this->commerce->adapter->getOption('commerce_splitit.first_installment_percentage');
        if (!empty($firstInstallmentPercentage)) {
            // Formula for percentage
            $firstAmount = ($firstInstallmentPercentage / 100) * $total;
            // Round if more than two decimal places
            $firstAmount = round($firstAmount, 2);

            // This only gets applied if system setting "commerce_splitit.first_installment_percentage" has a value.
            $this->planData['FirstInstallmentAmount'] = $firstAmount;
        }

        $address = $order->getAddress('billing');
        if (!$address) {
            $address = $order->getAddress('shipping');
        }

        $this->billingAddress = [
            'AddressLine1'  =>  $address->get('address1'),
            'AddressLine2'  =>  $address->get('address2'),
            'City'          =>  $address->get('city'),
            'State'         =>  $address->get('state'),
            'Country'       =>  $address->get('country'),
            'Zip'           =>  $address->get('zip'),
        ];

        $this->shopperData = [
            'FullName'      =>  $address->get('fullname'),
            'Email'         =>  $address->get('email'),
            'PhoneNumber'   =>  $address->get('phone'),
            'CultureName'   =>  $this->getSplititLocale(),
        ];

        $client = new SplititClient($this->commerce->isTestMode(), false, $accessToken);

        $requestParams = [
            'AutoCapture' =>  true,
            'PlanData' => $this->planData,
            'BillingAddress' => $this->billingAddress,
            'Shopper' => $this->shopperData,
        ];

        // If using 3DSecure, create a draft transaction so that we have an id for the RedirectUrls
        if (!empty($this->method->getProperty('use3DSecure'))) {
            $transaction = $this->getDraftTransaction($order);
            $requestParams['Attempt3DSecure'] = true;
            $requestParams['RedirectUrls'] = [
                'Succeeded' => GatewayHelper::getReturnUrl($transaction),
                'Failed' => GatewayHelper::getReturnUrl($transaction),
                'Canceled' => GatewayHelper::getCancelUrl($transaction),
            ];
        }

        $this->commerce->modx->log(MODX_LOG_LEVEL_DEBUG, print_r($requestParams,true));

        try {
            $response = $client->request('api/installmentplans/initiate', $requestParams);
        }
        catch (\Exception $e) {
            $this->adapter->log(MODX_LOG_LEVEL_ERROR, 'Error initiating installment plan with Splitit: ' . $e->getMessage());
            return false;
        }

        $data = $response->getData();
        $this->commerce->modx->log(MODX_LOG_LEVEL_DEBUG, print_r($response,true));

        // Save installment plan number to session
        $_SESSION['commerce_splitit']['installment_plan_number'] = $data['InstallmentPlanNumber'];


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

        $data['splitit_data'] = json_decode($data['splitit_data'],true);

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
        $order = $transaction->getOrder();
        if (!empty($transaction->getProperty('is_paid'))) {
            return new Order($order,true, $data);
        }

        return new Order($order,false, $data);
    }

    /**
     * Handles different locale formats according to: https://stackoverflow.com/a/15060052/9811495
     * and outputs the shortcode version Splitit expects e.g. en-US
     * @return string
     */
    public function getSplititLocale(): string
    {
        // First check if splitit locale is explicitly set
        $splititLocale = $this->adapter->getOption('commerce_splitit.locale');
        if (!empty($splititLocale)) {
            return $splititLocale;
        }

        // If there's no splitit locale, check for a standard MODX locale setting and format that
        $locale = $this->adapter->getOption('locale', [], 'en-US', true);

        $separators = [
            '.', // 1. Handle 'en_US.UTF-8' format
            '@', // 2. Handle 'en_IE@euro ISO-8859-15' format
            ' '  // 3. Handle 'en_US ISO-8859-1' format
        ];
        foreach ($separators as $separator) {
            $parts = explode($separator, trim($locale));
            if (count($parts) > 1) {
                return str_replace('_', '-', $parts[0]);
            }
        }

        return str_replace('_', '-', $locale);
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

        $fields[] = new PasswordField($this->commerce, [
            'name' => 'properties[apiKey]',
            'label' => 'Payment Terminal API Key',
            'description' => "In your Splitit merchant dashboard, go to <b>Credentials -> Gateway Provider Credentials</b> to find your Payment Terminal API Key",
            'value' => $method->getProperty('apiKey'),
        ]);

        $fields[] = new CheckboxField($this->commerce, [
            'name' => 'properties[use3DSecure]',
            'label' => 'Use 3DSecure',
            'description' => "Check this if 3DSecure is enabled by your payment provider",
            'value' => $method->getProperty('use3DSecure'),
        ]);

        return $fields;
    }

    private function getDraftTransaction(comOrder $order): comTransaction
    {
        $id = (int)$order->getProperty('splitit_draft_transaction_' . $this->method->get('id'));
        /** @var comTransaction $transaction */
        $transaction = $this->adapter->getObject(comTransaction::class, [
            'id' => $id,
            'method' => $this->method->get('id'),
            'status' => comTransaction::STATUS_NEW,
        ]);

        $amount = $order->get('total_due');
        $fee = $this->method->getPrice($order, $amount);
        if (!$transaction) {
            /** @var comTransaction $transaction */
            $transaction = $this->adapter->newObject(comTransaction::class);
            $transaction->fromArray([
                'test' => $order->get('test'),
                'status' => comTransaction::STATUS_NEW,
                'method' => $this->method->get('id'),
                'currency' => $order->getCurrency()->get('alpha_code'),
                'amount' => $amount + $fee,
                'fee' => $fee,
                'created_on' => time(),
            ]);
            $order->addTransaction($transaction);
            $order->setProperty('splitit_draft_transaction_' . $this->method->get('id'), $transaction->get('id'));
            $transaction->log('Created Splitit draft transaction for 3DS', comTransactionLog::SOURCE_GATEWAY);
        } else {
            // Make sure amount + fee is set
            $transaction->set('amount', $amount + $fee);
            $transaction->set('fee', $fee);
            $transaction->save();
        }

        return $transaction;
    }
}