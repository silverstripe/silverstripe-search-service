# Implementations

This module is a set of abstractions for creating search-as-a-service integrations. This section
of the documentation covers the details of each one.

## Elastic AppSearch

This module comes bundled with an implementation for [Elastic AppSearch](https://www.elastic.co/app-search/). While most of the details are abstracted away in the `AppSearchService` class, there are some key things to know about configuring AppSearch.

### Activating AppSearch

To start using AppSearch, simply define an environment variable containing your private API key
and endpoint.

```
APP_SEARCH_ENDPOINT="https://abc123.app-search.ap-southeast-2.aws.found.io"
APP_SEARCH_API_KEY="private-abc123"
```

### Configuring AppSearch

The most notable configuration surface for AppSearch is the schema, which determines how data
is stored in your AppSearch index (engine). There are four types of data in AppSearch:

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

**Note**: Be careful about whimsically changing your schema. AppSearch may need to be fully
reindexed if you change the data type of a field. 




