Option 2: The Modern Performance King — FrankenPHP + Laravel Octane ⚡If you want an incredibly fast setup that is production-ready, utilizing FrankenPHP (written in Go) alongside Laravel Octane is the state-of-the-art method. It completely removes the need for complex Nginx/PHP-FPM splits by serving your app through an all-in-one high-performance server.1. Create a DockerfilePlace this in the root of your Laravel application to orchestrate your PHP environment:dockerfileFROM dunglas/frankenphp:latest-php8.3

# Install core PHP extensions needed for Laravel
RUN install-php-extensions \
    pcntl \
    pdo_mysql \
    redis \
    intl \
    zip \
    opcache

# Copy existing application directory contents
COPY . /app

# Set the work directory to the default FrankenPHP path
WORKDIR /app

# Expose port 80 for local serving
EXPOSE 80

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80"]
Use code with caution.2. Create a compose.yaml fileDefine your app container alongside your persistent database stack:yamlservices:
  laravel.test:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - '80:80'
    volumes:
      - .:/app
    depends_on:
      - mysql
    networks:
      - laravel

  mysql:
    image: 'mysql/mysql-server:8.0'
    ports:
      - '3306:3306'
    environment:
      MYSQL_ROOT_PASSWORD: 'root'
      MYSQL_DATABASE: 'laravel_13'
    volumes:
      - 'mysql-data:/var/lib/mysql'
    networks:
      - laravel

networks:
  laravel:
    driver: bridge

volumes:
  mysql-data:
    driver: local
Use code with caution.3. Update Your .envEnsure your database parameters explicitly target the MySQL service name container rather than 127.0.0.1:envDB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_13
DB_USERNAME=root
DB_PASSWORD=root
Use code with caution.4. Run the EnvironmentBuild and kick off your modern stack seamlessly:bashdocker compose up -d --build
Use code with caution.Direct ComparisonFeatureLaravel Sail ⛵FrankenPHP + Octane ⚡Best ForFast prototyping, traditional apps, zero-config setups.API performance, enterprise scales, modern environments.Complexity🟢 Low (maintained by Laravel ecosystem).🟡 Medium (requires maintaining custom docker files).Execution MethodTraditional web server mapping (PHP-FPM equivalent).High-speed, persistent app memory execution in Go.ToolingPacked with Mailpit, Meilisearch, and custom services.Stripped down, fast container tailored precisely to your needs.