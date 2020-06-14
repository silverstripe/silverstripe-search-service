# Overview and rationale

This module provides a set of abstraction layers that integrate the Silverstripe CMS content 
with a search-as-a-service provider, such as [Elastic](https://elastic.co) or [Algolia](https://algolia.com). The focus of this module
is on indexing content only. It does not provide any frontend tooling for UI nor APIs for
querying data.

## Defaults

Out of the box, an implementation is included for indexing DataObject based content
with [Elastic AppSearch](https://www.elastic.co/app-search/). ([More about the AppSearch implementation](implementations.md#appsearch))

## Extending

Additional search-as-a-service integrations can be added using the `IndexInterface` abstraction.
 Additional non-dataobject content types can also be exposed to indexes. They must be class-backed 
 and implement the `DocumentInterface` abstraction.

## Indexing workflow

Indexing can be very resource intensive, and as such, using [QueuedJobs](https://github.com/symbiote/silverstripe-queuedjobs) is required. Understanding that running asynchronous tasks can be cumbersome
in dev mode, there is a `use_sync_jobs` [configuration setting](configuration.md) that runs the jobs
synchronously, but this is not recommended for production.

## More information

* [Configuration](configuration.md)
* [Usage](usage.md)
* [Implementations](implementations.md)
* [Customising and extending](customising.md) 

