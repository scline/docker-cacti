
## Docker Cacti Architecture
-----------------------------------------------------------------------------
With the recent update to version 1+, Cacti has introduced the ability to have remote polling servers. This allows us to have one centrally located UI and information system while scaling out multiple datacenters or locations. Each instance, master or remote poller, requires its own MySQL based database. The pollers also have an addition requirement to access the Cacti master's database with read/write access.


### Single Instance - cacti_single_install.yml
This is likely the most common example to deploy a cacti instance. This is not using any external pollers and is self-contained on one server. There are two separate docker containers, one for the cacti service and another for the MySQL based database. The example docker-compose file will have cacti setup a new database during the initial installation. This takes about 5-10 minutes on first boot before the web UI would be available, after the initial setup the service is up within 15 seconds. 

![Alt text](https://github.com/scline/docker-cacti/blob/master/document_images/single_host.png?raw=true "Single Host")

*docker-compose.yml*
```
version: '3.5'
services:


  cacti:
    image: "smcline06/cacti"
    container_name: cacti
    domainname: example.com
    hostname: cacti
    ports:
      - "80:80"
      - "443:443"
    environment:
      - DB_NAME=cacti_master
      - DB_USER=cactiuser
      - DB_PASS=cactipassword
      - DB_HOST=db
      - DB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - INITIALIZE_DB=1
      - TZ=America/Los_Angeles
    volumes:
      - cacti-data:/cacti
      - cacti-spine:/spine
      - cacti-backups:/backups
    links:
      - db


  db:
    image: "mariadb:10.3"
    container_name: cacti_db
    domainname: example.com
    hostname: db
    ports:
      - "3306:3306"
    command:
      - mysqld
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --max_connections=200
      - --max_heap_table_size=128M
      - --max_allowed_packet=32M
      - --tmp_table_size=128M
      - --join_buffer_size=128M
      - --innodb_buffer_pool_size=1G
      - --innodb_doublewrite=ON
      - --innodb_flush_log_at_timeout=3
      - --innodb_read_io_threads=32
      - --innodb_write_io_threads=16
      - --innodb_buffer_pool_instances=9
      - --innodb_file_format=Barracuda
      - --innodb_large_prefix=1
      - --innodb_io_capacity=5000
      - --innodb_io_capacity_max=10000
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=America/Los_Angeles
    volumes:
      - cacti-db:/var/lib/mysql


volumes:
  cacti-db:
  cacti-data:
  cacti-spine:
  cacti-backups:
```

### Single DB, Multi Node - cacti_multi_shared.yml
This instance would most likely be used if multiple servers are in close (same network/cluster) and uptime is not an issue. One or more remote pollers hang off a beefy master-cacti instance. All cacti databases need to be named differently for this to work, also note that due to how spine + boost work the database instance will utilize a bit of ram (~1-4GB per remote poller) and settings should be tweaked in this example to reflect this. This setup would be favorable if CPU becomes a bottleneck on one or many servers. Adding remote pollers can offset the load greatly. RDD files don't appear to be stored on remote systems.

![Alt text](https://github.com/scline/docker-cacti/blob/master/document_images/single_db.png?raw=true "Single DB, Multiple Hosts")

*docker-compose.yml (Server 01)*
```
version: '3.5'
services:


  cacti-master:
    image: "smcline06/cacti"
    container_name: cacti_master
    domainname: example.com
    hostname: cactimaster
    depends_on:
      - db
    ports:
      - "80:80"
      - "443:443"
    environment:
      - DB_NAME=cacti_master
      - DB_USER=cactiuser
      - DB_PASS=cactipassword
      - DB_HOST=db
      - DB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - INITIALIZE_DB=1
      - TZ=America/Los_Angeles
    volumes:
      - cacti-master-data:/cacti
      - cacti-master-spine:/spine
      - cacti-master-backups:/backups
    links:
      - db


  db:
    image: "mariadb:10.3"
    container_name: cacti_db
    domainname: example.com
    hostname: db
    ports:
      - "3306:3306"
    command:
      - mysqld
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --max_connections=200
      - --max_heap_table_size=128M
      - --max_allowed_packet=32M
      - --tmp_table_size=128M
      - --join_buffer_size=128M
      - --innodb_buffer_pool_size=1G
      - --innodb_doublewrite=ON
      - --innodb_flush_log_at_timeout=3
      - --innodb_read_io_threads=32
      - --innodb_write_io_threads=16
      - --innodb_buffer_pool_instances=9
      - --innodb_file_format=Barracuda
      - --innodb_large_prefix=1
      - --innodb_io_capacity=5000
      - --innodb_io_capacity_max=10000
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=America/Los_Angeles
    volumes:
      - cacti-db:/var/lib/mysql


volumes:
  cacti-db:
  cacti-master-data:
  cacti-master-spine:
  cacti-master-backups:
```

*docker-compose.yml (Server 02)*
```
version: '3.5'
services:


  cacti-poller:
    image: "smcline06/cacti"
    container_name: cacti_poller
    domainname: example.com
    hostname: cactipoller
    ports:
      - "8080:80"
      - "8443:443"
    environment:
      - DB_NAME=cacti_poller
      - DB_USER=cactipolleruser
      - DB_PASS=cactipollerpassword
      - DB_HOST=<IP/HOSTNAME>
      - DB_PORT=3306
      - RDB_NAME=cacti_master
      - RDB_USER=cactiuser
      - RDB_PASS=cactipassword
      - RDB_HOST=db
      - RDB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - REMOTE_POLLER=1
      - INITIALIZE_DB=1
      - TZ=America/Los_Angeles
    volumes:
      - cacti-poller-data:/cacti
      - cacti-poller-spine:/spine
      - cacti-poller-backups:/backups


volumes:
  cacti-poller-data:
  cacti-poller-spine:
  cacti-poller-backups:
```

### Multi DB, Multi Node - cacti_multi.yml
Likely used for large deployments or where multiple locations/datacenters are at play. One master server for settings and a single window into all monitoring while pollers in remote locations gather information and feed it back home. The limiting factor will be latency or disk IO on the master database since pollers will write data directly to it when gathering SNMP/scripts. RDD files don't appear to be stored on remote systems.

![Alt text](https://github.com/scline/docker-cacti/blob/master/document_images/multi_host.png?raw=true "Multiple Hosts and DB")

*docker-compose.yml (Server 01)*
```
version: '3.5'
services:


  cacti-master:
    image: "smcline06/cacti"
    container_name: cacti_master
    domainname: example.com
    hostname: cactimaster
    depends_on:
      - db-master
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
      - TZ=America/Los_Angeles
    volumes:
      - cacti-master-data:/cacti
      - cacti-master-spine:/spine
      - cacti-master-backups:/backups
    links:
      - db-master


  db-master:
    image: "percona:5.7.14"
    container_name: cacti_master_db
    domainname: example.com
    hostname: db-master
    ports:
      - "3306:3306"
    command:
      - mysqld
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --max_connections=200
      - --max_heap_table_size=128M
      - --max_allowed_packet=32M
      - --tmp_table_size=128M
      - --join_buffer_size=128M
      - --innodb_buffer_pool_size=1G
      - --innodb_doublewrite=ON
      - --innodb_flush_log_at_timeout=3
      - --innodb_read_io_threads=32
      - --innodb_write_io_threads=16
      - --innodb_buffer_pool_instances=9
      - --innodb_file_format=Barracuda
      - --innodb_large_prefix=1
      - --innodb_io_capacity=5000
      - --innodb_io_capacity_max=10000
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=America/Los_Angeles
    volumes:
      - cacti-db-master:/var/lib/mysql


volumes:
  cacti-db-master:
  cacti-master-data:
  cacti-master-spine:
  cacti-master-backups:

```

*docker-compose.yml (Server 02)*
```
version: '3.5'
services:


  cacti-poller:
    image: "smcline06/cacti"
    container_name: cacti_poller
    domainname: example.com
    hostname: cactipoller
    depends_on:
      - db-poller
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
      - RDB_HOST=<IP/HOSTNAME>
      - RDB_PORT=3306
      - DB_ROOT_PASS=rootpassword
      - REMOTE_POLLER=1
      - INITIALIZE_DB=1
      - TZ=America/Los_Angeles
    volumes:
      - cacti-poller-data:/cacti
      - cacti-poller-spine:/spine
      - cacti-poller-backups:/backups
    links:
      - db-poller

 
  db-poller:
    image: "mariadb:10.3"
    container_name: cacti_poller+db
    domainname: example.com
    hostname: db-poller
    command:
      - mysqld
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --max_connections=200
      - --max_heap_table_size=128M
      - --max_allowed_packet=32M
      - --tmp_table_size=128M
      - --join_buffer_size=128M
      - --innodb_buffer_pool_size=1G
      - --innodb_doublewrite=ON
      - --innodb_flush_log_at_timeout=3
      - --innodb_read_io_threads=32
      - --innodb_write_io_threads=16
      - --innodb_buffer_pool_instances=9
      - --innodb_file_format=Barracuda
      - --innodb_large_prefix=1
      - --innodb_io_capacity=5000
      - --innodb_io_capacity_max=10000
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - TZ=America/Los_Angeles
    volumes:
      - cacti-db-poller:/var/lib/mysql


volumes:
  cacti-db-poller:
  cacti-poller-data:
  cacti-poller-spine:
  cacti-poller-backups:

```
