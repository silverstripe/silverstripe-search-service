# Customising and Extending

The goal of this module is to provide a set of useful abstraction layers upon which developers
can build search-as-a-service integrations that best suit their needs. Out of the box, it
includes concretions for Elastic AppSearch and DataObject content, but this can be extended.
This section of the document covers the customisation and extension of those abstraction
layers.

## Adding a new search service

There are two fundamental requirements for creating a new search service integration. It must:

* Implement the `IndexingInterface` specification
* Be registered in `Injector` as the concretion for `IndexingInterface`

Let's walk through this bit by bit. 

### The IndexingInterface specification

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

#### addDocument(DocumentInterface $item): self

This method is responsible for adding a single document to the indexes. Keep in mind, the
`DocumentInterface` object that is passed to this function is self-aware of the indexes
it is assigned to. Be sure to check each item's `shouldIndex()` method, as well.

```php
public function addDocument(DocumentInterface $item): self
{
    if (!$item->shouldIndex()) {
        return $this;
    }
    
    $fields = DocumentBuilder::singleton()->toArray($item);
    $indexes = IndexConfiguration::singleton()->getIndexesForDocument($item);
    foreach (array_keys($indexes) as $indexName) {
        // your custom API call here
        $mySearchClient->addDocuementToIndex(
            static::environmentizeIndex($indexName),
            $fields
        );   
    }

}
```

**Tip**: Consider passing `DocumentBuilder` and `IndexConfiguration` as a constructor 
arguments to your indexing service.

#### addDocuments(array $items): self

Same as `addDocument()`, but accepts an array of `DocumentInterface` objects. It is recommended
that the `addDocument()` method works as a proxy for `addDocuments()`, e.g. 
`$this->addDocuments([$item])`.

**Tip**: Build a map of index names to documents to minimise calls to your API.

```php
[
    'index-1' => [$doc1, $doc2],
    'index-2' => [$doc1, $doc3, $doc8],
]
```

#### removeDocument(DocumentInterface $doc): self

Removes a document from its indexes.

```php  
public function removeDocument(DocumentInterface $doc): self
{
    $indexes = IndexConfiguration::singleton()->getIndexesForDocument($doc);
    foreach (array_keys($indexes) as $indexName) {
        // your custom API call here
        $myAPI->removeDocumentFromIndex(
            static::environmentizeIndex($indexName),
            $item->getIdentifier()
        );
    }

    return $this;
}
```

#### removeDocuments(array $items): self

Same as `removeDocument()`, but accepts an array of `DocumentInterface` objects. It is recommended
that the `removeDocument()` method works as a proxy for `removeDocuments()`, e.g. 
`$this->removeDocuments([$item])`.

**Tip**: Build a map of index names to documents to minimise calls to your API.

```php
[
    'index-1' => [$id1, $id2],
    'index-2' => [$id1, $id3, $id8],
]
```

#### getDocument(string $id): ?array

Gets a single document from an index. Should check each index and get the first one to match it.

```php
public function getDocument(string $id): ?array
{
    foreach (array_keys(IndexConfiguration::singleton()->getIndexes()) as $indexName) {
        // Your API call here
        $result = $myAPI->retrieveDocumentFromIndex(        
            static::environmentizeIndex($indexName),
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


#### getDocuments(array $ids): self

Same as `getDocument()`, but accepts an array of identifiers. It is recommended
that the `getDocument()` method works as a proxy for `rgetDocuments()`, e.g. 
`$this->getDocuments([$id])`.

#### listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array

This method is expected to list all documents in a given index, with some pagination
parameters.

```php
public function listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array
{
    // Your API call here    
    return $myAPI->listDocuments(
        static::environmentizeIndex($indexName),
        $offset,
        $limit
    );
}
```

#### getDocumentTotal(string $indexName): int

This method is expected to return the total number of documents in an index.

```php
public function getDocumentTotal(string $indexName): int
{
    // Your API call here
    $response = $myAPI->listDocuments(
        static::environmentizeIndex($indexName)
    );

    return $response['metadata']['total'];
}
```

#### configure(): void

A catch-all implementation that handles configuring the search service during the build
step. The build step is invoked during `dev/build` or explicitly in the `SearchConfigure` task.

Configuration can include operations like creating/removing indexes, defining a schema, and more.

This method should rely heavily on the `IndexConfiguration` class to guide its operations, along
with the `getOptions()` method of the `Field` objects, which can be used for adding arbitrary 
configuration data to the index (e.g. data types).

```php
public function configure(): void
{
    foreach ($indexesToCreate as $index) {
         $myAPI->createIndex(static::environmentizeIndex($index));
    }   
}
```

#### validateField(string $field): void

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


## Adding a new document type

A document type represents a source of content that is indexed in the search service. There are
several requirements for registering a new source of content.

* It must be defined as a PHP class
* It must at a minimum implement the `DocumentInterface` (along with others depending on use cases)
* You must create a fetching service
* You must register a fetch creator with the `DocumentFetchCreatorRegistry`

A good point of reference is the `DataObjectDocument` concretion that comes with the module.

Let's look at this piece by piece.

### The content must be backed by a PHP class

This may sound obvious, but you need to abstract the content you're creating into a PHP class.
It is not sufficient, for example, to use a flat file as a content source. You would need to 
create a class that interacts with that file.

Example:
```php
class FileContent
{
    private $absPath;
    private $fileContent;
    private $filename;

    public function __construct($absFilePath)
    {
        $this->absPath = $absFilePath;
        $this->fileContent = file_get_contents($absFilePath);
        $this->filename = basename($absPath);
    }
    
    public function getAbsPath(): string
    {
        return $this->absPath;
    }   
    
