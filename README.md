Overview
--------
GovWifi allows staff and visitors in government organisations to connect and stay connected to a secure wifi service 
whilst they move from building to building.

Government organisations often have several different wifi networks in the same building.
The GovWifi service is managed by Government Digital Service (GDS). Government organisations can run the service on
their existing infrastructure. It has been designed to replace user and guest wifi solutions with a single secure
wifi network.

For more high level information and links please
[see our guidance](https://www.gov.uk/government/publications/govwifi/govwifi).

This repository contains the backend API logic responsible for:
- AAA
[(Authentication, Authorization, and Accounting)](http://networkradius.com/doc/3.0.10/concepts/introduction/AAA.html),
- self-service setup and administration of site (building / location) configuration,
- generation of automated, specific reports upon request from administrators,
- handling of user accounts,
- sending out surveys,
- generating reports for [Performance Platform](https://www.gov.uk/performance/govwifi).


AAA
---
The main functionality of the service is the authentication of users by their unique username / password pair. The 
backend API sits behind a set of [FreeRADIUS](http://freeradius.org/) servers, making use of
the [REST module](http://networkradius.com/doc/3.0.10/raddb/mods-available/rest.html) to make the API calls.

The entry point for the API calls [/api/api.php](https://github.com/alphagov/govwifi/blob/master/src/api/api.php).
The appropriate requests are routed to this entry point by Apache configuration rules. The supported request types are:
- authorize
- post-auth
- accounting

In any of these cases when user data is loaded from the database it is then saved to
[cache.](https://github.com/alphagov/govwifi/blob/master/src/Cache.php) which currently uses
[Memcached](https://memcached.org/) provided by [AWS Elasticache](https://aws.amazon.com/elasticache/).
The functionalities are handled by the [AAA class](https://github.com/alphagov/govwifi/blob/master/src/AAA.php) as
follows:

**Authorize**

Currently this is the very simple matter of checking for the health check user and allowing it in by default, or
returning the user's password to be checked on the RADIUS side.
This is the point where we could implement building-specific restrictions, for example based on the user's signup
journey (email or text); or, in case of email registrations, the specific email domain the user has signed up with.
We could also consider removing the special case for health check.

**Post-Auth**

This is a ping-back from the RADIUS server to confirm if the authentication was successful. The result is
"Access-Accept" if so, "Access-Reject" otherwise. At this point, if it was successful we start a session for the user
(add a new session record to the database) with the current time as start and other user and site-specific values.
The response header sent back to the RADIUS server is 204 OK (No content) in either of the above cases, however it
is 404 (Not found) if the result received was not recognised.

**Accounting**

There are generally speaking 3 types (Acct-Status-Type) of accounting requests: start, stop and interim.
[The RADIUS standard](https://tools.ietf.org/html/rfc2866) also defines 2 additional, optional status types called
Accounting-On and Accounting-Off which correspond to Start and Stop respectively.
The accounting data is POST-ed in these requests, in JSON format,
[see examples here.](https://github.com/alphagov/govwifi/tree/master/tests/acceptance/config)

Upon a **Start** request the relevant parts of the data is saved
to [cache](https://github.com/alphagov/govwifi/blob/master/src/Cache.php)
and the session record in the database is also updated to cater for building identifiers that are only sent in the
accounting requests.

In case of **Interim** requests only the cache is updated.

When a **Stop** request is received the relevant data is removed from the cache and the session record in the database is
finalised.

External API integrations
-------------------------
**Emails**

For both incoming and outgoing email support we use [Amazon's SES](https://aws.amazon.com/ses/).
The incoming endpoint is [/sns/](https://github.com/alphagov/govwifi/blob/master/src/sns/index.php) - which is in fact a
notifications endpoint for the [Simple Notifications Service](https://aws.amazon.com/sns/). When an email is received by
one of the endpoints defined in the
[SES email configuration](https://github.com/alphagov/govwifi-terraform/blob/master/govwifi-emails/emails.tf) a
notification is pushed to the endpoint above containing the email's metadata. This is then processed by
[SnsEmailProvider](https://github.com/alphagov/govwifi/blob/master/src/providers/SnsEmailProvider.php).

The actual handling of the incoming and outgoing email logic is done by the
[EmailRequest](https://github.com/alphagov/govwifi/blob/master/src/EmailRequest.php) and
[EmailResponse](https://github.com/alphagov/govwifi/blob/master/src/EmailResponse.php) classes respectively.

**Text messages**

Currently we use separate providers for incoming and outgoing text messages. This is likely to change in the near future
as Notify has recently added support for incoming SMS.

The incoming endpoint is [/sms/](https://github.com/alphagov/govwifi/blob/master/src/sms/index.php) which handles text
messages sent by the incoming SMS provider.

Our outgoing provider is [GovUK Notify](https://www.notifications.service.gov.uk/). We're using the
[client library](https://github.com/alphagov/notifications-php-client) they provide.

The incoming and outgoing logic is handled by the
[SmsRequest](https://github.com/alphagov/govwifi/blob/master/src/SmsRequest.php) and
[SmsResponse](https://github.com/alphagov/govwifi/blob/master/src/SmsResponse.php) classes respectively.

Site administration
-------------------
Authorised administrators can talk to the automated system via the newsite@ email address to register a new location or
to change an existing one. Full documentation of the commands and supported automated functionalities is
[here](https://www.gov.uk/guidance/set-up-govwifi-on-your-infrastructure).

Administrators have to sign up with the GovWifi support team directly. The main reason for this is to keep control of
who's granted privileged access to the system within the team. Hence the process of creating "Organisations" and admins
belonging to these is manual.

We are in the process of planning a support portal where the admin and organisation setup will be simplified, as well
as the administration of the existing sites.

User account handling
---------------------
There are currently 3 journeys by which a [user can register](https://www.gov.uk/govwifi):
- Self-signup
- Sponsored
- Text message

These require handling of incoming and outgoing text messages as well as emails. In case of user registration we are
not using attachments in any way.
The email templates live in the [source](https://github.com/alphagov/govwifi/tree/master/templates/email) however for
the text messages we're using the functionalities provided by GovUK Notify
[platform](https://www.notifications.service.gov.uk) where separate staging and live environments are set up in order
to test different versions.

Timed jobs
----------
Timed jobs are called from cron, executed from the
[management instance](https://github.com/alphagov/govwifi-terraform/blob/master/govwifi-backend/management.tf) running in
the same region as the backends.

Surveys
-------
Automatic surveys are sent out after registration to measure user satisfaction and gather feedback - as the registration
is really the only point when we're in communication with our end users. The
[entry point is a timed job](https://github.com/alphagov/govwifi/blob/master/src/timedjobs/survey/index.php). Logic is
handled by the [Survey class](https://github.com/alphagov/govwifi/blob/master/src/Survey.php).

Integration with Performance Platform
-------------------------------------
Performance Platform displays graphs and statistical data sent to it in json format. They don't have a ready-made client
library so we use bespoke client logic,
[PerformancePlatformClient](https://github.com/alphagov/govwifi/blob/master/src/PerformancePlatformClient.php) to send
data to it.
Specific reports all inherit common logic from the abstract class
[PerformancePlatformReport](https://github.com/alphagov/govwifi/blob/master/src/PerformancePlatformReport.php), these
specific reports are:
- [Volumetric data](https://github.com/alphagov/govwifi/blob/master/src/ReportVolumetrics.php)
- [Account usage stats](https://github.com/alphagov/govwifi/blob/master/src/ReportAccountUsage.php)
- [Number of unique users](https://github.com/alphagov/govwifi/blob/master/src/ReportUniqueUsers.php)
- [Completion rate of the registration](https://github.com/alphagov/govwifi/blob/master/src/ReportCompletionRate.php)
- [Active locations](https://github.com/alphagov/govwifi/blob/master/src/ReportActiveLocations.php)

The reports are called daily, weekly or monthly as a timed job via the performanceplatform
[entry point](https://github.com/alphagov/govwifi/blob/master/src/timedjobs/performanceplatform/index.php).
There are no monthly reports at the moment.
