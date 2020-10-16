Splitit Payment Gateway Integration for Commerce on the MODX CMS.
==
Development by Murray Wood at Digital Penguin.
Thanks to Julien Studer at nopixel for sponsoring the development of this module.

Requirements
-
Commerce_Splitit requires at least MODX 2.6.5 and PHP 7.1 or higher. Commerce by modmore should be at least version 1.1.4. You also need to have a Splitit merchant account which provides a username, a password, a sandbox API key and a production API key.

Installation
-
Install via the MODX package manager. The package name is Commerce_Splitit.

Setup
-
Once installed, navigate to Commerce in the MODX manager. Select the `Configuration` tab and then click on `Modules`. Find Commerce_Splitit in the module list and click on it to make a window pop up where you can enable the module for `Test Mode`, `Live Mode`, or both. Note that while Commerce is set to test mode, Commerce_Splitit will automatically use the sandbox API. Setting Commerce to Live Mode will use Splitit's production API.

Now the module is enabled, you can click on the `Payment Methods` tab on the left. Then click `Add a Payment Method`. Select Splitit from the Gateway dropdown box and then give it a name e.g. Splitit.
Next, click on the availability tab and enable it for test or live modes and then click save. 

After saving, you'll see a Splitit tab appears at the top of the window. Here you can enter your Splitit API credentials: Username, password, sandbox API key and production API key. If for some reason you only have the one API key, enter it for both.

*Congratulations!* Splitit should now appear as a payment method a customer can use during checkout.

Form Styling
-
Splitit provides its own CSS for the payment widget and this is enabled by default. If you would like to completely restyle it from scratch, you can disable the system setting under the Commerce_Splitit namespace `use_default_css`.