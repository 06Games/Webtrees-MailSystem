# Mail System
Sends a mail with recent changes to [Webtrees](https://github.com/fisharebest/webtrees) users.  

> *Make sure to configure the cron job as described in the module settings.*

## APIs
* `/mail-sys/get`: lists all changes in JSON format.
* `/mail-sys/html`: a preview of the mail that will be sent.
* `/mail-sys/cron`: should be called regularly, it will check if a mail should be sent.


## Translation
All language files are located in `/ressouces/lang`. You can edit them with a po editor such as [Poedit](https://poedit.net/)
