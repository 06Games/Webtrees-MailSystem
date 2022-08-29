# Mail System
Sends a mail with recent changes to [Webtrees](https://github.com/fisharebest/webtrees) users.  

> *Make sure to configure the cron job as described in the module settings.*

## APIs

* `/mail-sys/get`: lists all the changes with a JSON format.
* `/mail-sys/html`: a preview of the mail that will be sent.
* `/mail-sys/cron`: needs to be called regularly, it will check if a mail needs to be sent.
