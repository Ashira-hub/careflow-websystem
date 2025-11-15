FROM php:8.2-cli

# Install PostgreSQL PDO driver
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy your app
COPY . /app
WORKDIR /app

CMD ["php", "-S", "0.0.0.0:3000", "-t", "."]
