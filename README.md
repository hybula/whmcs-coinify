# Coinify WHMCS Gateway
Payment gateway for WHMCS using the new Coinify API. Read more about this API [here](https://coinify.readme.io/docs).

### Introduction
Coinify recently introduced their new API, deprecating their older API. The new API supports faster payment processing, more alt coins and a generally better API features.
There is currently no official integration, not even an SDK, therefore we decided to develop this module.

Note: This is still under development, but *should* work as expected.

### Features
- Works with Production or Sandbox environment.
- Marks invoices as paid using the new webhooks.
- Notifies admins in case there is an issue with the payment (e.g. overpayment or underpayment).
- UUIDv4 generation straight from your gateway configuration.
- Supports multiple currencies (this is passed to API automatically).
- Simple webhook URL helper.

### Requirements
- PHP 8.x (tested on 8.1.23)
- WHMCS 8.x (tested on 8.7.3)

### Installation
1. Download the latest release and unzip it in the root of your WHMCS installation.
2. Enable the gateway via System Settings > Apps & Integration > Search for "Coinify" > Click "Activate".
3. Configure the gateway by filling in an API Key and Shared Secret. As of today, Coinify Support will provide you with an API Key.
You must supply them with a Shared Secret, this is automatically generated in the gateway configuration (copy it from the input field).
4. Finally provide Coinify with your webhook URL, this is provided in the gateway configuration view.

### Contribute
Contributions are welcome in a form of a pull request (PR).

### Sponsored
This project is developed and sponsored by [Hybula B.V.](https://www.hybula.com/)
<p>
  <a href="https://www.hybula.com/">
    <img src="https://www.hybula.com/assets/hybula/logo/logo-primary.svg" height="40px">
  </a>
</p>

### License
```Apache License, Version 2.0 and the Commons Clause Restriction```
