FROM centos:7
MAINTAINER Sean Cline <smcline06@gmail.com>

## --- SUPPORTING FILES ---
COPY cacti /cacti_install

## --- CACTI ---
RUN \
    rpm --rebuilddb && yum clean all && \
    yum update -y && \
    yum install -y \
        rrdtool net-snmp net-snmp-utils cronie php-ldap php-devel mysql php \
        ntp bison php-cli php-mysql php-common php-mbstring php-snmp curl \
        php-gd openssl openldap mod_ssl php-pear net-snmp-libs php-pdo \
        autoconf automake gcc gzip help2man libtool make net-snmp-devel \
        m4 libmysqlclient-devel libmysqlclient openssl-devel dos2unix wget \
        sendmail mariadb-devel && \

## --- CLEANUP ---
    yum clean all

## --- CRON ---
# Fix cron issues - https://github.com/CentOS/CentOS-Dockerfiles/issues/31
RUN sed -i '/session required pam_loginuid.so/d' /etc/pam.d/crond

## --- SERVICE CONFIGS ---
COPY configs /template_configs

## --- SETTINGS/EXTRAS ---
COPY plugins /cacti_install/plugins
COPY templates /templates
COPY settings /settings

## --- SCRIPTS ---
COPY upgrade.sh /upgrade.sh
RUN chmod +x /upgrade.sh

COPY restore.sh /restore.sh
RUN chmod +x /restore.sh

COPY backup.sh /backup.sh
RUN chmod +x /backup.sh

RUN mkdir /backups
RUN mkdir /cacti
RUN mkdir /spine

## -- MISC SETUP --
RUN echo "ServerName localhost" > /etc/httpd/conf.d/fqdn.conf

## --- ENV ---
ENV \
    DB_NAME=cacti \
    DB_USER=cactiuser \
    DB_PASS=cactipassword \
    DB_HOST=localhost \
    DB_PORT=3306 \
    RDB_NAME=cacti \
    RDB_USER=cactiuser \
    RDB_PASS=cactipassword \
    RDB_HOST=localhost \
    RDB_PORT=3306 \
    BACKUP_RETENTION=7 \
    BACKUP_TIME=0 \
    SNMP_COMMUNITY=public \
    REMOTE_POLLER=0 \
    INITIALIZE_DB=0 \
    INITIALIZE_INFLUX=0 \
    TZ=UTC

## --- Start ---
COPY start.sh /start.sh
CMD ["/start.sh"]

EXPOSE 80 443
