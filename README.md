# Dintero Checkout for WooCommerce

With this plugin, you can embed or redirect our checkout in your WooCommerce install, handle captures and refunds and customize to your liking.

## Contributing

1. Create a branch from the master branch
1. Perform your changes
1. Test your changes
1. Create a pull request describing your changes

## Running locally

See https://nimiq.github.io/tutorials/wordpress-woocommerce-installation for how to run locally from `~/wordpress`.

Run this command to sync the current code to the local running instance:

```
sudo cp -r . ~/wordpress/data/plugins/dintero-checkout-express && sudo chmod -R g+ ~/wordpress/data/plugins/dintero-checkout-express &&  sudo chown www-data:www-data -R ~/wordpress/data/plugins/dintero-checkout-express
```

Add this to `/etc/hosts`

```
127.0.0.1       localshop
```

Go to `localshop:8080` to see your installation.





