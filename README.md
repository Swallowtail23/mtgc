# README # (v29/01/2022)

## How do I get set up? ## 

* Summary of set up
* Configuration

### Web server ###
Install under web server as applicable.
- See setup/mtgc.conf for sample Apache configuration file
- Sample config restricts bulk and setup folders to localhost access

### Ini file ###
The application expects an ini file located at: /opt/mtg/mtg.ini. It must include:

    [general]
    tier = "dev"                            //either 'dev' or 'prod'
    ImgLocation = "/mnt/data/cardimg/"      //ensure web server can write here
    Logfile = "/var/log/mtg/mtgapp.log"     //ensure web server can write here
    Loglevel = 3                            //see admin pages
    Timezone = "Australia/Brisbane"
    Locale = "en_US" 

    [database]
    DBServer = "********"
    DBUser = "********"
    DBPass = "********"
    DBName = "********"

    [security]
    ServerEmail = "emp06MTG@mtgcollection.net"
    AdminEmail = "simon@simonandkate.net"
    AdminIP = ""
    Blowfish_Pre = "********"
    Blowfish_End = "********"
    Badloginlimit = x

NOTE: 
If AdminIP is empty, then an admin user can access admin pages from any IP address.
If AdminIP contains an IP address, then an admin user can access admin pages from
that IP address only.

### MySQL ###

- Database structure is noted in setup/mtg_new.sql
- You will also need:
in `admin`:

    INSERT INTO `admin` (`key`, `usemin`, `tier`, `mtce`) VALUES
    (1, 0, 'dev', 0);

Note: Set dev or prod as appropriate (sets header colour for identification)

    INSERT INTO `groups` (`groupnumber`, `groupname`, `owner`) VALUES
    (1, 'Masters', 1);

### Initial user ###

Run command line:
- php initial.php username password from webserver's console in the setup folder
- note the supplied username, salt and password, and write into the database for initial user
- copy collectionTemplate database table to {usernumber}collection, e.g. 1collection

### PAGE LOAD SEQUENCE ###

1. Load ini.php
2. Ini.php sets:
    - class autoload
    - reads in the ini file
    - checks the logfile is accessible and writable
    - establishes a MySQLi_Manager database connection ($db)
    - sets the function for handling errors (mtg_error)
    - ... and exceptions (mtg_exception)
    - sets the writelog function (to be rewritten)
3. Read in all functions from functions_new.php
4. Read in page variable set in secpagesetup.php
    - css version
    - check the user is logged in
    - get the user name
    - get user email
    - get the user's collection table
    - check if the site is in maintenance mode, and if it is: trigger a message and logout
5. Load page and framework (header, page content, overlays, menu, footer)

Version check - 1st August 2020

Changes, January 2022
- composer for json-machine (used for bulk scripts)
- install npm on the server for https://github.com/webcreate/infinite-ajax-scroll
- npm install --save @webcreate/infinite-ajax-scroll
mysql configuration changes:
- following changes on BL01 (2GB RAM)
[mysqld]
sql_mode = STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
innodb_buffer_pool_size = 1G