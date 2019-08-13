# Web2GDV #

## Installation ##
- `composer install`
- Set the DB `php bin/console doctrine:migrations:migrate` 
- Run `php bin/console gdv:crawl`
- Manually enter the following to `app_areas`:

| ID | text | gdv
|---|---|---|
| 1 | Gebäude | 1
| 2 | Inhalt (Industrie/Gewerbe) | 2
| 3 | Hausrat | 3
| 4 | Sonstiges | 99

- Set the ENVs:
    - DATABASE_URL
    - MAILER_TRANSPORT
    - MAILER_HOST
    - MAILER_USERNAME
    - MAILER_PASS
    - MAILER_PORT
    - MAILER_ENCRYPTION
    - SFTP_HOST
    - SFTP_USER
    - SFTP_PASS
    - SFTP_INBOX
    - SFTP_OUTBOX
    - SFTP_FOLDER
    - IS_TEST=false
    - MAILER_ADMIN
    - MAILER_DEFAULT
    
- ask 3C to enable Login for Server-IP
    
## Changelog ##

### v0.2 ###
- added MessageType Entity

### v0.1 ##
- Initial

## ToDo ##
- Adjust to GDV Changes from 07/2019