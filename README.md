# Cacti v1+ Docker Container

## Cacti System
-----------------------------------------------------------------------------
Cacti is a complete network graphing solution designed to harness the power of RRDTool's data storage and graphing functionality. Cacti provides following features:

* remote and local data collectors
* network discovery
* device management automation
* graph templating
* custom data acquisition methods
* user, group and domain management
* C3 level security settings for local accounts
  * strong password hashing
  * forced regular password changes, complexity, and history 
  * account lockout support

All of this is wrapped in an intuitive, easy to use interface that makes sense for both LAN-sized installations and complex networks with thousands of devices.

Developed in the early 2000's by Ian Berry as a high school project, it has been used by thousands of companies and enthusiasts to monitor and manage their Networks and Data Centers.

More information around this opensource product can be located at the following [website][cws].

## Using this image
### Running the container
This container contains Cacti 1.0.X and is not compatible with older version of cacti. It does rely on an external MySQL database that can be already configured before initial startup or having the container itself perform the setup and initialization. If you want this container to perform these steps for you, you will need to pass the root password for mysql login or startup will fail. This container automatically incorperates Cacti Spine's multithreaded poller.

### Exposed Ports
The following ports are important and used by Cacti

| Port |     Notes     |  
|------|:-------------:|
|  80  | HTTP GUI Port |
|  443 | HTTPS GUI Port|

It is recomended to allow at least one of the above ports for access to the monitoring system. This is translated by the -p hook. For example
`docker run -p 80:80 -p 443:443`

## Docker Cacti Architecture
-----------------------------------------------------------------------------
With the recent update to version 1.0, Cacti has introduced the ability to have remote polling servers. This allows us to have one centrally located UI and information system while scaling out multiple datacenters or locations. Each instance, master or remote poller, requires its own MySQL based database. The pollers also have an addition requirement to access the Cacti master's database with read/write access.


### Single Instance
This is likely the most common example to deploy a cacti instance. This is not using any external pollers and is self-contained on one server. There are two separate docker containers, one for the cacti service and another for the MySQL based database. The example docker-compose file will have cacti setup a new database during the initial installation. This takes about 5-10 minutes on first boot before the web UI would be available, after the initial setup the service is up within 15 seconds.

![Alt text](/document_images/single_host.png?raw=true "Single Host")

*docker-compose.yml*
```
version: '2'
services:
  cacti:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      - DB_NAME=cacti
      - DB_USER=cactiuser
      - DB_PASS=cactipassword
      - DB_HOST=db
      - DB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - INITIALIZE_DB=1
      - TZ=UTC
    links:
      - db

  db:
    image: "percona:5.7.14"
    ports:
      - "3306:3306"
    command: mysqld --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --max_connections=200 --max_heap_table_size=128M --max_allowed_packet=32M --tmp_table_size=128M --join_buffer_size=128M --innodb_buffer_pool_size=1G --innodb_doublewrite=OFF --innodb_flush_log_at_timeout=3 --innodb_read_io_threads=32 --innodb_write_io_threads=16
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=UTC
```

### Single DB, Multi Node
This instance would most likely be used if multiple servers are in close (same network/cluster) and uptime is not an issue. One or more remote pollers hang off a beefy master-cacti instance. All cacti databases need to be named differently for this to work, also note that due to how spine + boost work the database instance will utalize a bit of ram (~1-4GB per remote poller) and settings should be tweaked in this example to reflect this. This setup would be favorable if CPU becomes a bottleneck on one or many servers. Adding remote pollers can offset the load greatly. RDD files dont appear to be stored on remote systems.

![Alt text](/document_images/single_db.png?raw=true "Single DB, Multiple Hosts")

*docker-compose.yml (Server 01)*
```
version: '2'
services:
  cacti-master:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      - DB_NAME=cacti_master
      - DB_USER=cactiuser
      - DB_PASS=cactipassword
      - DB_HOST=db-master
      - DB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - INITIALIZE_DB=1
      - TZ=UTC
    links:
      - db

  db:
    image: "percona:5.7.14"
    ports:
      - "3306:3306"
    command: mysqld --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --max_connections=200 --max_heap_table_size=128M --max_allowed_packet=32M --tmp_table_size=128M --join_buffer_size=128M --innodb_buffer_pool_size=1G --innodb_doublewrite=OFF --innodb_flush_log_at_timeout=3 --innodb_read_io_threads=32 --innodb_write_io_threads=16
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=UTC

```

