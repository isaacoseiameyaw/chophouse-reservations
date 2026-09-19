# Dockerfile
# -----------
# Tells Render (or any Docker-based host) how to run this PHP app.
# You don't need to understand every line - just upload this file
# alongside index.php and the host handles the rest.

# Official PHP image that comes with Apache (a web server) built in.
FROM php:8.2-apache

# Aiven requires an encrypted (SSL) connection, and PHP's default MySQL
# driver (pdo_mysql) needs to be explicitly enabled to support that.
RUN docker-php-ext-install pdo_mysql

# Copies index.php (and anything else in this folder) into the
# web server's document root, so it's what visitors see.
COPY . /var/www/html/

# Render assigns a port number at startup (via the PORT environment
# variable) that's different every time, so this rewrite has to happen
# when the container actually starts, not when it's built - this line
# runs a shell command that substitutes the real port number in place
# of Apache's default (80) right before starting the web server.
CMD sh -c "sed -i \"s/80/\${PORT:-8080}/g\" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && apache2-foreground"
