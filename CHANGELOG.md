## ChangeLog

--- 1.5.3 ---

* issue#463: php error - undefined index dnotes 

* issue#474: Create threshold from templates fail with multiple data template ids

* feature#469: use thold template name as filename for single export

--- 1.5.2 ---

* issue#457: Page show blank if do export function without log info exist

* issue#458: The indicator is not removed upon completion when do export function

* issue#459: Background color looks ugly for availability column. Table not aligned well.

* issue#460: Filter result for template none incorrect

--- 1.5.1 ---

* issue: Upgrade to thold 1.5 misses new column causing saves to fail

--- 1.5 ---

* issue#187: Standalone Thresholds created do not read graph data due to threshold daemon not running

* issue#225: Customize Thold Restore message

* issue#304: Warn Cacti Administrator if the Thold Daemon is Down

* issue#366: When a value is undefined, Thresholds are no longer triggering when minimum is zero

* issue#372: Thold plugin execute a script each time device state changes? v2

* issue#376: Question - Variables to use when sending a command

* issue#402: Notification Lists should support searching via RegEx

* issue#412: Can not trigger thold command cause there is a Enter Key at the end

* issue#417: zero 'lastread' value incorrectly processed by
  thold_check_threshold()

* issue#418: Errors during Thold upgrade to v1.4

* issue#422: Apply Cacti#3191 for XSS exposure (CVE-2020-7106)

* issue#429: Add new per host config thold_failure_count

* issue#430: XSS Vulnerabilities if host/graph name include script

* issue#431: SQL Syntax Determining Failed Devices

* issue#433: ERROR PHP WARNING in Plugin 'thold': Use of undefined constant LOG_VERBOSITY_MEDIUM

* issue#441: Thold 1.4 + cacti 1.2.14 - Classic Theme - Unable to Create Thresholds

* issue#442: THOLD daemon should not log errors on interruption/termination by user

* issue#443: Add support for upper + lower bytes calculation

* issue#444: PHP bug - Array and string offset access syntax with curly braces is deprecated

* issue#446: Threshold not triggering if CDEF is utilized

* issue#449: Notification List is not saved when adding new device

* issue#450: Thold generate wrong bandwidth alert

* issue: Function array_column() missing from PHP5.4

* issue: Unable to search for thresholds by regular expression

* issue: Percentage threholds that show horizontal rule are cut off

* feature: Migrate all Images to Fontawesome Glyphs

--- 1.4 ---

* issue#400: Slow cli_thresholds.php Performance

* issue#405: thold_functions.php Undefined variable "polling_interval"

* issue#406: THOLD 1.3.4 no 1 minute cycle/Trigger option

* issue#407: Error php deprecated when delete any graph

* issue#409: Cannot send mail due to default From mail is invalid

* issue#410: php error when creating threshold - Undefined index: data_type

* issue#411: The newly added device In Stat value incorrect

* issue: Make notification lists aware of device removal

* issue: When Data Manipulation type is Percentage, RPN Expression column is shown

--- 1.3.4 ---

* issue#404: THOLD 1.3.3 Cacti 1.2.9 PHP errors

--- 1.3.3 ---

* feature#359: Allow DOWNTIME variable substitution also in UP/DOWN e-mail subject

* feature#375: Enhance Thold to Export Thold Logs to csv

* feature: Properly support 10, 20, and 30 second polling

* feature: Add additional environment variables for scripts

* issue#345: Adding an alarm threshold does not work

* issue#351: Missed required param $host_id of thold_new_graphs_save() in
  thold_functions.php

* issue#353: Thold Wizard enables Cacti poller to remove newly created
  thresholds

* issue#354: Error trying to resolve Graph display values with error: Unknown
  RPN type: 1

* issue#355: Thold search fields do not work with some international characters

* issue#356: Thold should not show the 'i' suffix for percentage type tholds

* issue#358: Downtime message is only calculated when SNMP is in use

* issue#361: Thresholds are not applying CDEFs correctly

* issue#364: Thold 1.3.2 A non-numeric value encountered in thold_functions.php

* issue#368: When using Classic Theme, devices not appearing when try to create
  a threshold

* issue#369: When saving a template, notification lists of all
  templates are updated

