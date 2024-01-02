# README # (v06/07/23)

## How do I get set up? ## 
* Summary of set up
* Configuration

### Web server ###
Install under web server as applicable
- See setup/mtgc.conf for sample Apache configuration file
- check and set all paths
- Sample config restricts bulk and setup folders to localhost access
- php-fpm config in /etc/php-fpm.d/www.conf


 php_admin_value[session.auto_start] = 0
 php_admin_value[session.use_cookies] = 1
 php_admin_value[session.use_only_cookies] = 1
 php_admin_value[session.cookie_httponly] = 1
 php_admin_value[session.cookie_secure] = 1
 php_admin_value[session.cookie_samesite] = Strict


### Dependencies ###
#### PHP ####
- Requires PHP 8.2
- requires php-gd for deck photo manipulation
- set upload_max_size and post_max to 25M

#### MySQL ####
- Tested with version 8+
- Indexes are vital for performance
- cards_scry table should be InnoDB

#### JsonMachine ####
Used for bulk script parsing
Composer install (see below section)

#### Cloudflare Turnstile ####
Used on login page to provide "captcha" style validation before login
Needs to be setup on Cloudflare account to obtain valid keys
Uses https://packagist.org/packages/andkab/php-turnstile
If tier is "dev", dummy keys are used

To setup Turnstile
- Composer install (see below section)
- in Ini file (see section below on Ini file):
    - enable/disable (anything other than Turnstile = "enabled" will disable)
    - if enabled, you must set keys

#### JQuery and IAS ####
Works with JQuery 3.7.1:  <script src="/js/jquery.js"></script> where required
IAS (ued in index.php) installed locally in /js folder, pulled down from CDN https://unpkg.com/@webcreate/infinite-ajax-scroll@3/dist/infinite-ajax-scroll.min.js

#### FreecurrencyAPI ####
Composer install (see below section)
Obtain API key from https://app.freecurrencyapi.com/. Free key has 10 per minute limit and 5,000 per month.
Note, if [fx][FreecurrencyAPI] in ini file is empty, FX is disabled.

The rate is updated at most every 60 minutes on demand, with the target currency set in ini file.

#### Composer apps ####
- run composer from mtg directory on server
-- composer require andkab/php-turnstile
-- composer require everapi/freecurrencyapi-php:dev-master
-- composer require halaxa/json-machine
To install as apache: ```sudo -Hu apache composer require halaxa/json-machine```

### File locations ###
- Create a new folder at /opt/mtg
- Copy the ini file (see next section) and the shell scripts to call bulk scripts to /opt/mtg (samples are in setup folder), altering as needed so they point to where the bulk scripts are
- Make sure the logfile location specified in the ini file exists and is web-server-writable
- Make sure the ImgLocation folder exists and is web-server-writable, and is presented to be served as 'cardimg' folder in Apache
- Make sure there is a json folder in the Imglocation folder

### Ini file ###
- The application expects an ini file located at: /opt/mtg/mtg_new.ini
- Apache must be able to read AND write this file
- It must include:

Ini file content:

    [general]
    tier = "dev"                            //either 'dev' or 'prod'
    ImgLocation = "/mnt/data/cardimg/"      //ensure web server can write here
    Logfile = "/var/log/mtg/mtgapp.log"     //ensure web server can write here
    Loglevel = 3                            //see admin pages
    Timezone = "Australia/Brisbane"
    Locale = "en_US" 
    Copyright = "Name - 2023"
    URL = "https://www.mtgcollection.info"

    [database]
    DBServer = "********"
    DBUser = "********"
    DBPass = "********"
    DBName = "********"

    [security]
    ServerEmail = "emp06MTG@mtgcollection.net"
    AdminEmail = "simon@simonandkate.net"
    AdminIP = ""
    Badloginlimit = x
    Turnstile = "enabled"
    Turnstile_site_key = "xxxxxx"
    Turnstile_secret_key = "xxxxxx"

    [fx]
    FreecurrencyAPI = "API_KEY"             // If empty, FX is disabled
    FreecurrencyURL = "https://api.freecurrencyapi.com/v1/latest?apikey="
    TargetCurrency = "aud"

- Check all variables, remove all comments
- Lines need to be fully left-aligned
- If Turnstile is enabled, valid keys must be included

NOTE: 
If AdminIP is empty, then an admin user can access admin pages from any IP address.
If AdminIP contains an IP address, then an admin user can access admin pages from
that IP address only.

### MySQL ###

- Database structure is noted in setup/mtg_new.sql
- You will also need:

In `admin` (administration) table:

    INSERT INTO `admin` (`key`, `usemin`, `tier`, `mtce`) VALUES
    (1, 0, 'dev', 0);

Note: Set dev or prod as appropriate (sets header colour for identification)

For groups to work:

    INSERT INTO `groups` (`groupnumber`, `groupname`, `owner`) VALUES
    (1, 'Masters', 1);

Edit my.cnf and set as follows:
(first line is to remove GROUP BY, check existing server config and remove that, don't copy the line):

    [mysqld]
    sql_mode = STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
    innodb_buffer_pool_size = 2G 

Note 2G sizing is based on 4G or more server RAM.

### PHP session.name ###
Set the same unique session.name on all pages which call session.start

### Initial user ###

Run command line:
- php initial.php username password from webserver's console in the setup folder
- note the username and hashed password which are echoed back to the console, and write into the database for initial user
- copy collectionTemplate database table to {usernumber}collection, e.g. 1collection for initial user

### Cron jobs ###

Setup cron job to run bulk files from /opt/mtg (run as root) and FX update script. Note the sets.sh file ensures that Apache has write access to the cardimg folder. 
Adjust folder and user to suit.

### PAGE LOAD SEQUENCE ###

1. Load ini.php
2. Ini.php sets:
    - class autoloader
    - reads in the ini file
    - checks the logfile is accessible and writable
    - establishes a mysqli database connection ($db)
    - sets the function for handling errors (mtg_error)
    - ... and exceptions (mtg_exception)
    - sets the writelog function (to be rewritten)
    - sets several arrays and variables to allow for changes to cards and types,
        which would otherwise need to be hard-coded into pages
3. Read in all functions from functions.php
4. Read in page variable set in secpagesetup.php
    - css version
    - check the user is logged in
    - get the user name
    - get user email
    - get the user's collection table
    - check if the site is in maintenance mode, and if it is: trigger a message and logout
5. Load page and framework (header, page content, overlays, menu, footer)
