**NOTE: Latest changes require PHP 7.1**

I always try to automate parts of my daily life if possible.

This very very simple script runs off my Raspberry Pi at home to check if my public IP address has changed. 

If yes, it will email me and I can just forward the email so my new IP address will be included in my company's
remote desktop firewall rules.

In addition, it will also update CloudFlare DNS record to point the the new IP address, so basically I have a "free"
Dynamic DNS setup here!
