# thold daemon installation

## Introduction

The thold daemon was designed to improve Cacti's scalability by allowing the
thold check process to take place out of band.  By doing so, the time Cacti
spends checking thresholds can be reduced significantly.  This service folder
includes a systemd unit file.  To install the thold daemon as a service, follow
the instructions below.

## SystemD Based Systems

Follow the steps below to install the thold daemon on a SystemD system.

* Verify the location of `thold_daemon.php`, which is in the root of the thold
  plugin directory ([cacti]/plugins/thold).

* You may have to change mariadb.service to mysql.service depending on your
  installed version of MariaDB or MySQL.

* Update the <CACTIDIR> placeholder to point to your Cacti base path.

* Copy the `thold_daemon.service` file to systemd directory and then reload the
  systemd daemon so that it knows the new service is available.

  ```shell
  cp thold_daemon.service /etc/systemd/system
  systemctl daemon-reload
  ```

* Edit the `thold_daemon.service` file and replace the <CACTIDIR> placeholder in
  the ExecStart path with the location of your Cacti base path.  The unit runs
  `thold_daemon.php` with `/usr/bin/php`, and has no ExecStop entry.  The unit
  file is found in the [cacti]/plugins/thold/service/systemd folder.

* Enable and start the service using either the -now parameter:

  ```shell
  systemctl enable --now thold_daemon
  ```

  or issuing two separate commands if you want to start at a later date:

  ```shell
  systemctl enable thold_daemon
  systemctl start thold_daemon
  ```

* Running on Windows?  Switch to Linux!

* System V init?  Upgrade your Linux!

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
