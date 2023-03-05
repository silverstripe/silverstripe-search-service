# Breaking changes in `3.0.0`

Support for Elastic Enterprise Search is now supplied through
[Silverstripe Search Service - Elastic](https://github.com/silverstripe/silverstripe-search-service-elastic).

This was (moreorless) just a lift and shift. There were namespace and linting changes, but otherwise there should be
no breaking changes between the experience you had in version `2` of Silverstripe Search Service, and what you now
have in version `3` with the addition of version `1` of Silverstripe Search Service - Elastic.

```bash
composer require silverstripe/silverstripe-search-service-elastic
```
