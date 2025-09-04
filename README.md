Before beginnning installation, please make sure to first make sure you have console access to your server, and you can login there. Also please fully read through this documentation before installing or reconfiguring anything.

Steps to Install:

1) Clone files to your server

2) cd cpanel-firewalld-plugin

3) sh install.sh

4) Enable the "Allow apps that have not registered with AppConfig to be run when logged in as root or a reseller with the "all" ACL in WHM" option in "Tweak Settings", or add permit_unregistered_apps_as_root=1 to the /var/cpanel/cpanel.config file, and run the /usr/local/cpanel/etc/init/startcpsrvd script

5) Check in WHM under plugins for Firewalld Manager



You will need to have the following installed:

Jq (usually installed on Alma by default)

firewalld should be installed, and running, and your SSH port added, or the SSH service enabled in firewalld. You may need to console your server to setup firewalld at first if you use a non-default SSH port.

sudo firewall-cmd --permanent --add-port=$PORTNUM/tcp
(change $PORTNUM to the port number you have set for SSH)
Then restart firewalld



If you cannot access cpanel after setting up firewalld, or installing cpanel fresh on almalinux, after cloning this repo, you can directly use the wrapper to add the cpanel service to firewalld:

sh ./3rdparty/firewalld_manager/scripts/fwctl.sh add-service public cpanel yes

Or

sudo firewall-cmd --zone=public --add-service=cpanel

Then restart firewalld

Chances are, if you just installed everything, your default zone will be called "public", when working on things, you can either use public, or you can add a new zone, and then set that as the default on the main interface. 








CSF/APF should be removed from the server as well to not conflict with firewalld after your testing is complete.


# How to use

Firewalld uses groups of rules called zones. Each zone includes a set of Services, ports, IPs, or "rich rules" that dictate how traffic will flow. The zone that is set to be default on an interface, is the zone that is active.


The firewall-cmd bash wrapper can be directly accessed via command line as well to do what you can do via the GUI
```
]# /usr/local/cpanel/3rdparty/firewalld_manager/scripts/fwctl.sh
Usage:
  /fwctl.sh status-json
  /fwctl.sh zone-info-json <zone>
  /fwctl.sh get-services-json
  /fwctl.sh list-interfaces-json
  /fwctl.sh add-service <zone> <service> [permanent: yes|no]
  /fwctl.sh remove-service <zone> <service> [permanent: yes|no]
  /fwctl.sh add-port <zone> <port/proto> [permanent: yes|no]
  /fwctl.sh remove-port <zone> <port/proto> [permanent: yes|no]
  /fwctl.sh add-source <zone> <cidr> [permanent: yes|no]
  /fwctl.sh remove-source <zone> <cidr> [permanent: yes|no]
  /fwctl.sh add-rich-rule <zone> <rule> [permanent: yes|no]
  /fwctl.sh remove-rich-rule <zone> <rule> [permanent: yes|no]
  /fwctl.sh add-interface <zone> <iface> [permanent: yes|no]
  /fwctl.sh remove-interface <zone> <iface> [permanent: yes|no]
  /fwctl.sh create-zone <zone>
  /fwctl.sh delete-zone <zone>
  /fwctl.sh set-default-zone <zone>
  /fwctl.sh panic <on|off>
  /fwctl.sh icmp-block <add|remove|list> [type]
  /fwctl.sh service <start|stop|restart|reload|enable|disable|status>
```


Adding a rich rule in the interface, you would enter the following in the box, which will open a range of ports:

```
rule family="ipv4" port port="35000-35999" protocol="tcp" accept
```

To add a rich rule via command line, encapsulate the rule in a single quote, for example opening a single port:

 /fwctl.sh add-rich-rule $ZONE_NAME 'rule family="ipv4" port port="35" protocol="tcp" accept' no



Please note there are no warranties expressed or implied. You can break things using or installing this plugin. This plugin has been tested with the latest WHM Version, 130.0.7. The manager panel is generally self explanatory, If further documentation is needed, please open an issue and we will review further.

Also, the panel is slightly slow, After clicking a button, give things a minute to fully respond and then update. Things need to go through PHP > Shell bash wrapper, making things slightly slower in response.
