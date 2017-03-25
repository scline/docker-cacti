FROM centos:7
MAINTAINER Sean Cline <smcline06@gmail.com>

## --- SUPPORTING FILES ---
COPY cacti /tmp

## --- CACTI ---
RUN \
    rpm --rebuilddb && yum clean all && \

    yum install -y \
        rrdtool net-snmp net-snmp-utils cronie php-ldap php-devel mysql php \
        ntp bison php-cli php-mysql php-common php-mbstring php-snmp \
        php-gd openssl openldap mod_ssl php-pear net-snmp-libs php-pdo && \

    tar -xf /tmp/cacti-1*.tar.gz -C /tmp && \
    mv /tmp/cacti-1*/ /cacti/

## --- SPINE ---
RUN \
    yum install -y \ 
        autoconf automake gcc gzip help2man libtool make net-snmp-devel \
        m4 libmysqlclient-devel libmysqlclient openssl-devel dos2unix wget mariadb-devel && \

    tar -xf /tmp/cacti-spine-*.tar.gz -C /tmp && \
    cd /tmp/cacti-spine-* && \
    ./configure --prefix=/spine && make && make install && \
    chown root:root /spine/bin/spine && \
    chmod +s /spine/bin/spine && \
    
## --- CLEANUP ---
    yum remove -y gcc net-snmp-devel && \
    rm -rf /tmp/*  && \
    yum clean all

## --- CRON ---
COPY configs/crontab.apache /var/spool/cron/apache
RUN chmod 0600 /var/spool/cron/*

COPY configs/spine.conf /spine/etc
COPY configs/cacti.conf /etc/httpd/conf.d
COPY configs/config.php /cacti/include

## --- SETTINGS/EXTRAS ---
COPY plugins /cacti/plugins
COPY templates /templates
COPY settings /settings

## --- SCRIPTS ---
COPY backup.sh /backup.sh
RUN chmod +x /backup.sh

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

    REMOTE_POLLER=0 \
    INITIALIZE_DB=0 \
    INITIALIZE_INFLUX=0 \
    TZ=UTC

## --- Start ---
COPY start.sh /start.sh
CMD ["/start.sh"]

EXPOSE 80 443