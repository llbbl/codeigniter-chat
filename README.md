# Codeigniter Chat

This is a basic shoutboard built using codeigniter. Originally only used XML for the backend, 
but was rewritten to illustrate different types of web services. It has been updated to use 
CodeIgniter 3.0.3.

## Installation

1. git clone git@github.com:llbbl/codeigniter-chat.git
2. Rename application/config/database-default.php to database.php
3. Modify database.php to include your MySQL connection details
4. Run the create.sql (does not include schema create)

## Configuration

* Setup the Webserver DocumentRoot to point to public folder inside the codeigniter chat folder
* Assuming you have setup the project under the domain example.local, Open a browser and goto example.local/chat
