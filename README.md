# Dintero.Checkout.WooCommerce


Settings in Extension:
When Authorized, you can choose if the Order status should be "Processing" or "On-hold". Default is "Processing". 



## What can you do from WooCommerce

Capture orders:
- Full Capture: Change the order status from "Processing" or "On-hold" --> "Complete". 
- Partial Capture: Change the order status to "On-hold", then remove the items you want from list (You cannot add items!) Change from "On-hold" --> "Complete". 
NB! Please verify in Dintero Backoffice when doing partial capture. 

Cancel orders:
- Only works when changing order from "Processing" or "On-hold" --> "Cancelled". 


Refund orders:
- Full refund: Change the order from status "Complete" --> "Refund". 
- Partial refund: Click on "Refund" button, then choose items to refund.
NB! Please verify in Dintero Backoffice when doing partial refunds. 

## Develop

### Linting

We use [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) to check the syntax of the PHP and HTML written.

The code will automatically be checked on pull requests.

#### Running lint locally
```
docker run -it --rm -v $(pwd):/app willhallonline/wordpress-phpcs:alpine --extensions=php phpcs dintero-hp/
```

To automatically fix the code, run:

```
docker run -it --rm -v $(pwd):/app willhallonline/wordpress-phpcs:alpine phpcbf --extensions=php dintero-hp/
```

Note: All errors can't be automatically fixed. 

