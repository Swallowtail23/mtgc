# README #

### How do I get set up? ###

* Summary of set up
* Configuration

Install under web server as applicable.
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

PAGE LOAD SEQUENCE:

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