*docker-compose.yml (Server 02)*
```
  cacti-poller:
    build: .
    ports:
      - "8080:80"
      - "8443:443"
    environment:
      - DB_NAME=cacti_poller
      - DB_USER=cactiuser
      - DB_PASS=cactipassword
      - DB_HOST=10.1.2.3
      - DB_PORT=3306
      - RDB_NAME=cacti_master
      - RDB_USER=cactiuser
      - RDB_PASS=cactipassword
      - RDB_HOST=10.1.2.3
      - RDB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - REMOTE_POLLER=1
      - INITIALIZE_DB=1
      - TZ=UTC
```

### Multi DB, Multi Node
Likely used for large deployments or where multiple locations/datacenters are at play. One master server for settings and a single window into all monitoring while pollers in remote locations gather information and feed it back home. The limiting factor will be latancy or disk IO on the master database since pollers will write data directly to it when gathering snmp/scripts. RDD files dont appear to be stored on remote systems.

![Alt text](/document_images/multi_host.png?raw=true "Multiple Hosts and DB")

*docker-compose.yml (Server 01)*
```
version: '2'
services:
  cacti-master:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      - DB_NAME=cacti_master
      - DB_USER=cactiuser
      - DB_PASS=cactipassword
      - DB_HOST=db-master
      - DB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - INITIALIZE_DB=1
      - TZ=UTC
    links:
      - db-master

  db-master:
    image: "percona:5.7.14"
    ports:
      - "3306:3306"
    command: mysqld --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --max_connections=200 --max_heap_table_size=128M --max_allowed_packet=32M --tmp_table_size=128M --join_buffer_size=128M --innodb_buffer_pool_size=1G --innodb_doublewrite=OFF --innodb_flush_log_at_timeout=3 --innodb_read_io_threads=32 --innodb_write_io_threads=16
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=UTC
```

*docker-compose.yml (Server 02)*
```
  cacti-poller:
    build: .
    ports:
      - "8080:80"
      - "8443:443"
    environment:
      - DB_NAME=cacti_poller
      - DB_USER=cactiuser
      - DB_PASS=cactipassword
      - DB_HOST=db-poller
      - DB_PORT=3306
      - RDB_NAME=cacti_master
      - RDB_USER=cactiuser
      - RDB_PASS=cactipassword
      - RDB_HOST=10.1.2.3
      - RDB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - REMOTE_POLLER=1
      - INITIALIZE_DB=1
      - TZ=UTC
    links:
      - db-poller

  db-poller:
    image: "percona:5.7.14"
    command: mysqld --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --max_connections=200 --max_heap_table_size=128M --max_allowed_packet=32M --tmp_table_size=128M --join_buffer_size=128M --innodb_buffer_pool_size=1G --innodb_doublewrite=OFF --innodb_flush_log_at_timeout=3 --innodb_read_io_threads=32 --innodb_write_io_threads=16
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=UTC

```

## Installation

### Cacti Master
tba

### Cacti Poller
tba

### Data Backups
Included is a backup script that will backup cacti (including settings/plugins), rrd files, and spine. This is accomplished by taking a complete copy of the root spine and cacti directory and performing a mysql dump of the cacti database which stores all the settings and device information. To manually perform a backup, run the following exec commands:

```
docker exec -it <docker image ID or name> ./backup.sh
```

This will store compressed backups in a tar.gz format within the cacti docker container under /backups directory. Its recomended to map this directory using volumes so data is persistant. By default it only stores 7 most recent backups and will automatically delete older ones, to change this value update `BACKUP_RETENTION` environmental variable with the number of backups you wish to store.

##### Automatic backups
The environment variable `BACKUP_TIME` can be altered to have the container automatically backup cacti. The value is in days and will kick off at midnight by default. By default this is disabled with a value of 0, if you want to further customize backup times edit `configs/crontab.apache` in this repo and rebuild the docker image.

## Customization
tba

### Device Templates
```
|--templates
 |--template.xml (files to import)
 |--resource
  |---etc
 |--scripts
  |---etc
```

### Plugins
To have plugins automatically loaded on boot, simply have the uncompressed plugin in the main `plugins` folder within the main directory. Upon build/run, the startup script will automatically install them to the appropriate directory. Please understand that you will need to enable any plugins via Cacti GUI for them to become active. 

### Settings
Settings can be passed through to cacti at initial install by placing the SQL changes in the form of filename.sql under the settings folder. start.sh will automatically merge all *.sql files during install.

#### Device/Graph Templates
tba

# Known Issues/Fixes
* ICMP monitoring is not reliable under docker, responces are all <1ms when remote servers are 200ms away. 

# ToDo
* Restore from backup
* Cacti version upgrades
* Local SNMP for monitoring
* Documentation cleanup

[cws]: http://cacti.net/