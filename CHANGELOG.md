Changelog
=========

v1.1.3
------

 1. Added the ability to pass additional attributes to an ErrorResponse

v1.2
----

 1. Added default value to `$guarded` on Model
 2. Added the ability to pass JSON encode options to `toJsonResponse`

v1.2.1

 1. Fixed a bug where linked resources would not be associated to requested models

v1.2.2

 1. Implemented proper response codes for various HTTP methods as required by jsonapi.org spec
 2. Added a `ERROR_MISSING_DATA` constant to `Handler` for generic use where insufficient data was provided