* issue#381: Threshold Daemon Server (get rid of start script)

* issue#383: Backtrace errors when first creating a thold

* issue#384: Call to undefined function rrdtool_execute() in thold_functions.php:4094

* issue#391: Undefined CDEF generated with latest Core / Thold versions

* issue#392: Make THOLD working on 1min poller w/ 5min rrd

* issue#394: THOLD shows devices that have been deleted as down

* issue#395: Filtering on Ack Required field does not work

* issue#401: Errors occur with invalid thresholds with a bad thold type

--- 1.3.2 ---

* issue#349: Undefined variable notice when attempting to create a threshold

* issue#352: Non-numeric value error can sometimes be issued by
  thold_format_number()

--- 1.3.1 ---

* issue#342: Unable to include thold_functions.php in thold regex function

* issue#343: Call to undefined function db_fetch_cell_assoc()

* issue#344: Thold VRules Display is subject to XSS Attack

--- 1.3.0 ---

* feature#240: Thold plugin expose alerting information for external script

* feature#259: Custom status was not replaced correctly after creating threshold

* feature#274: Data Source link on Data Template column is ambiguous and can
  lead to confusion.

* feature#292: New Requirement: CLI to import thold template.

* feature: Add last changed date to Threshold table to make it easier to tell
  when a Threshold was last triggered.

* feature: Add Sites to Notification Lists for Devices and Thresholds.

* feature: Allow Threshold Notification Settings to be overwritten based upon
  Template setting.

* feature: Set THOLD_* specific environment variables for script use.

* feature: Allow Threshold Acknowledgment and Suspension of Notification.

* issue#78: Different dimension - MegaBytes instead of Megabits

* issue#95: Enhancement: Display duration count in days:hr:min:sec for triggered
  thold alarms and warnings

* issue#182: When adding/removing list from a Threshold, Templated Thresholds
  should be ignored.

* issue#241: Problem when acknowleding a thold

* issue#248: No device available when attempting to manually add a new Threshold

* issue#253: Threshold names are not being displayed correctly

* issue#257: Can not create thold for graph

* issue#258: When listing Thresholds, unable to sort by Data Source column

* issue#260: New thold tab in a threshold can not work properly

* issue#262: MySQL is sorting measured values as strings not numerics on Log tab

* issue#268: Allow Device Association/Disassociation to set Thresholds
  Notification as well.

* issue#271: A non-numeric value encountered in file polling.php line 570 & 571

* issue#272: MySQL is sorting measured values as strings not numerics on Thold
  tab

* issue#273: Wrong navigation bar link

* issue#276: When saving a threshold, multiple errors are generated

* issue#277: When finding data sources to template, string vs array error occurs
  if templates are found

* issue#278: When creating new thresholds, created count generates errors

* issue#283: Do not show 'Threshold Create' icon on the Graphs page for
  Aggregates

* issue#287: Thresholds are not being created in 1.2.3

* issue#289: Threshold filter on Thold - Log tab can not work

* issue#290: Upgrade log some SQL Error, and save thold_template failure

* issue#293: php error, undefined selected_items in notify_lists.php

* issue#294: Update thold_webapi.php

* issue#295: Thold daemon setting causing warnings

* issue#296: plugin_thold_contacts keep obsoleted data if email is changed from
  User Management

* issue#297: Thold row always `alert color` when current value only reach warn
  hi/low

* issue#298: Thold(tab)->Log(Subtab)->Threshold(Filter) should be "threshold",
  current is template

* issue#299: php error Constant STAT_NORMAL,STAT_LO,STAT_HI already defined

* issue#300: Thold shows multiple tabs as 'selected' when first creating a thold

* issue#301: Thold Graph does not substitute comment for warn/alert hrule

* issue#302: Fixed: PHP Notice: Undefined Variable $thold_data

* issue#305: Correct three PHP Undefined indexes and CSS errors

* issue#307: Thold should log thold device down and recovering to Cacti log for
  syslog forwarding

* issue#309: Remove use of deprecated each() from thold_functions.php

* issue#310: Sort by Low value in management - Threshold page can not work well

* issue#311: Can not show graphs when drill down from Thold - Device Status

