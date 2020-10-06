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

class SplitIt implements GatewayInterface {
    /** @var Commerce */
    protected $commerce;

    /** @var comPaymentMethod */
    protected $method;

    public function __construct(Commerce $commerce, comPaymentMethod $method)
    {
        $this->commerce = $commerce;
        $this->method = $method;
    }

    /**
     * Render the payment gateway for the customer; this may show issuers or a card form, for example.
     *
     * @param comOrder $order
     * @return string
     */
    public function view(comOrder $order)
    {
        // Get a token from the SplitIt API to render with the card form


        // To render a template, use: $this->commerce->view()->render('frontend/gateways/foo.twig', []);
        return '<p>This may render a template that contains client-side code that needs to run for the gateway.</p>';
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
        // Validate the request
        if (!array_key_exists('required_value', $data) || empty($data['required_value'])) {
            throw new TransactionException('required_value is missing.');
        }

        $value = htmlentities($data['required_value'], ENT_QUOTES, 'UTF-8');

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
            'name' => 'properties[terminalApiKey]',
            'label' => 'Payment Terminal API Key',
            'description' => 'Enter the API Key for the payment gateway.',
            'value' => $method->getProperty('terminalApiKey'),
        ]);

        return $fields;
    }
}