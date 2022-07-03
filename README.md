# Mail System
Sends a mail with recent changes to [Webtrees](https://github.com/fisharebest/webtrees) users

To use the plugin, you just have to add a cron task (replacing YOURWEBTREESSERVER by the url of your Webtrees installation)
```
0 0 * * 1 wget -O - -q "YOURWEBTREESSERVER/mail-sys/send"
```
