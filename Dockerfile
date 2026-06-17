FROM php:8.2-cli

RUN apt-get update && apt-get install -y unzip && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first (layer cache)
COPY composer.json ./
RUN composer install --no-scripts --no-autoloader --no-interaction

# Copy source and generate optimised autoloader
COPY . .
RUN composer dump-autoload --optimize

CMD ["./vendor/bin/phpunit", "--colors=always"]
