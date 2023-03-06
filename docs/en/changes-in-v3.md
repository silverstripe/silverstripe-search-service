# Breaking changes in `3.0.0`

Support for Elastic Enterprise Search is now supplied through
[Silverstripe Search Service - Elastic](https://github.com/silverstripe/silverstripe-search-service-elastic).

This was (more-or-less) just a lift and shift. There were namespace changes, linting changes, and one interface change
(described below), but otherwise (and especially if you were not overriding any classes) there should be no breaking 
changes between the experience you had in version `2` of Silverstripe Search Service, and what you now have in version
`3` with the addition of version `1` of Silverstripe Search Service - Elastic.

```bash
composer require silverstripe/silverstripe-search-service-elastic
```

## Interface change

`IndexingInterface` now requires you to define an `environmentizeIndex()` method. This used to be provided by a static
method on `EnterpriseSearchService`, but of course, that no longer exists in this module, so we now reference the
local method that you will create in your service.
