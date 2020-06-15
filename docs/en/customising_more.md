# Customising: More customisations

This section of the documentation covers less common customisations you may want to implement.

## Event Handling

The `Indexer` class will invoke event handlers for before/after addition/removal from indexes.
To handle these events, implement `DocumentRemoveHandler` and/or `DocumentAddHandler`.

```php
class FileDocument implements DocumentRemoveHandler, DocumentAddHandler
{
    public function onRemoveFromSearchIndexes(string $event): void
    {
        if ($event === DocumentRemoveHandler::BEFORE_REMOVE) {
            // do something here
        }
    }

    public function onAddToSearchIndexes(string $event): void
    {
        if ($event === DocumentAddHandler::AFTER_ADD) {
            // do something here
        }
    }
}
```
 
## Document meta

To add additional metadata to your document, you can implement the `DocummentMetaProvider`
interface.

```php
class FileDocument implements DocumentInterface, DocumentMetaProvider
{
    public function provideMeta(): array
    {
        return [
            'lastModified' => filemtime($this->file->getAbsPath());
        ]
    }
}
```

## Extension points

For DataObject implementations, there are several extension hooks you can use to
customise your results.

### updateSearchAttributes(&$attributes)

This method can be added to multiple extensions.

When an extension to `DataObjectDocument` can add this method to arbitrarily update all
 DataObject documents before they are handed off to the indexer. This method is called after the `DocumentBuilder` has applied its own metadata.
 
 When an extension to a DataObject has this method, it is used to update the document for
 just that record.
 
 ### canIndexInSearch(): bool
 
 A DataObject extension implementing this method can supply custom logic for determining
 if the record should be indexed.
 
 ### onBeforeAttributesFromObject(): void
 
 A DataObject extension implementing this method can carry out any side effects that should
 happen as a result of a DataObject being ready to go into the index. It is invoked before
 `DocumentBuilder` has processed the document.
 
 ### updateSearchDependentDocuments(&$dependentDocs): void
 
 A DataObject extension implementing this method can add dependent documents to the given list.
 This is particularly relevant if you're not using `auto_dependency_tracking`. It is important 
 to remember that `$dependentDocs` in this context should be first-class `DocumentInterface`
 instances, not DataObjects.
 
## More information

* [Adding a new search service](customising_add_search_service.md)
* [Adding a new document type](customising_add_document_type.md)
  
