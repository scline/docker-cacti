#!/bin/bash

# script to upgrade a cacti instance to latest, if you want a specific version please update the following download links
cacti_download_url=http://www.cacti.net/downloads/cacti-latest.tar.gz
spine_download_url=http://www.cacti.net/downloads/spine/cacti-spine-latest.tar.gz

# create temp workspace
echo "$(date +%F_%R) [Upgrade] Prepping workspace for restore."
rm -rf /tmp/update
mkdir /tmp/update
mkdir /tmp/update/spine
mkdir /tmp/update/cacti

# download and uncompress cacti
echo "$(date +%F_%R) [Upgrade] Downloading Cacti from $cacti_download_url"
wget -qO- $cacti_download_url | tar xzC /tmp/update/cacti

# download and uncompress spine
echo "$(date +%F_%R) [Upgrade] Downloading Spine from $spine_download_url"
wget -qO- /tmp/update $spine_download_url | tar xzC /tmp/update/spine

# cacti install
echo "$(date +%F_%R) [Upgrade] Installing new version of Cacti."
cp -Rf /tmp/update/cacti/*/* /cacti

# fixing permissions
echo "$(date +%F_%R) [Restore] Setting cacti file permissions."
chown -R apache.apache /cacti/resource/
chown -R apache.apache /cacti/cache/
chown -R apache.apache /cacti/log/
chown -R apache.apache /cacti/scripts/
chown -R apache.apache /cacti/rra/

# compile new version of spine
echo "$(date +%F_%R) [Upgrade] Compile + Installing new version of Spine."
cd /tmp/update/spine/* && \
       ./configure --prefix=/spine && make && make install && \
       chown root:root /spine/bin/spine && \
       chmod +s /spine/bin/spine

# copy templated config files, makes sed command easier
echo "$(date +%F_%R) [Upgrade] Copying config templates for Spine and Cacti."
cp -f /template_configs/config.php /cacti/include
cp -f /template_configs/spine.conf /spine/etc

# cacti settings
echo "$(date +%F_%R) [Upgrade] Updating cacti/spine settings."
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
       /spine/etc/spine.conf 

# remote poller tasks
if [ ${REMOTE_POLLER} = 1 ]; then
       echo "$(date +%F_%R) [Upgrade - Remote Poller] This is slated to be a remote poller, updating cacti configs for these settings."
       sed -i -e "s/#$rdatabase/$rdatabase/" \
                 /cacti/include/config.php
       echo "$(date +%F_%R) [Upgrade - Remote Poller] Updating permissions in cacti directory for remote poller template."
       chown -R apache.apache /cacti
fi

# attempt db upgrade via clu
echo "$(date +%F_%R) [Upgrade] Attempting to update database via CLI."
php /cacti/cli/upgrade_database.php 

# cacti settings
echo "$(date +%F_%R) [Upgrade] Cleaning temp files."
rm -rf /tmp/update

# write note in cacti.log that a upgrade is complete
echo "$(date +%F_%R) [Upgrade] Cacti upgrade complete!" >> /cacti/log/cacti.log

echo "$(date +%F_%R) [Upgrade] Upgrade complete, please log into cacti to finish."