* issue#312: A warning thold use wrong color

* issue#314: Current display value incorrect

* issue#315: Can not distinct threshold with same graph template but with
  different DataSource

* issue#316: Wrong color when baseline deviation type of threshold back to
  normal

* issue#319: Thold duplicating tholds when two Graphs use the same Data Source

* issue#320: Thold Graph broken when name include single quote

* issue#322: Standalone thresholds based on graph with serveral DS dont work
  for DS after the 1st one...

* issue#323: "Associated Graph (Graphs using this RRD)" field not including all
  graphs using same RRD...

* issue#324: value not correct in Thold - Log page

* issue#325: In State status incorrect for threshold

* issue#326: Threshold Acknowledgment, Suspend, Resume not functional

* issue#327: php error for thold_functions.php

* issue#329: SQL Error due to typo if thold upgrade from 0.6 and prior

* issue#334: Undefined index: data_source_name in file: thold_functions.php

* issue#335: Undefined index: desc in file:
  CACTI_TOP/plugins/thold/thold_functions.php

* issue#336: cacti 1.2.4 and THOLD 1.2.x check_all_thresholds error

* issue#339: thold_rpn error if some xxx_hi/low value is empty

* issue#340: fix wrong function name function db_fetch_cell_assoc

* issue: When updating the Notification List for a Threshold Template, updates
  were not pushed to Thresholds.

--- 1.2.3 ---

* issue#283: Do not show 'Threshold Create' icon on the Graphs page for
  Aggregates

* issue#284: Threshold loses parent template if template propagation disabled
  during save

* issue#285: Threshold name cache is not being populated properly

* issue#295: Custom status was not replaced correctly after creating threshold

--- 1.2.2 ---

* feature: Add hook for Threshold autocreation during automation cycle.  This
  allows Cacti v1.3 to use a hook instead of hardcoded plugin functionality to
  generate the thresholds

--- 1.2.1 ---

* feature: Allow CLOG to direct link to threshold

* issue#95: Display duration count in days:hr:min:sec for triggered thold alarms
  and warnings

* issue#248: No device available when attempting to manually add a new
  Threshold

* issue#250: Database upgrade is not performed properly

* issue#253: Threshold names are not being displayed correctly

* issue#257: Creating a Threshold from Template using the Wizard inconsistent

* issue#262: MySQL is sorting measured values as strings not numerics on Log
  tab

* issue#266: Autocreation of Thresholds not working as expected when multiple
  Templates specified

* issue#267: Database Upgrade not handled properly in some cases

* issue#271: A non-numeric value encountered in file polling.php line 570 & 571

* issue#272: MySQL is sorting measured values as strings not numerics on Thold tab

* issue#273: Wrong navigation bar link

* issue#274: Data Source link on Data Template column is ambiguous and can lead
  to confusion

* issue#276: When saving a threshold, multiple errors are generated

* issue#277: When finding data sources to template, string vs array error occurs
  if templates are found

* issue#278: When creating new thresholds, created count generates errors

--- 1.2.0 ---

* feature: Add Acknowledgment support to thold

* feature: Allow per threshold syslog settings

* feature: Allow Notification Lists to be hooked by other Plugins

* feature: Allow command execution on Threshold breaches and restoral events

* feature: Add per threshold email body for alert and warning messages

* feature: Allow threshold actions to be hooked by other plugins

* issue#207: Errors when attempting to save a Threshold

* issue#210: Disabled Thresholds appear in breach/trigger filters

* issue#211: Site filtering does not work without another filter selected

* issue#220: Fixed bug when mass host down created wrong email sending where
  used both global and local notification lists on hosts.

* issue#226: Tholds cli_thresholds.php script does not fully function

* issue#244: Unknown column 'td.template' error generated when propagating
  templates

* issue#245: Upgrade to THold 1.2.0 fails with massive stack trace being
  repeated

* issue#265: When saving a Threshold or a Threshold Template you were redirected
  back to the respective table views and not left on the edit page.

--- 1.0.6 ---

* feature: Template list shows clickable/sortable count of Thresholds

* feature: Threshold list allows filtering by Threshold Template as well as
  Data Template

* issue: Suggested names was not selecting the correct data source

