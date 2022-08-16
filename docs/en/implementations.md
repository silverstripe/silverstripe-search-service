# Implementations

This module is a set of abstractions for creating search-as-a-service integrations. This section
of the documentation covers the details of each one.

## Naive search

This is the service that is enabled by default. It does not interact with any specific service, and is
there to fill the whole in the abstraction layer when search is not yet being used. It is also a good option
to have enabled when running tests and/or doing CI builds.

## Elastic EnterpriseSearch

This module comes bundled with an implementation for [Elastic EnterpriseSearch](https://www.elastic.co/app-search/).
While most of the details are abstracted away in the `EnterpriseSearchService` class, there are some key things to
know about configuring EnterpriseSearch.

### Activating EnterpriseSearch

To start using EnterpriseSearch, simply define an environment variable containing your private API key
and endpoint.

```
ENTERPRISE_SEARCH_ENDPOINT="https://abc123.app-search.ap-southeast-2.aws.found.io"
ENTERPRISE_SEARCH_API_KEY="private-abc123"
```

### Configuring EnterpriseSearch

The most notable configuration surface for EnterpriseSearch is the schema, which determines how data
is stored in your EnterpriseSearch index (engine). There are four types of data in EnterpriseSearch:

* Text (default)
* Date
* Number
* Geolocation

You can specify these data types in the `options` node of your fields.

```yaml
SilverStripe\SearchService\Service\IndexConfiguration:
  indexes:
    myindex:
      includeClasses:
        SilverStripe\CMS\Model\SiteTree:
          fields:
            title:
              property: Title
              options:
                type: text
```

**Note**: Be careful about whimsically changing your schema. EnterpriseSearch may need to be fully
reindexed if you change the data type of a field.

## More information

* [Usage](usage.md)
* [Configuration](configuration.md)
* [Customising and extending](customising.md)
* [Overview and Rationale](overview.md)
