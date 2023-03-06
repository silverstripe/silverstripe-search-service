# Customising and extending: Adding a new search service

There are two fundamental requirements for creating a new search service integration. It must:

* Implement the `IndexingInterface` specification
* Be registered in `Injector` as the concretion for `IndexingInterface`

Let's walk through this bit by bit.

## Index Configuration

`IndexConfiguration` is used in a singleton pattern throughout the service. Depending on your service you might also
need to define an index variant whenever `IndexConfiguration` is instantiated.

This could be done through setting the value of an environment variable to the contructor parameter.

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\SearchService\Service\IndexConfiguration:
    constructor:
      index_variant: '`SEARCH_ENGINE_PREFIX`'
```

## The IndexingInterface specification

There are several methods that a class must implement as part of this spec.

```php
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;

class MySearchProvider implments IndexingInterface
{
   // implementations here
}
```

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\SearchService\Interfaces\IndexingInterface:
    class: MyProject\MySearchProvider
``` 

In all the examples below, we do not have to worry about batching documents
into suitably sized chunks. That work is handled by another service (`Indexer`). Assume
in all these implementations that the array of documents is appropriately sized.

All methods that rely on API calls should throw `IndexingServiceException` on error.

### addDocument(DocumentInterface $document): self

This method is responsible for adding a single document to the indexes. Keep in mind, the
`DocumentInterface` object that is passed to this function is self-aware of the indexes
it is assigned to. Be sure to check each item's `shouldIndex()` method, as well.

Return value should be the unique ID of the document.

```php
public function addDocument(DocumentInterface $document): ?string
{
    if (!$document->shouldIndex()) {
        return null;
    }
    
    $fields = DocumentBuilder::singleton()->toArray($document);
    $indexes = IndexConfiguration::singleton()->getIndexesForDocument($document);
    
    foreach (array_keys($indexes) as $indexName) {
        // your custom API call here
        $mySearchClient->addDocuementToIndex(
            $this->environmentizeIndex($indexName),
            $fields
        );   
    }
    
    return $document->getIdentifier();

}
```

**Tip**: Consider passing `DocumentBuilder` and `IndexConfiguration` as a constructor 
arguments to your indexing service.

### addDocuments(array $documents): self

Same as `addDocument()`, but accepts an array of `DocumentInterface` objects. It is recommended
that the `addDocument()` method works as a proxy for `addDocuments()`, e.g. 
`$this->addDocuments([$document])`.

**Tip**: Build a map of index names to documents to minimise calls to your API.

```php
[
    'index-1' => [$doc1, $doc2],
    'index-2' => [$doc1, $doc3, $doc8],
]
```

### removeDocument(DocumentInterface $doc): ?string

Removes a document from its indexes.

Return value should be the unique ID of the document.

```php
public function removeDocument(DocumentInterface $document): ?string
{
    $indexes = IndexConfiguration::singleton()->getIndexesForDocument($document);
    
    foreach (array_keys($indexes) as $indexName) {
        // your custom API call here
        $myAPI->removeDocumentFromIndex(
            $this->environmentizeIndex($indexName),
            $document->getIdentifier()
        );
    }

    return $document->getIdentifier();
}
```

### removeDocuments(array $documents): array

Same as `removeDocument()`, but accepts an array of `DocumentInterface` objects. It is recommended
that the `removeDocument()` method works as a proxy for `removeDocuments()`, e.g. 
`$this->removeDocuments([$document])`.

Return value should be an array of the Document IDs that were removed

**Tip**: Build a map of index names to documents to minimise calls to your API.

```php
[
    'index-1' => [$id1, $id2],
    'index-2' => [$id1, $id3, $id8],
]
```

### getDocument(string $id): ?DocumentInterface

Gets a single document from an index. Should check each index and get the first one to match it.

```php
public function getDocument(string $id): ?array
{
    foreach (array_keys(IndexConfiguration::singleton()->getIndexes()) as $indexName) {
        // Your API call here
        $result = $myAPI->retrieveDocumentFromIndex(        
            $this->environmentizeIndex($indexName),
            $id
        );
        
        if ($result) {
            return DocumentBuilder::singleton()->fromArray($result);
        }       
    }
    
    return null;
}

```

**Tip**: Consider passing `DocumentBuilder`  and `IndexConfiguration` as a constructor arguments
 to your indexing service. (See the `ConfigurationAware` trait).


### getDocuments(array $ids): array

Same as `getDocument()`, but accepts an array of identifiers. It is recommended
that the `getDocument()` method works as a proxy for `rgetDocuments()`, e.g. 
`$this->getDocuments([$id])`.

return type should be an array of `DocumentInterface`.

### listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array

This method is expected to list all documents in a given index, with some pagination
parameters.

return type should be an array of `DocumentInterface`.

```php
public function listDocuments(string $indexName, ?int $pageSize = null, int $currentPage = 0): array
{
    // Your API call here    
    $request = new ListDocuments($this->environmentizeIndex($indexName));
    $request->setPageSize($pageSize);
    $request->setCurrentPage($currentPage);
    
    $response = $this->getClient()->appSearch()
        ->listDocuments($request)
        ->asArray();
        
    // Convert your reponse body into DocumentInterface objects
    $documents = [];
    
    return $documents;
}
```

### getDocumentTotal(string $indexName): int

This method is expected to return the total number of documents in an index.

```php
public function getDocumentTotal(string $indexName): int
{
    // Your API call here
    $response = $myAPI->listDocuments(
        $this->environmentizeIndex($indexName)
    );

    return $response['metadata']['total'];
}
```

### configure(): void

A catch-all implementation that handles configuring the search service during the build
step. The build step is invoked during `dev/build` or explicitly in the `SearchConfigure` task.

Configuration can include operations like creating/removing indexes, defining a schema, and more.

This method should rely heavily on the `IndexConfiguration` class to guide its operations, along
with the `getOptions()` method of the `Field` objects, which can be used for adding arbitrary 
configuration data to the index (e.g. data types).

Return value should be an array describing the current Schema for each index.

```php
public function configure(): array
{
    foreach ($indexesToCreate as $index) {
         $myAPI->createIndex($this->environmentizeIndex($index));
    }   
}
```

### validateField(string $field): void

Validate that the field is acceptable for the search service. If not, throw 
`IndexConfigurationException`.

```php
public function validateField(string $field): void
{
    if (!preg_match('/[a-z0-9]+/', $field)) {
        throw new IndexConfigurationException('Fields can only be lowercase and numbers');
    }
}
```

## The DocumentBuilder service

The `DocumentBuilder` service should be used in all indexing service implementations to ensure
that the necessary metadata gets stamped on each document before it is shipped off to the index.
It has `toArray(): array` and `fromArray(): ?DocumentInterface` methods that either prepares a 
document for the search service, or interprets one that has been retrieved from it, respectively.

## More information

* [Adding a new document type](customising_add_document_type.md)
* [More customisations](customising_more.md)
 

