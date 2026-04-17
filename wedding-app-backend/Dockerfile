FROM php:8.2-cli

# Install PostgreSQL drivers and unzip for Composer
RUN apt-get update && apt-get install -y libpq-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the app
WORKDIR /app
COPY . .

# Force debug mode to reveal the error
ENV APP_DEBUG=true

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# We don't need these since artisan serve manages itself but good practice
RUN chmod -R 775 storage bootstrap/cache

# Start the Laravel development server on Render's assigned port
CMD php artisan serve --host=0.0.0.0 --port=$PORT
