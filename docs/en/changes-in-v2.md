# Changes in Version 2

## Preamble

If your existing implementation of this module simply uses the out of the box extensions, jobs, and tasks, without
any customisations, then it's very likely that the changes to Environment Variable names would be the only change
required for you.

## Breaking changes

### PHP

Minimum PHP requirement has been increased to PHP 8.

### Environment variables

Usages of the name `APP` has been replaced with `ENTERPRISE`.

Before:
```yml
APP_SEARCH_ENGINE_PREFIX=""
APP_SEARCH_API_KEY=""
APP_SEARCH_API_SEARCH_KEY=""
APP_SEARCH_ENDPOINT=""
```

After:
```yml
ENTERPRISE_SEARCH_ENGINE_PREFIX=""
ENTERPRISE_SEARCH_API_KEY=""
ENTERPRISE_SEARCH_API_SEARCH_KEY=""
ENTERPRISE_SEARCH_ENDPOINT=""
```

### Method return types

Some methods previously returned `void` or `$this`, which meant they didn't give any useful feedback from the request
we had just made.

Methods like `EnterpriseSearchService::configure()` now return info about the response (in this case, an array of the
active configuration for each index).

Methods with changed return types:

* `IndexingInterface::configure()`
* `IndexingInterface::addDocument()`
* `IndexingInterface::removeDocument()`
* `BatchDocumentInterface::addDocuments()`
* `BatchDocumentInterface::removeDocuments()`

### Pretty much every property/parameter everywhere

I think we've been able to keep the general API for all methods (that being, the order and purpose of params) the same,
but a lot of methods have had type declarations added, so if you are extended these classes and overriding methods,
then these would likely be breaking changes for you.

Where possible, properties have been given type hints (where they didn't previously). Exceptions are the usual
properties that extend some other vendor classes that they have to match.

### `NaiveSearchService`

Practically, I don't think anyone would have been accessing this class, but the namespace has changed to be consistent
with other classes.

Before: `SilverStripe\SearchService\Services\Naive\NaiveSearchService`
After: `SilverStripe\SearchService\Service\Naive\NaiveSearchService`

## Maybe breaking

### Default HTTP Client

We **don't think** this will be a breaking change, as the old App Search already explicitly used `Guzzle` as its HTTP
Client. However, the new Enterprise search uses PSR-18 "discovery". We found this to be a bit too fragile (EG: It was
breaking with Symfony Client), so we've added a new config to the `ClientFactory` to allow folks to specify what client
they would like instantiated. Default is `Guzzle`.

```yml
SilverStripe\Core\Injector\Injector:
  Elastic\EnterpriseSearch\Client:
    factory: SilverStripe\SearchService\Service\EnterpriseSearch\ClientFactory
    constructor:
      # original configs
      host: '`ENTERPRISE_SEARCH_ENDPOINT`'
      token: '`ENTERPRISE_SEARCH_API_KEY`'
      # new config
      http_client: '%$GuzzleHttp\Client'
```

The Elastic Enterprise dependency seems to be tested on Guzzle specifically though, so any change here would need to be
covered by your own testing.

### `recordBaseClass` and `pageContent`

There were two conflicting configs, one defined these fields in snake case (EG: `record_base_class`), and the other
defined them in camel case (EG: `recordBaseClass`). I've standardised all configs to snake case.

Looking through some of my own projects, we seem to use the snake case, so this might not be a breaking change.

## New features

### New Permission for triggering ReIndex through Service Admin

`SearchAdmin_ReIndex` permission has been added so that it's not longer only ADMIN who are able to trigger a full
ReIndex through the Model Admin area.
