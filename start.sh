#!/bin/bash

# setup database credential settings
sed -i -e "s/%DB_HOST%/${DB_HOST}/" \
       -e "s/%DB_PORT%/${DB_PORT}/" \
       -e "s/%DB_NAME%/${DB_NAME}/" \
       -e "s/%DB_USER%/${DB_USER}/" \
       -e "s/%DB_PASS%/${DB_PASS}/" \
       -e "s/%DB_PORT%/${DB_PORT}/" \
       -e "s/%RDB_HOST%/${RDB_HOST}/" \
       -e "s/%RDB_PORT%/${RDB_PORT}/" \
       -e "s/%RDB_NAME%/${RDB_NAME}/" \
       -e "s/%RDB_USER%/${RDB_USER}/" \
       -e "s/%RDB_PASS%/${RDB_PASS}/" \
       /cacti/include/config.php \
       /settings/*.sql \
       /spine/etc/spine.conf

# set server timezone
echo "$(date +%F_%R) [Note] Setting server timezone settings to '${TZ}'"
echo "date.timezone = ${TZ}" >> /etc/php.ini
rm /etc/localtime
ln -s /usr/share/zoneinfo/${TZ} /etc/localtime

# verify if initial install steps are required, if lock file does not exist run the following   
if [ ! -f /cacti/install.lock ]; then
       echo "$(date +%F_%R) [New Install] Lock file does not exist - new install."
       # wait for database to initialize - http://stackoverflow.com/questions/4922943/test-from-shell-script-if-remote-tcp-port-is-open
       while ! timeout 1 bash -c 'cat < /dev/null > /dev/tcp/${DB_HOST}/${DB_PORT}'; do sleep 3; done
       echo "$(date +%F_%R) [New Install] Database is up! - configuring DB located at ${DB_HOST}:${DB_PORT} (this can take a few minutes)."

       # if docker was told to setup the database then perform the following
       if [ ${INITIALIZE_DB} = 1 ]; then
              echo "$(date +%F_%R) [New Install] Container has been instructed to create new Database on remote system."
              # initial database and user setup
              echo "$(date +%F_%R) [New Install] CREATE DATABASE ${DB_NAME} /*\!40100 DEFAULT CHARACTER SET utf8 */;"
              mysql -h ${DB_HOST} -uroot -p${DB_ROOT_PASS} -e "CREATE DATABASE ${DB_NAME} /*\!40100 DEFAULT CHARACTER SET utf8 */;"
              # allow cacti user access to new database
              echo "$(date +%F_%R) [New Install] GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}' IDENTIFIED BY '*******';"
              mysql -h ${DB_HOST} -uroot -p${DB_ROOT_PASS} -e "GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}' IDENTIFIED BY '${DB_PASS}';"
              # allow required access to mysql timezone table
              echo "$(date +%F_%R) [New Install] GRANT SELECT ON mysql.time_zone_name TO '${DB_USER}' IDENTIFIED BY '*******';"
              mysql -h ${DB_HOST} -uroot -p${DB_ROOT_PASS} -e "GRANT SELECT ON mysql.time_zone_name TO '${DB_USER}' IDENTIFIED BY '${DB_PASS}';"   
       fi

       # fresh install db merge
       echo "$(date +%F_%R) [New Install] Merging vanilla cacti.sql file to database."
       mysql -h ${DB_HOST} -u${DB_USER} -p${DB_PASS} ${DB_NAME} < /cacti/cacti.sql

       echo "$(date +%F_%R) [New Install] Installing supporting template files."
       cp -r /templates/resource /cacti
       cp -r /templates/scripts /cacti

       # install additional settings
       for filename in /settings/*.sql; do
              echo "$(date +%F_%R) [New Install] Importing settings file $filename"
              mysql -h ${DB_HOST} -u${DB_USER} -p${DB_PASS} ${DB_NAME} < $filename
       done

       # install additional templates
       for filename in /templates/*.xml; do
              echo "$(date +%F_%R) [New Install] Installing template file $filename"
              php -q /cacti/cli/import_template.php --filename=$filename > /dev/null
       done

       # create lock file so this is not re-ran on restart
       touch /cacti/install.lock
       echo "$(date +%F_%R) [New Install] Creating lock file, db setup complete."
fi

# correcting file permissions
echo "$(date +%F_%R) [Note] Setting cacti file permissions."
chown -R apache.apache /cacti/resource/
chown -R apache.apache /cacti/cache/
chown -R apache.apache /cacti/log/
chown -R apache.apache /cacti/scripts/
chown -R apache.apache /cacti/rra/

# remote poller tasks
if [ ${REMOTE_POLLER} = 1 ]; then
       echo "$(date +%F_%R) [Remote Poller] This is slated to be a remote poller, updating cacti configs for these settings."
       sed -i -e "s/#$rdatabase/$rdatabase/" \
                 /cacti/include/config.php
       echo "$(date +%F_%R) [Remote Poller] Updating permissions in cacti directory for remote poller template."
       chown -R apache.apache /cacti
fi

# backup cron tasks
if [ ${BACKUP_TIME} -gt 0 ]; then
       
       sed -i -e "s/%DB_HOST%/${DB_HOST}/" /var/spool/cron/apache

fi

# start cron service
echo "$(date +%F_%R) [Note] Starting crond service."
/usr/sbin/crond -n &

# start snmp servics
echo "$(date +%F_%R) [Note] Starting snmpd service."
snmpd -Lf /var/log/snmpd.log &

# start web service
echo "$(date +%F_%R) [Note] Starting httpd service."
httpd -DFOREGROUND