    public function getFilename(): string
    {
        return $this->filename;
    }
    
    public function getFileContent(): string
    {
        return $this->fileContent;
    }
}
```

### The DocumentInterface spec

The `DocumentInterface` is the fundamental contract that documents must adhere to in order
to work within this system.

Example:
```php
class FileDocument implements DocumentInterface
{
    private $file;

    public function __construct(FileContent $file)
    {
        $this->file = $file;
    }

    public function getIdentifier(): string
    {
        return $this->file->getAbsPath();
    }

    public function shouldIndex(): bool
    {
        return !dirname($this->file->getAbsPath()) === '__private';
    }

    public function markIndexed(): void
    {
        $fh = fopen('/path/to/state.txt', 'a');
        fwrite($fh, $this->getIdentifier() . ' - ' . time());
    }

    public function toArray(): array
    {
        /*
         * includedClasses:
         *   FileContent:
         *     fields:
         *       name:
         *         property: Filename
         *       content:
         *         property: FileContent
         */
        $fields = IndexConfiguration::singleton()->getFieldsForClass(
            $this->getSourceClass()
        );
        $data = [];
        foreach ($fields as $field) {
            // e.g. "title"
            $name = $field->getSearchFieldName();
            // e.g. "FileContent"
            $prop = $field->getProperty();
            // e.g. 'getFileContent()'
            $method = 'get' . $prop;
            $data[$name] = $this->file->$method();
        }
    
        return $data;
    }

    public function getSourceClass(): string
    {
        return get_class($this->file);
    }
```

Let's look at the methods that are required:

####  getIdentifier(): string

This should return a unique identifier for the document. You can use a UUID library for this if
you want opaque identifiers, or use something more transparent. It just has to be unique across
the entire search platform (not just indexes).

#### shouldIndex(): bool

If this method returns true, it is allowed in indexes. If not, it should not only be blocked
from insertion into the index, but also removed if it exists.

#### markIndexed(): void

Documents are largely stateless, but they should preserve their indexed status. This can
be a date or a boolean value, or you can just leave this as a noop. This is mostly useful
in the bulk indexing tasks that rely on when the document was last indexed.

#### toArray(): array

Converts the document into an array, mapping field names to their values. While these
arrays are not required to be one-dimensional, it should be noted that many search services
require them to be, and nested data should be flattened.

Required metadata such as `id` should **not** be included in this result. That is the responsibility
of `DocumentBuilder`. As such, this method should never be called directly. It should always be 
the responsibility of `DocumentBuilder` to invoke this method.

#### getSourceClass(): string

Gets the name of the class that contains the content provided to this document. For example,
`DataObjectDocument` returns the `ClassName` property of the DataObject that was passed to
its constructor.

### Create a fetching service

A fetching service is responsible for three things:

* Getting the content for documents given some parameters
* Reporting metadata about the content source
* Creating a document instance based on an array of data coming from the search service

Fetching services must implement the `DocumentFetcherInterface` specification.

Let's look at this piece by piece. Imagine we have a content source that comes from
flat files.

```php
class FileDocumentFetcher implements DocumentFetcherInterface
{
    private $cutoffTime;

    public function __construct(int $until)
    {
        $this->cutoffTime = $until;
    }

    public function fetch(int $limit, int $offset): array
    {
        $files = $this->getFilePaths('/path/to/dir', $limit, $offset);
        $files = array_filter(function($path) {
            return filemtime($path) < $this->cutoffTime;
        }, $files);
        return array_map(function ($path) {
            return new FileDocument(new FileContent($path));
        }, $files);
    }
    
    public function getTotalDocuments(): int
    {
        return count($this->getFilePaths('/path/to/dir', null, 0));
    }
    
    public function createDocument(array $data): ?DocumentInterface
    {
        $path = $data['id'];
        if (!file_exists($path)) {
            return null;
        }

        return new FileDocument(new FileContent($path));   
    }  
}
```

#### fetch(int $limit, int $offset): array

This should return a list of the source content objects given the pagination parameters.

#### getTotalDocuments(): int

This method should return the total number of documents to fetch. It is used for batching
jobs, particularly in bulk indexing.

#### createDocument(array $data): ?DocumentInterface

When a document is fetched from the search service, it comes back in a plain array structure.
This method converts that data to a first-class `DocumentInterface`.

### Register a fetch creator

Another layer to the fetching involves mapping a given class name to a fetching service.
These creators must implement the `DocumentFetchCreatorInterface` specification.

Let's follow on with our example.

```php
class FileDocumentFetchCreator implements DocumentFetchCreatorInterface
{
    public function appliesTo(string $className): bool
    {
        return $className === FileContent::class || is_subclass_of($className, FileContent::class);
    }
   
    public function createFetcher(string $class, ?int $until = null): DocumentFetcherInterface
    {
        // You might check $class here to determine which fetcher to return
        return new FileDocumentFetcher($until);
    }
}
```

#### appliesTo(string $className): bool

Returns true if this fetcher should be used for the given class.

#### createFetcher(string $class, ?int $until = null): DocumentFetcherInterface

Given a `$class` parameter and a cutoff time for the source content, create the
appropriate `DocumentFetcherInterface` instance.

The fetch creator can now be registered.

```yaml
  SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry:
    constructor:
      fileContent: '%$MyProject\MyApp\FileDocumentFetchCreator'
```

## Event handling

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

## The DocumentBuilder service

The `DocumentBuilder` service should be used in all indexing service implementations to ensure
that the necessary metadata gets stamped on each document before it is shipped off to the index.
It has `toArray(): array` and `fromArray(): ?DocumentInterface` methods that either prepares a 
document for the search service, or interprets one that has been retrieved from it, respectively.
 
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
 

  
  