--- 1.0.5 ---

* feature: Increase size for RPN expressions

* feature: Template list should allow reapply suggested name

* issue#168: Suggested names does not work if no substitute variable used

* issue#198: Depreciated each() function usage under PHP 7.2

* issue#199: Threshold template association issue

--- 1.0.4 ---

* issue#117: ERROR: possible illegal string offset when sending mails

* issue#125: Thold VRules Enabling and Disabling results in a broken page

* issue#128: Thold log date format does not align with Cacti log format

* issue#130: Correct errors relative to mailer headers

* issue#131: Users without permissions can disable/enable tholds

* issue#132: Undefined indexes from time to time when storing current value

* issue#136: Add Support `<DATE>` in Cacti format and document `<DATE_RFC822>`
  option

* issue#138: Duplicate thresholds created

* issue#141: Division by zero when removing threshold due to deleted Graph
  Template

* issue#142: When two data source have same field name, thold will create
  duplicates

* issue#144: Unexpected threshold breach due to very high value

* issue#145: Cacti turning Thold off

* issue#148: Creating threshold from data source fails if not all variables are
  graphed

* issue#153: Threshold Template "Operator Notes" Variable not Recognized

* issue#154: Removed Devices are not pruned to thold tables

* issue#155: Autocreate now failing due to recent changes

* issue#157: Operator Notes are not propagated during Template updates

* issue#160: RPN Expressions not handling ifSpeed and ifHighSpeed replacement

* issue#167: Separate filter for management and monitoring tabs

* feature: Now shows counts of notification list usage on front page

* feature: First attempt of thresholding by data collector.  Note that if you
  are using the thold daemon, you will need to run on each data collector.

* feature: Report Thold Daemon Runtime with Millisecond precision.

* feature: Prepare for new Cacti 1.2 feature for storing RRDfiles on remote
  storage

* feature: Add `<TIME>`, `<DATE>` in Cacti format, and `<DATE_RFC822>` to Device
  Up/Down Emails

* feature: Allow Operator Notes to include threshold tags

* issue: Speed thold processing by reducing queries and string manipulation

* issue: Fully convert the thold daemon to Cacti 1.x.  Old converted thold
  daemon was generating MySQL 2006 errors due to a feature in PDO

--- 1.0.3 ---

* feature#34: Allow notes to be attached to thresholds and templates

* feature: New setting for expression tholds using ifHighSpeed to handle empty
  ifHighSpeed entries

* issue#81: Alert settings are mandatory, Warning is optional

* issue#86: Undefined variable in snmptrap function

* issue#89: Add Site to main table views

* issue#91: Search filter not working from Thold Management

* issue#93: Thold IDs when auto created are NOT in sequential order

* issue#94: `<DOWNTIME>` not processed properly when the value has never changed

* issue#97: Re-write logger() function to leverage subject instead of attempting
  to reconstruct message

* issue#99: Thold disables itself due to division by zero for invalid RPN expressions

* issue#104: Query using the wrong/deprecated column

* issue#106: Sort host list by time in state, graphs, and data sources

* issue#109: RPN Expressions fail when ifHighSpeed is set to zero

* issue#110: Notification List duplication not implemented

* issue#111: Default Status filter not respected on thold tab

* issue: Baseline tholds generate SQL errors and PHP warnings

* issue: Add test domain for i18n

--- 1.0.2 ---

* issue#60: Threshold Templates could not be exported

* issue#61: RPN Expression column too narrow

* issue#64: Request High/Low Threshold allow for floating point values

* issue#65: Link in Thold Email not working due to &rra_id=1

* issue#73: PHP Warnings when saving baseline alerts

--- 1.0.1 ---

* issue#57: Thold can not display graph with hrules due to lack of escaping

* issue#58: Autocreate creates too many thresholds

* bug: Resolve issue where wrong graph could be attached to email

--- 1.0.0 ---

* feature: Initial Support for Cacti 1.0

* feature: Multiple tholds per data source

* feature: Moving most SQL to prepared statements for security

* feature: Moving away from direct use of GET, REQUEST, and POST variables for
  security

* feature: Rename several legacy database columns to match Cacti's default
  schema, making the thold code much more readable

