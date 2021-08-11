# Dintero Checkout for WooCommerce

With this plugin, you can embed or redirect our checkout in your WooCommerce install, handle captures and refunds and customize to your liking.

## Contributing

1. Create a branch from the master branch
1. Perform your changes
1. Test your changes
1. Create a pull request describing your changes

## Running locally

Run this:

```
docker-compose up --build --force-recreate -d && ./bin/docker-setup.sh
```

Add this to `/etc/hosts`

```
127.0.0.1       localshop
```

Go to `localshop:8123` to see your installation, and change the site url to localshop instead of localhost.

## Testing locally

```
docker-compose up --build --force-recreate -d && ./bin/docker-setup.sh
# Wait for the startup to finish
make test
```





