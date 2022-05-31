FROM php:8.1-cli

ARG TZ

# Installing cron package
RUN apt-get update && apt-get -y install cron nano

# Set timezone for the image
RUN ln -sf /usr/share/zoneinfo/$TZ /etc/localtime
RUN printf '[PHP]\ndate.timezone = "%s"\n' "$TZ" > $PHP_INI_DIR/conf.d/tzone.ini

# Copy the crontab in a location where it will be parsed by the system
COPY crontab /etc/cron.d/crontab

# Owner can read and write into the crontab, group and others can read it
RUN chmod 0644 /etc/cron.d/crontab

# Running our crontab using the binary from the package we installed
RUN /usr/bin/crontab /etc/cron.d/crontab