* feature: complete audit and rewrite of several functions addressing:
  readability, clarity, and consistency

--- 0.6 ---

* feature: Reduce influence upon Cactis poller runtime to a minimum by
  introducing a Thold daemon (also allows distribution)

* feature: Support of SNMP traps and informs ( requires the CACTI SNMPAgent
  plugin v0.2 or above )

* feature: CACTI-THOLD-MIB added. Yep, Thold got its own MIB. ;)

* Bug#0002371: Thold plugin - Undefined index: template in
  /var/www/html/plugins/thold/thold.php on line 273

--- 0.5 ---

* feature: Allow threshold log retention to be configurable

* bug: Thold Suggested Names not applied in all cases

* bug: Use of Aggregate breaks Thold edit UI and new Tholds

* bug: #0002247 - SQL Injection in thold.php (must be authenticated first)
  Thanks Primož!!

--- 0.4.9 ---

* feature: Allow HRULES based upon HI/LOW Value portions courtesy of Barnaby
  Puttick

* bug: Restoral Emails not working in all cases

* bug: When polling returns non-numeric data,  dont return false LOW Alerts

* bug: Fix time based Warnings

* bug: More issues with Realert for Time Based and Hi/Low

* bug: Be more specific about what 'Hosts:' means when generating stats

--- 0.4.8 ---

* feature: Support for Ugroup Plugin

* bug: Speed of |query_*| replacements in RPN Expressions

* bug: Correct name space collision with Weathermap

* bug: THRESHOLDNAME replacement using Data Source name and not Threshold Name

* bug: Notification List Disassociate Global List not functional

--- 0.4.7 ---

* feature: Add index to optimize page loads

* feature: Allow more hosts to be reported as down

--- 0.4.6 ---

* feature: Add Warning Support Curtesy (Thomas Urban)

* feature: Improve display of Baseline Alerts

* feature: Add Log Tab and new Events for Readablilty

* feature: Allow a variable replacements in Threshold names

* bug: Fix several GUI and polling issues

* bug: Dont alert on blank data output

* bug: Remove old ununsed variables

* bug: Fix thold messages on restorals

* feature: Reapply Suggested Name for Tholds

* feature: Add Email Priority per 'dragossto'

* feature: Add Email Notification Lists for Thresholds, Templates and Dead
  Hosts

* feature: Add Ability to Disable Legacy Alerting

* feature: Add Ability to use |ds:dsname| and |query_ifSpeed| in RPN Expression

* feature: Add RPN Expressions 'AND' and 'OR'

* feature: Add Support for Boost to Baseline Tholds

* feature: Add Template Export/Import Functionality

* feature: Add DSStats functionality to RPN Expressions save on Disk I/O

--- 0.4.4 ---

* bug: Fix emailing of alerts when PHP-GD is not available

* feature: Add Debug logging

* feature: Sort Threshold drop down list by Description

* bug: Add missing column to upgrade script

* feature: Update baseline description

* bug: Multiple fixes posted by our hard working forum users!!!

--- 0.4.3 ---

* feature: Add support for maint plugin

* bug: Fix to allow Add Wizard to show all datasources belonging to a graph
  (even when in separate data templates)

* bug: Re-apply SQL speed up when polling

* bug: Several fixes to Baselining

* bug: Several fixes to CDEFs

* feature: Add customizable subjects and message body for down host alerts

--- 0.4.2 ---

* bug: Fixed Cacti 0.8.7g compatibility

* bug#0001753: Lotus Notes are unable to render inline png pictures

* bug#0001810: Thold: RRDTool 1.4.x error while determining last RRD value

* bug: Fix for compatibility with other plugins using datasource action hook

* bug: Re-add syslog messages for down hosts

* bug: Fixed a few minor issues

* bug: Allow the use of query_XYZ in CDEFs

* bug: Fix host status page to only allow users to see hosts they have access
  to

* bug: Fix ru_nswap errors on Windows

--- 0.4.1 ---

* feature: Add thold statistics to settings table to allow graphing the results

* bug: Return False from host status check function if it is disabled

* bug: Speed up Datasource Query on Told Tab

* bug: Fix CDEF usage

* bug: Fix Thold Add Wizard Bug with IE6/7

