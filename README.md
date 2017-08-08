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

The entry point for the API calls the /api/api.php. The appropriate requests are routed to this entry point by Apache
configuration rules. The supported request types are:
- authorize
- post-auth
- accounting

In any of these cases when user data is loaded from the database it is saved then to
[cache.](https://github.com/alphagov/govwifi/blob/master/src/Cache.php) These functionalities are handled by the
[AAA class](https://github.com/alphagov/govwifi/blob/master/src/AAA.php) as follows:

Authorize

Currently this is the very simple matter of checking for the health check user and allowing it in by default, or
returning the user's password to be checked on the RADIUS side.
This is the point where we could implement building-specific restrictions, for example based on the user's signup
journey (email or text); or, in case of email registrations, the specific email domain the user has signed up with.

Post-Auth

This is a ping-back from the RADIUS server to confirm if the authentication was successful. The result is
"Access-Accept" if so, "Access-Reject" otherwise. At this point, if it was successful we start a session for the user
(add a new session record) with the current time as start and other user and site-specific values.
The response header sent back to the RADIUS server is 204 OK (No content) in either of the above cases, however it
is 404 (Not found) if the result received was not recognised.

Accounting

There are generally speaking 3 types (Acct-Status-Type) of accounting requests: start, stop and interim.
[The RADIUS standard](https://tools.ietf.org/html/rfc2866) also defines 2 additional, optional status types called
Accounting-On and Accounting-Off which correspond to Start and Stop respectively.
The accounting data is POST-ed in these requests, in JSON format,
[see examples here.](https://github.com/alphagov/govwifi/tree/master/tests/acceptance/config)

Upon a **Start** request The relevant parts of the data is saved
to [cache](https://github.com/alphagov/govwifi/blob/master/src/Cache.php)
and the session record in the database is also updated to cater for building identifiers that are only sent in the
accounting requests. We currently use [Memcached](https://memcached.org/) for caching.

In case of **Interim** requests only the cache is updated.

When a **Stop** request is received the relevant data is removed from the cache and the session record in the database is
finalised.

Site administration
-------------------

Authorised administrators can talk to the automated system via the newsite@ email address to register a new location or
to change an existing one.
