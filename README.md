# php-email-reader
Read emails whit PHP and download attachments

Download attachments from email, filtering by File type and email sender
After or download attachments move emails to another INBOX

```php
define("BASE_PATH", '[/var/www/your-site-path]');
define("LINE_RETURN", "\n");

//Define mail access configuration
define("EMAIL_SERVER", '[Server-ip or server name]');
define("EMAIL_PORT", 993);
define("EMAIL_USER", 'user@gmail.com');
define("EMAIL_PASSWORD", '123456');
define("EMAIL_MAIN_INBOX", 'INBOX');

define("EMAIL_PROCESSED_INBOX", 'INBOX.Processed'); //Where to move processed e-mails
define('EMAIL_ATTACHMENTS_PATH', FILES_PATH. '/attachments');
$MAIL_LIST_TO_CHECK = array('user1@gmail.com','user2@gmail.com');
$SUPPORTED_EXTENSIONS = array('xls', 'xlsx', 'csv', 'zip'); //Files to download from e-mail attachments

//Create new Email Object
$emailObj = new Email_reader(EMAIL_SERVER, EMAIL_PORT, EMAIL_USER, EMAIL_PASSWORD, 'imap', true, 'novalidate-cert', EMAIL_MAIN_INBOX);

//Downlaod attachment of emails and move processed mails to inbox: INBOX.Processed
$emailObj->download_mail_attachments_from($MAIL_LIST_TO_CHECK, EMAIL_ATTACHMENTS_PATH, true, $SUPPORTED_EXTENSIONS, EMAIL_PROCESSED_INBOX);
