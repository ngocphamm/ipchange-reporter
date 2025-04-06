Check your public IP address (via default `https://icanhazip.com` but feel free to change to whatever service you want), and update a DNS record of your choice, in CloudFlare, and send an email notification, with Mailgun.

It is checked every 15 minutes, but only logged to the database (SQLite) once every hour. The cron can be changed to [whatever supported by Alpine](https://wiki.alpinelinux.org/wiki/Cron). Can also check out [this FAQ](https://wiki.alpinelinux.org/wiki/Alpine_Linux:FAQ#Why_don't_my_cron_jobs_run?).

`.env` file should look like below with the data pieces for CloudFlare and Mailgun.

```
TZ=America/New_York

DATA_FOLDER_BASEPATH=/path/to/persistent/data/folder

CLOUDFLARE_API_TOKEN=''
CLOUDFLARE_EMAIL=''
CLOUDFLARE_ZONE_ID=''
CLOUDFLARE_DOMAIN_ID=''
CLOUDFLARE_DOMAIN_NAME=''
MAILGUN_API_KEY=''
MAILGUN_DOMAIN=''
REPORT_FROM_EMAIL=''
REPORT_TO_EMAIL=''
```

Optionally, you can also have [Litestream service](https://litestream.io/guides/docker/) to replicate the SQLite database to an AWS S3 bucket of your choice. When that is needed, uncomment out the `litestream` service in `docker-compose.yml` file, and add the following to `.env` file.

```
LITESTREAM_ACCESS_KEY_ID=''
LITESTREAM_SECRET_ACCESS_KEY=''
LITESTREAM_S3_BUCKET_NAME=''
```
