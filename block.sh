#!/bin/bash

while true; do
	mosquitto_sub -t 'RedNinja/'$1'/read' -h $2 | php block.php $1 "mosquitto_pub -t 'RedNinja/'${1}'/write' -h ${2} -l"
done;
