**Requires PHP 7.0. Tested on PHP 8.1**

I always try to automate parts of my daily life if possible.

This very very simple script runs off my Raspberry Pi at home to check if my public IP address has changed. 

If yes, it will email me and I can just forward the email so my new IP address will be included in my company's remote desktop firewall rules.

In addition, it will also update CloudFlare DNS record to point the the new IP address, so basically I have a "free" Dynamic DNS setup here!

Use Docker Compose like the following. The `ip.db3` file would have to `chmod 666` so the cronjob can write to it.

```
ipcheck:
  image: php:cli-alpine
  container_name: ipcheck
  command: >
    sh -c "printf 'date.timezone = ${TZ}' > $${PHP_INI_DIR}/conf.d/tzone.ini &&
    crond -f -l 8"
  volumes:
    - /etc/localtime:/etc/localtime:ro # Use timezone from host
    - ./ipchange-reporter/crond/hourly:/etc/periodic/hourly/:ro # The file in this folder needs `chmod +u` inside the host
    - ./ipchange-reporter/src:/usr/src/ipcheck
  restart: unless-stopped
```

`ip.db3` file should be placed in `src/sqlite` folder.
