# Change Log
#### 1.2.8 - 12/11/2019
 * Update Cacti and Spine from 1.2.6 to 1.2.8
   * [changelog 1.2.7 -> 1.2.8][CL1.2.8]
   * [changelog 1.2.6 -> 1.2.7][CL1.2.7]

#### 1.2.6a - 10/30/2019
 * Update start.sh to persist Apache Cacti configurations on restart. [#52] (https://github.com/scline/docker-cacti/issues/52)
 * Update docker-compose examples to use different type of volumes so `docker-compose down` will not affect data without the `-v` flag.

#### 1.2.6 - 09/06/2019
 * Update Cacti and Spine from 1.2.0 to 1.2.6
   * [changelog][cacti_changelog]
 * Removed 1.1.X changelog notes from README.md, this can be located in [CHANGELOG.md](https://github.com/scline/docker-cacti/blob/master/changelog.md)
 * Close Issue [#49](https://github.com/scline/docker-cacti/issues/49) - New version of Spine don't have configure file
 * Close Issue [#45](https://github.com/scline/docker-cacti/issues/45) - Directories backup and backups mixed up; thank you [shortbloke](https://github.com/shortbloke) for [PR #46](https://github.com/scline/docker-cacti/pull/46)
 * Merge [PR #47](https://github.com/scline/docker-cacti/pull/47) and [PR #48](https://github.com/scline/docker-cacti/pull/48) - Add modify PHP env; thank you [joey741019](https://github.com/joey741019)

#### 1.2.0 - 01/06/2019
 * Update Cacti and Spine from 1.1.38 to 1.2.0
    * [changelog][cacti_changelog]

 * Add sendmail to dockerfile via yum due to cacti 1.2.0 requirements
 * Created separate changlog file for future documentation cleanup
 * Update PHP variable readme to include `max_execution_time` and `memory_limit` changes for 1.2.0
 * Add and Hotfix the PHP variable `max_execution_time` for PHP_MAX_EXECUTION_TIME and `memory_limit` for PHP_MEMORY_LIMIT

#### 1.1.38 - 05/12/2018
 * Update Cacti and Spine from 1.1.37 to 1.1.38
   * [changelog 1.1.37 -> 1.1.38][CL1.1.38]
 * Merge yum run commands in dockerfile to reduce stored space.

#### 1.1.37 - 04/4/2018
 * Update Cacti and Spine from 1.1.34 to 1.1.37
   * [changelog 1.1.36 -> 1.1.37][CL1.1.37]
   * [changelog 1.1.35 -> 1.1.36][CL1.1.36]
   * [changelog 1.1.34 -> 1.1.35][CL1.1.35]
 * Close Issue [#36](https://github.com/scline/docker-cacti/issues/36) - Initialize DB fails if mysql running on non-standard port
 * Close Issue [#38](https://github.com/scline/docker-cacti/issues/38) - "httpd: Could not reliably determine the server's fully qualified domain name" httpd errors
 * Close Issue [#40](https://github.com/scline/docker-cacti/issues/40) - Remove documentation about automated backups since this is not implemented. 

#### 1.1.34 - 02/8/2018
 * Update Cacti and Spine from 1.1.31 to 1.1.34
   * [changelog 1.1.33 -> 1.1.34][CL1.1.34]
   * [changelog 1.1.32 -> 1.1.33][CL1.1.33]
   * [changelog 1.1.31 -> 1.1.32][CL1.1.32]

#### 1.1.31 - 01/18/2018
 * Update Cacti and Spine from 1.1.30 to 1.1.31
   * [changelog 1.1.30 -> 1.1.31][CL1.1.31]

#### 1.1.30 - 01/03/2018
 * Update Cacti and Spine from 1.1.28 to 1.1.30
   * [changelog 1.1.29 -> 1.1.30][CL1.1.30]
   * [changelog 1.1.28 -> 1.1.29][CL1.1.29]

#### 1.1.28u1 - 12/23/2017
 * Removed pre-installed plugins (expecting users to add there own)
 * Refactored the way Cacti is installed. This is now removed from Dockerfile and moved to start.sh
   * Allows the volume mounting of '/cacti', before this would break cacti installation

#### 1.1.28 - 11/21/2017
 * Update Cacti and Spine from 1.1.27 to 1.1.28
   * [changelog 1.1.27 -> 1.1.28][CL1.1.28]

#### 1.1.27 - 11/07/2017
 * Update Cacti and Spine from 1.1.24 to 1.1.27
   * [changelog 1.1.26 -> 1.1.27][CL1.1.27]
   * [changelog 1.1.25 -> 1.1.26][CL1.1.26]
   * [changelog 1.1.24 -> 1.1.25][CL1.1.25]

#### 1.1.24 - 09/18/2017
 * Update Cacti and Spine from 1.1.19 to 1.1.24 
   * [changelog 1.1.23 -> 1.1.24][CL1.1.24]
   * [changelog 1.1.22 -> 1.1.23][CL1.1.23]
   * [changelog 1.1.21 -> 1.1.22][CL1.1.22]
   * [changelog 1.1.20 -> 1.1.21][CL1.1.21]
   * [changelog 1.1.19 -> 1.1.20][CL1.1.20]

#### 1.1.19 - 08/21/2017
 * Update Cacti and Spine from 1.1.12 to 1.1.19 
   * [changelog 1.1.18 -> 1.1.19][CL1.1.19]
   * [changelog 1.1.17 -> 1.1.18][CL1.1.18]
   * [changelog 1.1.16 -> 1.1.17][CL1.1.17]
   * [changelog 1.1.15 -> 1.1.16][CL1.1.16]
   * [changelog 1.1.14 -> 1.1.15][CL1.1.15]
   * [changelog 1.1.13 -> 1.1.14][CL1.1.14]
   * [changelog 1.1.12 -> 1.1.13][CL1.1.13]

#### 1.1.12 - 07/05/2017
 * Update Cacti and Spine from 1.1.11 to 1.1.12 - [changelog link][CL1.1.12]
 * Update upgrade.sh script to use `wget` instead of `curl` due to URL errors.
 
#### 1.1.11 - 07/04/2017
 * Update Cacti and Spine from 1.1.10 to 1.1.11 - [changelog link][CL1.1.11]

#### 1.1.10 - 06/17/2017
 * Update Cacti and Spine from 1.1.9 to 1.1.10 - [changelog link][CL1.1.10]

#### 1.1.9 - 06/08/2017
 * Update Cacti and Spine from 1.1.5 to 1.1.9 
   * [changelog 1.1.8 -> 1.1.9][CL1.1.9]
   * [changelog 1.1.7 -> 1.1.8][CL1.1.8]
   * [changelog 1.1.6 -> 1.1.7][CL1.1.7]
   * [changelog 1.1.5 -> 1.1.6][CL1.1.6]
 * Update cacti plugins
   * thold from 1.0.2 -> 1.0.3
   * monitor from 2.0 -> 2.1
   * syslog from 2.0 -> 2.1

#### 1.1.5 - 04/27/2017
 * Update Cacti and Spine from 1.1.4 to 1.1.5 - [changelog link][CL1.1.5]

#### 1.1.4 - 04/24/2017
 * Update Cacti and Spine from 1.1.3 to 1.1.4 - [changelog link][CL1.1.4]
 * Update THOLD template with master due to function bug on cacti 1.1+

#### 1.1.3 - 04/15/2017
 * Update Cacti and Spine from 1.1.2 to 1.1.3 - [changelog link][CL1.1.3]
 * remove temp automation_api file fix since this has been solved in 1.1.3

#### 1.1.2 - 04/11/2017
 * Added 1 Minute polling template
 * Updated plugin THOLD 1.0.1 -> 1.0.2
 * Updated CereusTransporter 0.65 -> 0.66
 * Added F5, ESX, PerconaDB, and Linux host templates
##### --- 04/09/2017 ---
 * Update crontab from apache user to /etc/crontab
 * Apply https://github.com/CentOS/CentOS-Dockerfiles/issues/31 fix so cron works on Centos:7 container
##### --- 04/02/2017 ---
 * Update Cacti and Spine from 1.1.1 to 1.1.2 - [changelog link][CL1.1.2]
 * Restore from a cacti backup is now working via `restore.sh <backupfile>` command
 * Minor cleanup of `backup.sh` script
 * Upgrade cacti script created and tested using `upgrade.sh` script
 
#### 1.1.1 - 03/27/2017
 * Update Cacti and Spine from 1.1.0 to 1.1.1 - [changelog link][CL1.1.1]
 * GitHub ReadMe organization

#### 1.1.0 - 03/25/2017
 * Initial push

[CL1.2.8]: http://www.cacti.net/release_notes.php?version=1.2.8
[CL1.2.7]: http://www.cacti.net/release_notes.php?version=1.2.7
[CL1.2.6]: http://www.cacti.net/release_notes.php?version=1.2.6
[CL1.2.5]: http://www.cacti.net/release_notes.php?version=1.2.5
[CL1.2.4]: http://www.cacti.net/release_notes.php?version=1.2.4
[CL1.2.3]: http://www.cacti.net/release_notes.php?version=1.2.3
[CL1.2.2]: http://www.cacti.net/release_notes.php?version=1.2.2
[CL1.2.1]: http://www.cacti.net/release_notes.php?version=1.2.1
[CL1.2.0]: http://www.cacti.net/release_notes.php?version=1.2.0
[CL1.1.38]: https://www.cacti.net/changelog.php?version=1.1.38
[CL1.1.37]: https://www.cacti.net/changelog.php?version=1.1.37
[CL1.1.36]: https://www.cacti.net/changelog.php?version=1.1.36
[CL1.1.35]: https://www.cacti.net/changelog.php?version=1.1.35
[CL1.1.34]: https://www.cacti.net/changelog.php?version=1.1.34
[CL1.1.33]: https://www.cacti.net/changelog.php?version=1.1.33
[CL1.1.32]: https://www.cacti.net/changelog.php?version=1.1.32
[CL1.1.31]: https://www.cacti.net/changelog.php?version=1.1.31
[CL1.1.30]: https://www.cacti.net/changelog.php?version=1.1.30
[CL1.1.29]: https://www.cacti.net/changelog.php?version=1.1.29
[CL1.1.28]: https://www.cacti.net/changelog.php?version=1.1.28
[CL1.1.27]: https://www.cacti.net/changelog.php?version=1.1.27
[CL1.1.26]: https://www.cacti.net/changelog.php?version=1.1.26
[CL1.1.25]: https://www.cacti.net/changelog.php?version=1.1.25
[CL1.1.24]: https://www.cacti.net/changelog.php?version=1.1.24
[CL1.1.23]: https://www.cacti.net/changelog.php?version=1.1.23
[CL1.1.22]: https://www.cacti.net/changelog.php?version=1.1.22
[CL1.1.21]: https://www.cacti.net/changelog.php?version=1.1.21
[CL1.1.20]: https://www.cacti.net/changelog.php?version=1.1.20
[CL1.1.19]: https://www.cacti.net/changelog.php?version=1.1.19
[CL1.1.18]: https://www.cacti.net/changelog.php?version=1.1.18
[CL1.1.17]: https://www.cacti.net/changelog.php?version=1.1.17
[CL1.1.16]: https://www.cacti.net/changelog.php?version=1.1.16
[CL1.1.15]: https://www.cacti.net/changelog.php?version=1.1.15
[CL1.1.14]: https://www.cacti.net/changelog.php?version=1.1.14
[CL1.1.13]: https://www.cacti.net/changelog.php?version=1.1.13
[CL1.1.12]: https://www.cacti.net/changelog.php?version=1.1.12
[CL1.1.11]: https://www.cacti.net/changelog.php?version=1.1.11
[CL1.1.10]: https://www.cacti.net/changelog.php?version=1.1.10
[CL1.1.9]: https://www.cacti.net/changelog.php
[CL1.1.8]: https://www.cacti.net/changelog.php
[CL1.1.7]: https://www.cacti.net/changelog.php
[CL1.1.6]: https://www.cacti.net/changelog.php
[CL1.1.5]: https://www.cacti.net/changelog.php
[CL1.1.4]: https://www.cacti.net/changelog.php
[CL1.1.3]: https://www.cacti.net/changelog.php
[CL1.1.2]: https://www.cacti.net/changelog.php
[CL1.1.1]: https://www.cacti.net/changelog.php

[cacti_changelog]: https://www.cacti.net/changelog.php
[cacti_download]: http://www.cacti.net/downloads
[spine_download]: http://www.cacti.net/downloads/spine
[arch]: https://github.com/scline/docker-cacti/tree/master/docker-compose
[docker_volume_help]: https://docs.docker.com/engine/tutorials/dockervolumes
[cacti_forums]: http://forums.cacti.net
[cws]: http://cacti.net