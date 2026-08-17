#!/bin/sh
set -e

# Wait for PostgreSQL to accept connections if needed
# (Healthchecks in compose.yaml handle this locally, but good for standalone deployments)

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ]; then
    # Run migrations only if in production or explicitly requested
    echo "Running database migrations..."
    bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

# Execute the main container process (FrankenPHP)
exec "$@"
