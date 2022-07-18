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

| Parameter |   Type    |                                                 Description                                                 |         Default value         |
|:---------:|:---------:|:-----------------------------------------------------------------------------------------------------------:|:-----------------------------:|
|  `users`  |  `list`   |                                          Users to send the mail to                                          |                               |
|  `trees`  |  `list`   |                                       Names of trees to be considered                                       |                               |
|  `days`   | `integer` |                                    The period (in days) to be considered                                    |              `7`              |
|  `tags`   |  `list`   |                                      Types of changes to be considered                                      |          `INDI,FAM`           |
|  `empty`  | `boolean` | Display trees without changes <br>(If the value is `False` and there is no change, the email won't be sent) |            `True`             |
|  `title`  | `string`  |                                        Customised title of the mail                                         | `Changes in the last %s days` |
|   `png`   | `boolean` |                                    The images are in PNG instead of SVG                                     |            `False`            |
