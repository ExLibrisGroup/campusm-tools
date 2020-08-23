# Moodle Plugins for integrating with Ex Libris campusM

### Ex Libris campusM - [http://campusm.com](hrrp://campusm.com)

### Moodle - [http://moodle.org](http://moodle.org)

Tested with versions 3.5 through 3.9 of Moodle.

## There are two plugins, Web Services and Alerts

### Web Services

This is required for the campusM - Moodle integration to work.

#### Installation

Unzip the plugin and put it in the local folder. If the plugin is in the correct place the version.php file will be at [Moodle document root]/local/ombiel_webservices/version.php.

##### Install and Configure the plugin


1. Log in to Moodle as admin.
2. Go to Site administration > Notifications and install the plugin.

##### Enable Web Services
1. Go to the web service overview page (Site administration > Plugins > Web services > Overview).
2. Follow the 'Enable web services' link.
3. Tick the  'Enable web services' box and save changes.
4. Go to the web service overview page (Site administration > Plugins > Web services > Overview).
5. Follow the 'Enable protocols' link.
6. Enable SOAP protocol and save changes.

##### Check and Set the User Permissions
1. Go to Site administration > Users > Define Roles.
2. Edit 'Authenticated User'.
3. Set 'Create a web service token' to 'Allow'
4. Set 'Web service: SOAP protocol' to 'Allow'

##### Testing the Web service

1. Get the token by visiting [Moodle document root]/login/token.php?username=<username>&password=<password>&service=campusm 
2. If you have sucessfully retrieved a token check that you get a WSDL (XML document) at [Moodle document root]/webservice/soap/server.php?wsdl=1&wstoken=<token>

### Alerts

This plugin allows Moodle to push messages as campusM alerts.

#### Installation


Unzip the plugin and put it in the local folder. If the plugin is in the correct place the version.php file will be at [Moodle document root]/local/ombiel_webservices/version.php.

##### Install and Configure the Plugin
1. Log in to Moodle as admin.
2. Go to Site administration > Notifications and install the plugin.
3. Complete the plugin settings form. The correct settings can be obtained from ExLibris.
4. Go to Site administration > Development > Purge all caches and click on the Purge all caches button.
5. Go to Site administration > Plugins > Message outputs > Default message outputs and setup the default settings.

N.B. If these settings need changing they can be found at Site administration > Plugins > Message outputs.
