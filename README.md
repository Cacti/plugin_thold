# thold

The Cacti thold plugin is designed to be a fault management system driven by
Cacti's Graph information.  It provides the facility to inspect data in a Cacti
Graph and the underlying RRDfile, and generate alerts for management and
operations personnel.  It provides Email, Syslog, and SNMP Trap or Inform
escalations.  In addition, it also can notify personnel of Cacti Device status
changes through Email, Syslog, and either SNMP Trap or Inform.

NOTE: The Thold plugin that is in GitHub is ONLY compatible with Cacti 1.0.0 and
above!

## Installation

To install the plugin, simply copy the plugin_thold directory to Cacti's plugins
directory and rename it to simply 'thold'.  Once this is complete, go to Cacti's
Plugin Management section, and Install and Enable the plugin.  Once this is
complete, you can grant users permission to view and create Thresholds.

Once you have installed thold, you should verify that Email support is
functioning in Cacti by going to Cacti's Console and under Configuration select
Settings, and from there the 'Mail/Reporting/DNS'.  From there, you can test
your mail settings to validate that users will receive notifications via email.

After you have completed that, you should go to the 'Thresholds' Settings tab,
and become familiar with its settings.  From there, you can provide overall
control of thold, and set defaults for things like Email bodies, weekend
exemptions, alert log retention, logging, etc.

As with much of Cacti, settings should be documented in line with the actual
setting.  If you find that any of these settings are ambiguous, please create a
pull request with your proposed changes.

## Usage

The Cacti 1.0 version of thold is designed to work with Device Templates.
Therefore, when you configure a Device Template, you can add default Threshold
Templates to that Device Template and when a Device in Cacti is created with
that Device Template, all the required Thresholds will be created automatically.
Of course, creating stand alone Thresholds is still supported.

Also new in thold version 1.0 is the ability to create multiple Thresholds per
Data Source.  So, you can have a Baseline Threshold say measuring the rate of
change of a file system, while at the same having a Hi/Low and Time Based
thresholds to notify you of free space low type of events.

Most standalone Thresholds are created from the Graph Management interface in
Cacti.  This process starts with first creating a Threshold Template for the
specific Graph Template in question, and then from Graph Management selecting
the Graphs that you wish to apply this Template to.  Then, from the Cacti
Actions drop down, select 'Create Threshold from Template' and simply select
your desired Threshold Template.  Though this method continues to work today, we
believe that with the support of associating Threshold Templates with Device
Templates, that this method will become less popular over time.

When creating your first Threshold using thold, you need to be first understand
the Threshold Type.  They include: High / Low, Time Based, and Baseline
Deviation.  The High / Low are the easiest to understand.  If the measured value
falls either above or below the High / Low values, for the Min Trigger Duration
specified in the High / Low section, it will trigger an alert.  In the Time
Based Threshold type, the measured value must go above or below the High / Low
values so many times in the measurement window, or the 'Time Period Length'.
Lastly, the Baseline Deviation provides a floating window in the 'Time reference
in the past' to measure change.  If the change in the measured value either goes
up or down by a certain value in that time period, an alert will be triggered.

The Re-Alert Cycle is how often you wish to re-inform either via Email, Syslog,
or SNMP Trap or Inform if the Threshold has not resolved itself before then.

Thold has multiple Data Manipulation types, including: Exact Value, CDEF,
Percentage, and RPN Expression.  The simplest form is the Exact Value data
manipulation where thold simply takes the raw value collected from Cacti's Data
Collector, and applies rules to it.  In the case of COUNTER type data, thold
will convert that to a relative value automatically.  The CDEF data manipulation
allows you to use some, but not all Cacti CDEF's and apply them to Graph Data.
The CDEF's that work, have to leverage one or more of the special types included
in Cacti's CDEF implementation like 'CURRENT_DATASOURCE' to be relative.  The
Percentage Data Manipulation requires you to select the 'primary' Data Source as
the Numerator, and then when selecting 'Percentage', you will be able to select
the Denominator of the percentage calculation.  The most involved Data
Manipulation is the RPN Expression type.  This Data Manipulation type allows you
to use RPN Expressions to determine the value to be evaluated.  It can include
other Data Sources in the Cacti graph in addition to the selected Data Source.
It follows closely RRDtools RPN logic, and most RRDtool RPN functions are
supported.

If you plan on using the Threshold Daemon to increase the scalability of your
thresholds, note that you must modify and install the thold_daemon.service file
into your systemd configuration, and then start and test the service.  If you
fail to perform these steps, thold will appear to not work as expected.

Lastly, please note that several forks of the thold plugin are available from
different sources.  These forks of thold are not necessarily compatible with the
current version of Cacti's thold plugin.  Please be aware of this when
installing thold for the first time.

## Authors

The thold plugin has been in development for well over a decade with increasing
functionality and stability over that time.  There have been several
contributors to thold over the years.  Chief among them are Jimmy Conner, Larry
Adams, and Andreas Braun.  We hope that version 1.0 and beyond are the most
stable and robust versions of thold ever published.  We are always looking for
new ideas.  So, this won't be the last release of thold, you can rest assured of
that.

