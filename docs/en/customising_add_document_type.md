# Customising and extending: Adding a new document type

A document type represents a source of content that is indexed in the search service. There are
several requirements for registering a new source of content.

* It must be defined as a PHP class
* It must at a minimum implement the `DocumentInterface` (along with others depending on use cases)
* You must create a fetching service
* You must register a fetch creator with the `DocumentFetchCreatorRegistry`

A good point of reference is the `DataObjectDocument` concretion that comes with the module.

Let's look at this piece by piece.

## The content must be backed by a PHP class

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

## The DocumentInterface spec

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

###  getIdentifier(): string

This should return a unique identifier for the document. You can use a UUID library for this if
you want opaque identifiers, or use something more transparent. It just has to be unique across
the entire search platform (not just indexes).

### shouldIndex(): bool

If this method returns true, it is allowed in indexes. If not, it should not only be blocked
from insertion into the index, but also removed if it exists.

### markIndexed(): void

Documents are largely stateless, but they should preserve their indexed status. This can
be a date or a boolean value, or you can just leave this as a noop. This is mostly useful
in the bulk indexing tasks that rely on when the document was last indexed.

### toArray(): array

Converts the document into an array, mapping field names to their values. While these
arrays are not required to be one-dimensional, it should be noted that many search services
require them to be, and nested data should be flattened.

Required metadata such as `id` should **not** be included in this result. That is the responsibility
of `DocumentBuilder`. As such, this method should never be called directly. It should always be 
the responsibility of `DocumentBuilder` to invoke this method.

### getSourceClass(): string

Gets the name of the class that contains the content provided to this document. For example,
`DataObjectDocument` returns the `ClassName` property of the DataObject that was passed to
its constructor.

## Create a fetching service

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

### fetch(int $limit, int $offset): array

This should return a list of the source content objects given the pagination parameters.

### getTotalDocuments(): int

This method should return the total number of documents to fetch. It is used for batching
jobs, particularly in bulk indexing.

### createDocument(array $data): ?DocumentInterface

When a document is fetched from the search service, it comes back in a plain array structure.
This method converts that data to a first-class `DocumentInterface`.

## Register a fetch creator

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

### appliesTo(string $className): bool

Returns true if this fetcher should be used for the given class.

### createFetcher(string $class, ?int $until = null): DocumentFetcherInterface

Given a `$class` parameter and a cutoff time for the source content, create the
appropriate `DocumentFetcherInterface` instance.

The fetch creator can now be registered.

```yaml
  SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry:
    constructor:
      fileContent: '%$MyProject\MyApp\FileDocumentFetchCreator'
```

## More information

* [Adding a new search service](customising_add_search_service.md)
* [More customisations](customising_more.md)
