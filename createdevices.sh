#!/bin/bash

cd /cacti

while IFS="," read devname ipaddr comstring
do
    php cli/add_device.php --description=$devname --ip=$ipaddr --community=$comstring --template=8 --version=2 --site=0;
done < /cacti/$1
