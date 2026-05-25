# syntax=docker/dockerfile:1.7
#
# Nginx production image — fronts the PHP-FPM pool and serves the SPA build
# (if mounted at /var/www/html/app). Stateless, scalable, no PHP installed.
#

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# The php-fpm image owns the application source; nginx only needs read access
# to the public webroot. The shared volume mount is wired in docker-compose.prod.yml.

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
