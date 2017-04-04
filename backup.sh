#!/bin/bash

# Script is to backup cacti files and settings. This will store files in /backup on the container and will store the following in a compressed file
# - Cacti SQL Database
# - Cacti Plugins
# - Cacti RRD files, the graphs that make it all pretty :)

echo "$(date +%F_%R) [Backup] Prepping workspace for backup."
rm -rf /tmp/backup
mkdir /tmp/backup

# write note in cacti.log that a backup has started
echo "$(date +%F_%R) [Backup] Cacti Backup Complete!" >> /cacti/log/cacti.log

# copy files for processing
echo "$(date +%F_%R) [Backup] Cloning /cacti to temporary directory."
cp -a /cacti /tmp/backup/
echo "$(date +%F_%R) [Backup] Cloning /spine to temporary directory."
cp -a /spine /tmp/backup/

# mysqldump cacti.sql
echo "$(date +%F_%R) [Backup] Performing a mysqldump of database ${DB_NAME} from host ${DB_HOST}."
mysqldump -h ${DB_HOST} -u${DB_USER} -p${DB_PASS} ${DB_NAME} > /tmp/backup/cacti/cactibackup.sql

# compress all the things
echo "$(date +%F_%R) [Backup] Compressing backup files."
tar -zcf $(date +%Y%m%d_%H%M%S)_cactibackup.tar.gz -C /tmp/backup/ . > /dev/null 2>&1

# make sure backup directory exists, if not make it
if [ ! -d "/backup" ]; then
echo "$(date +%F_%R) [Backup] Backup directory does not exists, creating new one."
  mkdir /backup
fi

# move compressed backup to /backup directory
echo "$(date +%F_%R) [Backup] Moving backup files to /backup."
mv *_cactibackup.tar.gz /backup

# remove temporary backup workspace
echo "$(date +%F_%R) [Backup] Cleaning up temporary files."
rm -rf /tmp/backup

# only keep X number of backups, env variable BACKUP_RETENTION passed by docker
echo "$(date +%F_%R) [Backup] Removing backup files if more then ${BACKUP_RETENTION} files are present."

# adding 1 to BACKUP_RETENTION for delete statement to work correctly, then remove any additional
x=$((${BACKUP_RETENTION}+1))
rm -f $(ls -1t /backup/*_cactibackup.tar.gz | tail -n +$x)

# write note in cacti.log that a backup is complete
echo "$(date +%F_%R) [Backup] Cacti Backup Complete!" >> /cacti/log/cacti.log

echo "$(date +%F_%R) [Backup] Complete!"
