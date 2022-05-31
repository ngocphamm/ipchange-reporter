**Requires PHP 7.0**

I always try to automate parts of my daily life if possible.

This very very simple script runs off my Raspberry Pi at home to check if my public IP address has changed. 

If yes, it will email me and I can just forward the email so my new IP address will be included in my company's remote desktop firewall rules.

In addition, it will also update CloudFlare DNS record to point the the new IP address, so basically I have a "free" Dynamic DNS setup here!

Use Docker Compose like the following. The `ip.db3` file would have to `chmod 666` so the cronjob can write to it.

```
cron-ipcheck:
  image: cron-ipcheck
  container_name: ipcheck
  build:
    context: ipchange-reporter
    args:
      - TZ=America/New_York
  entrypoint: [ "bash", "-c", "cron -f" ]
  volumes:
    - /path/to/ipchange-reporter:/usr/src/ipcheck
```
