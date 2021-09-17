## 6.0.0 - 2021-09-17

- Compatibility with Prestashop up to 1.7.7
- Fixed email being sent before completing payment.

## 5.1.1 - 2021-03-03

- Bumped version of JS SDK.

## 5.1.0 - 2021-02-03

- Added support for PSD2 transactions.
## 5.0.9 - 2020-10-30

- Fixed and issue regarding conditions terms checkbox showing error when disabled.

## 5.0.8 - 2020-10-28

- Fixed and issue when using Prestashop JS smart cache.

## 5.0.7 - 2020-10-22

- Fixed and issue of precision with Paypal Express Checkout that may cause and error processing a payment.

## 5.0.6 - 2020-10-19

- Fixed a bug that may cause to send more than two decimals on total discount in Paypal Express Checkout

## 5.0.5 - 2020-09-14

- Fixed and issue with only virtual products in cart requesting for a shipping method

## 5.0.4 - 2020-09-07

- Updated version of PHP SDK to include is_digital param in all Express Checkout requests.

## 5.0.3 - 2020-08-24

- Fix and issue when minicart and product page or cart page custom position where both active.
- Added validation and tips in admin configuration.

## 5.0.2 - 2020-08-19

- Fixed and issue with terms and conditions checkbox in checkout.

## 5.0.1 - 2020-08-07

- Added Fraud detection.

## 5.0.0 - 2020-07-10

- Added cart info to Paypal express checkout request.
- Added logo to Amazon Express checkout overlay.
- Disable state validation only on express checkout data validation.
- Added express checkout payment option to minicart, cart, and product page.

## 4.1.1 - 2020-07-09

- Updated Sipay SDK version to fix an issue with decimals not displaying correctly.
- Added payment info to admin panel when payment method redirects back to store.

## 4.1.0 - 2020-07-07

- Changed checkout validation to be done once user clicks on PaymentWall.
- Methods that requires redirect to gateway will close the current cart as pending payment order.
- New page that will validate if the payment was succesfull, changing the order state to processing.