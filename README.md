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
- self-service setup and administration of site (building / location) configuration 
- generation of automated, specific reports upon request from administrators
- handling of user accounts, 
- sending out surveys,
- generating reports for [Performance Platform](https://www.gov.uk/performance/govwifi).


AAA
---
The main functionality of the service is the authentication of users by their unique username / password pair. The 
backend API sits behind a set of [FreeRADIUS](http://freeradius.org/) servers, making user of the 
[REST module](http://networkradius.com/doc/3.0.10/raddb/mods-available/rest.html) to make the API calls. 

The entry point for the API calls the /api/api.php. The appropriate requests are routed to this entry point by Apache
configuration rules. The supported request types are:
- authorize
- accounting
- post-auth



