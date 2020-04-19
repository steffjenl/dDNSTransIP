**Requirements**
* PHP 7.2
* GIT
* Composer
* Cronjob of Windows Task Scheduler

**Installation on Linux**
* git clone https://github.com/steffjenl/dDNSTransIP.git
* composer install
* cp .example.env .env
* Edit .env file with your credentials and domain information
* Create an cronjob to check every 30 minutes is the ip address is changed.
    * crontab -e
    * */30 * * * * php -q /home/username/dDNSTransIP/dddnstransip.php