* bug: Fix HTTP_REFERER error

* bug: Fix duplicate function names (when improperly installing plugin)

--- 0.4.0 ---

* bug: Fix for multiple poller intervals, use RRD Step of Data Source
  instead of Polling Interval

* Bug: Fix for down host alerting on disabled hosts

* feature: Add filtering to listthold.php

* feature: Use time periods instead of number of pollings when specifying
  Repeat Alerts and Fail Triggers

* feature: Time Based Threshold Checking

* feature: Percentage Calculation

* feature: Add Threshold Sub Tabs with Both Threshold and Host Status

* feature: Allow Naming of Threshold Templates and Thresholds

* feature: Allow Thresholds to be added in mass via a Data Sources dropdown

* feature: Allow Thresholds to be added in mass via a Graph Management dropdown

* feature: Added Background Color Legend for Multiple Interfaces

* feature: Add Threshold creation Wizard

* feature: Make Wizard Design Consistent

* feature: Add Filtering to User Threshold View and Host Status View

* feature: Allow Disable/Enable/Edit/View Graph Actions from Main Page

* feature: Allow Edit Host from Host Status

* feature: Enable Toggle VRULE On/Off to show breached Thresholds on the graph
  images

* feature: Allow Adding Thresholds from Graphs Page

* feature: Use Cacti User Permissions when viewing and editing Thresholds

* feature: Allow Weekend Exemptions per Threshold

* feature: Allow the disabling of the Restoration Email per Threshold

* feature: Allow logging of all Threshold Breaches to Cacti Log File

* feature: Allow logging of all Threshold creations / changing / deletions to
  Cacti Log File

* feature: Allow global disabling of all Thresholds

* feature: Allow setting of Syslog Facility for Syslog Logging

--- 0.3.9 ---

* feature: Major poller speed increase when using large numbers of thresholds

--- 0.3.8 ---

* bug: Fix undefined variable error on thold.php

--- 0.3.7 ---

* bug: Fix issue with thold.php not correctly saving the host id

* bug: Fix issue with Setting plugin having to be before thold in the plugins
  array

--- 0.3.6 ---

* feature: Compatible with Cacti v0.8.7 (not backwards compatible with previous
  versions)

* bug: Fixed issue with saving user email addresses

* bug: Fixed issue with tab images

--- 0.3.5.2 ---

* bug: Fix issues for users not using latest SVN of the Plugin Architecture

--- 0.3.5.1 ---

* bug: Fix for latest Cacti v0.8.6k SVN (requires latest SVN of Plugin
  Architecture)

--- 0.3.5 ---

* feature: Update plugin to use the Settings plugin for mail functionality

* bug: Fix for thold values being off when using different polling intervals

* feature: Use new "api_user_realm_auth" from Plugin Architecture

* bug: Fix for creating multiple thresholds via templates from the same
  DataSource

* bug: Fix for threshold template data propagating to an incorrect threshold

* feature: Added Email Address field to User's Profiles

* feature: Added ability to select a user to alert for a threshold instead of
  having to type in their email address

* feature: Change to using the Settings plugin for mail functionality

--- 0.3.4 ---

* feature: Allow text only threshold alerts (aka no graph!)

* feature: Add some text to the alerts, including the hostname

* bug: Change the email to be sent as "Cacti" instead of PHPMailer

* bug: Fix issue with host alerts still being sent as multipart messages

* feature: Add the ability to completely customize the threshold alert (allow
  descriptors)

* feature: Re-arrange the Settings page to group like options

* bug: Fix an issue when applying thresholds to a device with no datasources /
  dataqueries

* feature: Add the ability for template changes to propagate back to the
  thresholds (with the ability to disable per threshold)

--- 0.3.3 ---

* bug#0000076 - Fix to speed up processing of thresholds (thanks mikv!)

* bug#0000079 - Bug causing thold to not respect the others plugins device page
  actions

* bug: Fix an issue with re-alert set to 0 still alerting

* bug: Fix the host down messages, this will work with cactid also

* feature: Host Down messages are now sent as text only emails

--- 0.3.2 ---

* bug: Fix an index error message displayed when clicking the auto-creation
  link

