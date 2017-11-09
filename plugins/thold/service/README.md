# thold daemon installation

## Introduction

The thold daemon was designed to improve Cacti's scalability by allowing the thold check process to take place out of band.  By doing so, the time Cacti spends checking thresholds can be reduced significantly.  This service folder includes initilization scripts for both systemd and initd based systems.  To install the thold daemon as a service, follow the instructions below.

## SystemD Based Systems

Follow the steps below to install the thold daemon on a SystemD system.

* Verify the location of the thold_daemon.php in the systemd subfolder of the location of this README.md file.
* Verify that the path_thold is accurate.  If it is not accurate, please update it to the correct location.
* Edit the thold_daemon.service file and update the path where you plan to install the thold_daemon control script.  Examples would include /usr/sbin and /sbin
* Ensure that the thold_daemon script is marked executable
* Copy the 'thold_daemon.service' file to /etc/systemd/service directory
* Run the following command 'systemctl enable thold_daemon.service'
* Start the thold daemon using the following command 'systemctl start thold_daemon'
