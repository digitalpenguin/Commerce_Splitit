## Splitit Payment Gateway Integration for Commerce on the MODX CMS.

Development by Murray Wood at Digital Penguin.
Thanks to Julien Studer at nopixel for sponsoring the development of this module.

### New API

Splitit has recently completely changed their API and as such, older versions of this module won't work anymore. Version 2.0+ is required.

### Regarding Stripe

Splitit has Stripe as an option for the payment processor. Be aware that if you use Stripe through Splitit AND also
accept Stripe payments directly, then two Stripe accounts should be used. If a single account is used, webhook requests
can be triggered by Stripe with the incorrect transaction.

### Requirements

Commerce_Splitit requires at least MODX 2.6.5 and PHP 7.4 or higher. Commerce by modmore should be at least version 1.4. You also need to have a Splitit merchant account which provides a username and a password.

### Installation

Install via the MODX package manager. https://extras.modx.com/package/splititpaymentgatewayforcommerce

### Setup

Once installed, navigate to Commerce in the MODX manager. Select the `Configuration` tab and then click on `Modules`. Find Commerce_Splitit in the module list and click on it to make a window pop up where you can enable the module for `Test Mode`, `Live Mode`, or both. Note that while Commerce is set to test mode, Commerce_Splitit will automatically use the sandbox API. Setting Commerce to Live Mode will use Splitit's production API.

Now the module is enabled, you can click on the `Payment Methods` tab on the left. Then click `Add a Payment Method`. Select Splitit from the Gateway dropdown box and then give it a name e.g. Splitit.
Next, click on the availability tab and enable it for test or live modes and then click save.

After saving, you'll see a Splitit tab appears at the top of the window. Here you can enter your Splitit API credentials: Username, password, API key and set 3DSecure.

*Congratulations!* Splitit should now appear as a payment method a customer can use during checkout.

### Form Styling

Here's an example of the form with default styling:

![Screenshot 2024-01-25 at 15-09-17 Checkout - MODX Revolution](https://github.com/digitalpenguin/Commerce_Splitit/assets/5160368/bc3f4c15-df0c-481a-ad37-dc372dc4eaaa)

Splitit makes a list of CSS class available to style. Default values shown here:
```css
--spt-color-primary: #732c70,
--spt-color-pay-button: #000,
--spt-color-pay-button-text: #fff,
--spt-color-pay-button-disabled: #00000033,
--spt-color-border-focused: #732c70,
--spt-color-border-idle: rgba(203, 203, 203, 0.54),
--spt-color-border-error: #ff0000,
--spt-color-error: #ff0000,
--spt-color-text: #000,
--spt-color-labels: #757575,
--spt-color-main-shade: #ece8ee,
--spt-color-link: rgb(125, 166, 222)
```
In case these change in the future, here is [the link](https://developers.splitit.com/checkout-solutions/hosted-fields/#customization).

### Configuration

**Payment Method Settings**

- `API Username` - Found in the Splitit dashboard under "Splitit Integration Credentials"
- `API Password` - Found in the Splitit dashboard under "Splitit Integration Credentials"
- `Payment Terminal API Key` - Found in the Splitit dashboard under "Gateway Provider Credentials"
- `Use 3DSecure` - Check this to use 3D Secure

![Screenshot 2024-01-25 at 15-10-29 Commerce » Payment Methods MODX Revolution](https://github.com/digitalpenguin/Commerce_Splitit/assets/5160368/1b388da7-a298-49cf-a7ce-c6e05a48ece5)

**System Settings**
- `commerce_splitit.first_installment_percentage` - Value should be an integer or a float. Don't include the percentage symbol.
- `commerce_splitit.num_of_installments` - Value should be a comma-separated list of integers. Example: `2,3,4,5,6`
- `commerce_splitit.num_of_installments_default` - Value should be an integer. Sets which payment option is pre-selected when the form loads.
- `commerce_splitit.locale` - Sets the locale for Splitit which will typically affect the language that's used. e.g. `en-US` or `fr-FR` etc.

![Screenshot 2024-01-25 at 15-05-39 System Settings MODX Revolution](https://github.com/digitalpenguin/Commerce_Splitit/assets/5160368/390c53e9-1cba-44df-b289-c6e49f27c271)