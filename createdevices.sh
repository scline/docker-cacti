#!/bin/bash

while getopts ":dgf:" opt; do
  cd /cacti
  case $opt in
    f)
      DEVLIST="$OPTARG"
      ;;
    d)
      DEVICES=true
      ;;
    g)
      GRAPHS=true
      ;;
    /?)
      echo "Invalid option: -$OPTARG" >&2
      exit 1
      ;;
  esac
done

if [[ $DEVICES = true ]]; then
  while IFS="," read devname ipaddr comstring; do
    php cli/add_device.php --description=$devname --ip=$ipaddr --community=$comstring --template=8 --version=2 --site=0;
  done < /cacti/$DEVLIST
fi

if [[ $GRAPHS = true ]]; then
  for i in $(php cli/add_graphs.php --list-hosts | awk '$3 ~ /8/ { print $1 }'); do
    php cli/add_graphs.php --host-id="$i" --graph-type=ds --graph-template-id=13 --snmp-query-id=1 --snmp-query-type-id=49 --snmp-field=ifOperStatus --snmp-value=Up;
  done
fi