* bug: Fix an issue with thresholds not switching into "is still down" mode
  when alerting

* bug: Fix a rare error where under certain conditions no data is passed back
  to threshold during polling

--- 0.3.1 ---

* feature: Patch from William Riley to allow the threshold management page to
  be split into separate pages

* bug: Fix a php short tag issue on graph_thold.php

* feature: Major rewrite of thold processing, now we pull from the poller
  output table instead of directly from the rrd files

* feature: Major code cleanup in a few files

* feature: Remove the tholdset table

* feature: Remove the thold table

* feature: Add an option for the priority level used when syslogging

* feature: Add the option allow applying thresholds to multiple hosts at once
  through the Devices page

* bug#0000035 - Does not handle INDEXED data sources correctly

* bug#0000038 - Thresholding non-integer does not seem to work

* bug#0000041 - Subject of mail message now reflects the data source item (also
  #0000066)

* bug#0000059 - Thold always displays and assigns only one associated graph
  with the lowest graph_id

* bug#0000060 - Issue with "nan" values in the RRD File

* bug#0000062 - Step value of the rra is not considered for fetching rrd values

* bug#0000063 - CDEF function error (100 -DS)

--- 0.3.0 ---

* bug#0000040 - Fix issue with invalid link in Navigation panel under certain
  circumstances

* bug#0000048 - Fix improper notification of global address when Threshold set
  to "Force: Off"

* bug#0000042 - Add ability to apply a CDEF to the threshold before using the
  data

* bug#0000054 - Fix issue with CDEFs on manual threshold creating page

--- 0.2.9 ---

* bug#0000021 - Fix for rare SQL errors when auto-creating Thresholds when no
  Graph is associated with a Datasource

* bug#0000024 - Thold Templates not allowing for NULL Upper or Lower Baselines

* bug#0000031 - When creating Thresholds and Templates, default values were not
  provided

* bug#0000032 - Validation Error on listthold.php when selecting "Show All"

* bug: Added some more POST validation to Threshold Templates

* bug: Fix for Undefined offset in thold.php

* feature: Changed the font size for the Auto-Create Thold Messages

--- 0.2.8 ---

* bug#0000013 - Fix issues with database names with uncommon characters by
  enclosing in back-ticks.

* bug#0000030 - Allow use of decimal values in thresholds up to 4 decimal
  places

* bug#0000005 - Fix for threshold values not matching the graph values

* feature: Change "Thresholds" to "Threshold Templates"

--- 0.2.7 ---

* bug: Fixes for "are you sure you meant month 899" errors

* bug: Fixes for table tholdset being empty causes poller to not function

* bug: Resolved issue with Base URL auto generation pointing to the plugin directory

* feature: Code Cleanup of Threshold Management Page

* feature: "Instructions" rewording on Threshold Management Page

* feature: Can now select multiple Thresholds to delete

* bug: Orphan thresholds are now cleaned up automatically

* bug: Fixed Guest account access to View Thresholds

--- 0.2.6 ---

* bug: Fixes for HI and Low thresholds limiting the max characters

* bug: Fixed wrong data reported to thold.log

* bug: Fix for the error: "sh: line 1: -e: command not found" during thold
  checks

* feature: Added command line switch /show for check-thold.php, which will show
  the output of all thresholds

* feature: Added command line switch /debug to allow it to log to file (to make
  it permanent, just set debug=1 in the file)

* bug: Fixed the Test Email link for IE

--- 0.2.5 ---

* feature: Test Link Created to help debug mail sending issues

* bug: Several fixes to the Threshold Mailing (SMTP especially was broken)

* bug: Several fixes to the Down Host Notification

--- 0.2.4 ---

* feature: Added Threshold Templates

* bug: A few other minor interface fixes

--- 0.2.3 ---

* feature: Emails now use embedded PNG images (instead of links)

* feature: Option to send mail via PHP Mail function, Sendmail, or SMTP (even
  authenicated)

* bug: Set the from email address and name

* bug: Fixed the Host Down Notification

--- 0.2.0 ---

* feature: Auto-create the database if it doesn't exist

* bug: Better sorting on threshold tables

* bug: Does not require its own cron job anymore

* bug: Lots of bug fixes for issues in the original threshold module
