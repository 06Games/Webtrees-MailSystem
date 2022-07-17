# Mail System
Sends a mail with recent changes to [Webtrees](https://github.com/fisharebest/webtrees) users

To use the plugin, you just have to add a cron task (replacing `YOURWEBTREESSERVER` by the url of your Webtrees installation and `USER1,USER2,...` by a user list)
```
0 0 * * 1 wget -O - -q "YOURWEBTREESSERVER/mail-sys/send?users=USER1,USER2,..."
```

## Endpoints

* `/mail-sys/api`: a (fairly simple) api
* `/mail-sys/html`: a preview of the mail that will be sent
* `/mail-sys/send`: sends the mail

## Parameters

* `users`: A list of users (separated by a `,`) to send the mail to
* `days`: The period (in days) to be considered -- default `7`
* `title`: Customised title of the mail -- default `Changes in the last %s days`
* `tags`: Types of changes to be considered -- default `INDI,FAM`
* `png`: The images are in png instead of svg -- default `False`
