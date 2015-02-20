Changelog
=========

v3.0.0
------

 1. Updated code and docs for basic support for jsonapi 1.0.0.rc2 specs
 2. Updated support for sorting to allow for multiple items and ascending/descending specifications
 3. Added validation of POST/PUT data

v2.0.0
------

 1. Updated code and docs to support laravel 5.0
 2. Added basic support for sorting and filtering
 3. Updated POST response to return code 201 as specified in jsonapi specs

v1.1.3
------

 1. Added the ability to pass additional attributes to an ErrorResponse

v1.2
----

 1. Added default value to `$guarded` on Model
 2. Added the ability to pass JSON encode options to `toJsonResponse`

v1.2.1
------

 1. Fixed a bug where linked resources would not be associated to requested models

v1.2.2
------

 1. Implemented proper response codes for various HTTP methods as required by jsonapi.org spec
 2. Added a `ERROR_MISSING_DATA` constant to `Handler` for generic use where insufficient data was provided

v1.2.3
------

 1. Fix a bug which caused One-to-One relations not to work
 2. Improved handling of linked resources to never include other than the requested

v1.2.4
------

 1. Bugfixes

v1.2.5
------

 1. Add ability to pass additional error details (or *attributes*) through an `Exception`

v1.2.6
------

 1. Fix a bug which caused linked models to not be correctly included

v1.2.7
------

 1. Fix a mistake in phpDoc block

v1.2.8
------

 1. Fix a bug which caused the handler not to expose models from a has-many relationship

v1.2.9
------

 1. Ensure that toMany relations are presented on the entity with a plural key name

v1.2.10
-------

 1. Add the ability to map a relation name to a non-default key when serializing linked objects

v1.2.11
-------

 1. Add workaround for loading of nested relationships, see commit for details

v1.2.12
-------

 1. Fix a bug with previous update
