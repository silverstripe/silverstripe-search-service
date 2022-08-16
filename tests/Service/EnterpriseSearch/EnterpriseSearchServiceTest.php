<?php

namespace SilverStripe\SearchService\Tests\Service\EnterpriseSearch;

use Elastic\EnterpriseSearch\AppSearch\Schema\SchemaUpdateRequest;
use Elastic\EnterpriseSearch\Client as ElasticClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Page;
use ReflectionMethod;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\EnterpriseSearch\EnterpriseSearchService;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\DataObjectFakePrivate;
use SilverStripe\SearchService\Tests\Fake\DataObjectFakeVersioned;
use SilverStripe\SearchService\Tests\Fake\ImageFake;
use SilverStripe\SearchService\Tests\Fake\TagFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;
use SilverStripe\Security\Member;

class EnterpriseSearchServiceTest extends SearchServiceTest
{

    protected static $fixture_file = '../../fixtures.yml'; // phpcs:ignore

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
        DataObjectFakePrivate::class,
        DataObjectFakeVersioned::class,
        TagFake::class,
        ImageFake::class,
        Member::class,
    ];

    protected ?MockHandler $mock;

    protected EnterpriseSearchService $searchService;

    public function testMaxDocumentSize(): void
    {
        EnterpriseSearchService::config()->set('max_document_size', 100);

        $this->assertEquals(100, $this->searchService->getMaxDocumentSize());
    }

    public function testGetExternalURL(): void
    {
        Environment::setEnv('ENTERPRISE_SEARCH_ENDPOINT', null);

        $this->assertNull($this->searchService->getExternalURL());

        Environment::setEnv('ENTERPRISE_SEARCH_ENDPOINT', 'https://api.elastic.com');

        $this->assertEquals('https://api.elastic.com', $this->searchService->getExternalURL());
    }

    public function testEnvironmentizeIndex(): void
    {
        // Setting this to null to check that the "no prefix version works
        IndexConfiguration::singleton()->setIndexVariant(null);

        // No change to our indexName should be made
        $this->assertEquals('content', EnterpriseSearchService::environmentizeIndex('content'));

        // Setting this back to a value to check the appending words
        IndexConfiguration::singleton()->setIndexVariant('dev-test');

        // Our indexName should now be updated to include our environment name
        $this->assertEquals('dev-test-content', EnterpriseSearchService::environmentizeIndex('content'));
    }

    /**
     * @dataProvider provideFieldsForValidation
     */
    public function testValidateField(string $fieldName, bool $shouldBeValid): void
    {
        if (!$shouldBeValid) {
            $this->expectExceptionMessage('Invalid field name');
        } else {
            $this->expectNotToPerformAssertions();
        }

        $this->searchService->validateField($fieldName);
    }

    public function provideFieldsForValidation(): array
    {
        return [
            [
                'title',
                true,
            ],
            [
                'title_two',
                true,
            ],
            [
                'title_2',
                true,
            ],
            [
                '_title',
                false,
            ],
            [
                'Title_two',
                false,
            ],
            [
                'title-2',
                false,
            ],
        ];
    }

    public function testSchemaRequiresUpdateFalse(): void
    {
        $definedSchema = new SchemaUpdateRequest();
        $definedSchema->title = 'text';
        $definedSchema->html_text = 'text';
        $definedSchema->first_name = 'text';
        $definedSchema->surname = 'text';

        // There is an extra field in Elastic vs our configuration, but that isn't something that would trigger an
        // update
        $elasticSchema = [
            'title' => 'text',
            'html_text' => 'text',
            'first_name' => 'text',
            'surname' => 'text',
            'extra' => 'text',
        ];

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'schemaRequiresUpdate');
        $reflectionMethod->setAccessible(true);

        // There are no differences (that we care about), so we would expect this to be false
        $this->assertFalse($reflectionMethod->invoke($this->searchService, $definedSchema, $elasticSchema));
    }

    public function testSchemaRequiresUpdateNewField(): void
    {
        // There is an extra (new) field in our defined Schema, so we should expect to need an update
        $definedSchema = new SchemaUpdateRequest();
        $definedSchema->title = 'text';
        $definedSchema->html_text = 'text';
        $definedSchema->first_name = 'text';
        $definedSchema->surname = 'text';
        $definedSchema->extra = 'text';

        $elasticSchema = [
            'title' => 'text',
            'html_text' => 'text',
            'first_name' => 'text',
            'surname' => 'text',
        ];

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'schemaRequiresUpdate');
        $reflectionMethod->setAccessible(true);

        // There are no differences (that we care about), so we would expect this to be false
        $this->assertTrue($reflectionMethod->invoke($this->searchService, $definedSchema, $elasticSchema));
    }

    public function testSchemaRequiresUpdateUpdatedField(): void
    {
        // We have changed the surname field to be type "number", so we would expect this to require an update
        $definedSchema = new SchemaUpdateRequest();
        $definedSchema->title = 'text';
        $definedSchema->html_text = 'text';
        $definedSchema->first_name = 'text';
        $definedSchema->surname = 'number';

        $elasticSchema = [
            'title' => 'text',
            'html_text' => 'text',
            'first_name' => 'text',
            'surname' => 'text',
        ];

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'schemaRequiresUpdate');
        $reflectionMethod->setAccessible(true);

        // There are no differences (that we care about), so we would expect this to be false
        $this->assertTrue($reflectionMethod->invoke($this->searchService, $definedSchema, $elasticSchema));
    }

    public function testGetSchemaForFields(): void
    {
        $expectedSchema = [
            'title' => 'text',
            'html_text' => 'text',
            'first_name' => 'text',
            'surname' => 'text',
        ];

        $fields = $this->searchService->getConfiguration()->getFieldsForIndex('content');

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'getSchemaForFields');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which should simply result in [no exceptions being thrown]
        $resultSchema = $reflectionMethod->invoke($this->searchService, $fields);

        $this->assertEquals($expectedSchema, (array) $resultSchema);
    }

    public function testValidateIndex(): void
    {
        // The default IndexConfiguration that we've defined in setUp() is valid, so we would expect this to work
        // without throwing any exception
        $this->expectNotToPerformAssertions();

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'validateIndex');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which should simply result in [no exceptions being thrown]
        $reflectionMethod->invoke($this->searchService, 'content');
    }

    public function testValidateIndexInvalidType(): void
    {
        // We're going to set a new IndexConfiguration which has an invalid type specified. When we run validateIndex()
        // we would expect this exception to be thrown ("fail" being the name of the invalid type)
        $this->expectExceptionMessage('Invalid field type: fail');

        // The field configuration that we want to use for our classes and tests
        IndexConfiguration::config()->set(
            'indexes',
            [
                'content' => [
                    'includeClasses' => [
                        Page::class => [
                            'fields' => [
                                'title' => true,
                                'html_text' => [
                                    'property' => 'getDBHTMLText',
                                    'options' => [
                                        'type' => 'fail',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'validateIndex');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which should throw our expected Exception message
        $reflectionMethod->invoke($this->searchService, 'content');
    }

    public function testValidateIndexIncompatibleFields(): void
    {
        // We're going to set a new IndexConfiguration which has the same field defined twice with a different "type"
        // specified for each. This should result in an Exception being thrown, as one field can't be two different
        // types
        $this->expectExceptionMessage('Field "fail_field" is defined twice in the same index with differing types');

        // The field configuration that we want to use for our classes and tests
        IndexConfiguration::config()->set(
            'indexes',
            [
                'content' => [
                    'includeClasses' => [
                        Page::class => [
                            'fields' => [
                                'fail_field' => [
                                    'property' => 'getDBHTMLText',
                                    'options' => [
                                        'type' => 'date',
                                    ],
                                ],
                            ],
                        ],
                        DataObjectFake::class => [
                            'fields' => [
                                'fail_field' => [
                                    'property' => 'getDBHTMLText',
                                    'options' => [
                                        'type' => 'number',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'validateIndex');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which should throw our expected Exception message
        $reflectionMethod->invoke($this->searchService, 'content');
    }

    public function testFetchEngines(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing one engine called 'dev-test-content'
        $body = json_encode([
            'results' => [
                [
                    'name' => 'dev-test-content',
                    'type' => 'default',
                    'language' => 'en',
                    'document_count' => 0,
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'fetchEngines');
        $reflectionMethod->setAccessible(true);

        // We expect just the one engine
        $expectedEngines = [
            'dev-test-content',
        ];
        // Invoke our method which will trigger an API call
        $resultEngines = $reflectionMethod->invoke($this->searchService);

        $this->assertEqualsCanonicalizing($expectedEngines, $resultEngines);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testFetchEnginesEmptyResults(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content. It doesn't contain any engines, but that's still valid
        $body = json_encode([
            'results' => [],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'fetchEngines');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger an API call
        $resultEngines = $reflectionMethod->invoke($this->searchService);

        // Check that we successfully received no engines
        $this->assertEqualsCanonicalizing([], $resultEngines);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testFetchEnginesMissingResults(): void
    {
        $this->expectExceptionMessage('Invalid response format for listEngines; missing "results"');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, except it's missing the 'results' data
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 1,
                    'size' => 25,
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'fetchEngines');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger an API call. We expect this to throw an Exception
        $reflectionMethod->invoke($this->searchService);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testFetchEnginesError(): void
    {
        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content containing errors
        $body = json_encode([
            [
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'fetchEngines');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger an API call. We expect this to throw an Exception
        $reflectionMethod->invoke($this->searchService);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testFindOrMakeExists(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing one engine called 'dev-test-content'
        $body = json_encode([
            'results' => [
                [
                    'name' => 'dev-test-content',
                    'type' => 'default',
                    'language' => 'en',
                    'document_count' => 0,
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'findOrMakeIndex');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger an API call. We kinda just know that this is finding an existing
        // engine, because if it didn't, a second API call would be made, and that call would fail (because we've only
        // mocked one response)
        $reflectionMethod->invoke($this->searchService, 'dev-test-content');
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testFindOrMakeExistsError(): void
    {
        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content containing errors
        $body = json_encode([
            [
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'findOrMakeIndex');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger an API call. We expect this to throw an exception
        $reflectionMethod->invoke($this->searchService, 'dev-test-content');
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testFindOrMakeNew(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing no existing engines. This should trigger a second call to create the engine
        $bodyFirst = json_encode([
            'results' => [],
        ]);
        // Valid body content, containing our newly created engine called 'dev-test-content'
        $bodySecond = json_encode([
            'results' => [
                [
                    'name' => 'dev-test-content',
                    'type' => 'default',
                    'language' => 'en',
                    'document_count' => 0,
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $bodyFirst));
        $this->mock->append(new Response(200, $headers, $bodySecond));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'findOrMakeIndex');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger 2 API calls
        $reflectionMethod->invoke($this->searchService, 'dev-test-new-content');
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testFindOrMakeNewError(): void
    {
        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing no existing engines. This should trigger a second call to create the engine
        $bodyFirst = json_encode([
            'results' => [],
        ]);
        // Body content containing errors
        $bodySecond = json_encode([
            [
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $bodyFirst));
        $this->mock->append(new Response(200, $headers, $bodySecond));

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'findOrMakeIndex');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger 2 API calls, and we're expecting the second API call to trigger an error
        $reflectionMethod->invoke($this->searchService, 'dev-test-new-content');
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetContentMapForDocuments(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $documentTwo = $this->objFromFixture(DataObjectFake::class, 'two');
        $documentThree = $this->objFromFixture(DataObjectFake::class, 'three');

        $documents = [];
        // This document should be indexable
        $documents[] = DataObjectDocument::create($documentOne);
        // This document should NOT be indexable
        $documents[] = DataObjectDocument::create($documentTwo);
        // This document should be indexable
        $documents[] = DataObjectDocument::create($documentThree);

        $expectedMap = [
            'content' => [
                [
                    'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentOne->ID),
                    'title' => 'Dataobject one',
                    'html_text' => 'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
                    'record_base_class' => DataObjectFake::class,
                    'record_id' => $documentOne->ID,
                    'source_class' => DataObjectFake::class,
                ],
                [
                    'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentThree->ID),
                    'title' => 'Dataobject three',
                    'html_text' => 'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
                    'record_base_class' => DataObjectFake::class,
                    'record_id' => $documentThree->ID,
                    'source_class' => DataObjectFake::class,
                ],
            ],
        ];

        // This method is private, so we need Reflection to access it
        $reflectionMethod = new ReflectionMethod(EnterpriseSearchService::class, 'getContentMapForDocuments');
        $reflectionMethod->setAccessible(true);

        // Invoke our method which will trigger 2 API calls, and we're expecting the second API call to trigger an error
        $this->assertEquals($expectedMap, $reflectionMethod->invoke($this->searchService, $documents));
    }

    public function testConfigureNewField(): void
    {
        // Make sure our IndexConfiguration has our IndexVariant set
        IndexConfiguration::singleton()->setIndexVariant('dev-test');

        // Valid headers that we can use for each Request
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // First Response body is for the call being made as part of findOrMakeIndex()
        $firstBody = [
            'results' => [
                [
                    'name' => 'dev-test-content',
                    'type' => 'default',
                    'language' => 'en',
                    'document_count' => 0,
                ],
            ],
        ];
        // Second Response body is for the call to fetch the current Elastic Schema. We're going to exclude the
        // surname field, which should trigger an update
        $secondBody = [
            'title' => 'text',
            'html_text' => 'text',
            'first_name' => 'text',
            'page_content' => 'text',
        ];
        // Third Response body should be the new Schema after the requested change has been made by Elastic
        $thirdBody = [
            'title' => 'text',
            'html_text' => 'text',
            'first_name' => 'text',
            'surname' => 'text',
            'page_content' => 'text',
        ];

        // Expected Schemas at the end of all of that is just our "content" schema with exactly what we expect to get
        // from $thirdBody
        $expectedSchemas = [
            'content' => $thirdBody,
        ];

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, json_encode($firstBody)));
        $this->mock->append(new Response(200, $headers, json_encode($secondBody)));
        $this->mock->append(new Response(200, $headers, json_encode($thirdBody)));

        // Trigger our configure() action. There is no response as part of this, we're simply checking that no
        // Exception is thrown. We can also assume that no update is triggered if nothing breaks, because we've only
        // mocked 2 Responses, and a 3rd request would be made if we attempted to update
        $resultSchemas = $this->searchService->configure();

        // Check that our result matches the expected
        $this->assertEquals($expectedSchemas, $resultSchemas);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testConfigureNoUpdate(): void
    {
        // Make sure our IndexConfiguration has our IndexVariant set
        IndexConfiguration::singleton()->setIndexVariant('dev-test');

        // Valid headers that we can use for each Request
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // First Response body is for the call being made as part of findOrMakeIndex()
        $firstBody = [
            'results' => [
                [
                    'name' => 'dev-test-content',
                    'type' => 'default',
                    'language' => 'en',
                    'document_count' => 0,
                ],
            ],
        ];
        // Second Response body is for the call to fetch the current Elastic Schema
        $secondBody = [
            'title' => 'text',
            'html_text' => 'text',
            'first_name' => 'text',
            'surname' => 'text',
            'page_content' => 'text',
        ];

        // Expected Schemas at the end of all of that is just our "content" schema with exactly what we expect to get
        // from $secondBody
        $expectedSchemas = [
            'content' => $secondBody,
        ];

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, json_encode($firstBody)));
        $this->mock->append(new Response(200, $headers, json_encode($secondBody)));

        // Trigger our configure() action. We can assume that no update is triggered if nothing breaks, because we've
        // only mocked 2 Responses, and a 3rd request would be made if we attempted to update
        $resultSchemas = $this->searchService->configure();

        // Check that our result matches the expected
        $this->assertEquals($expectedSchemas, $resultSchemas);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetDocumentTotal(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing all the metadata we need. Results are not relevant for this method, as
        // they are never accessed
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 2,
                    'total_results' => 146,
                    'size' => 100,
                ],
            ],
            'results' => [],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $total = $this->searchService->getDocumentTotal('content');

        // Check that the total matches what was in the meta response
        $this->assertEquals(146, $total);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetDocumentTotalError(): void
    {
        // We're testing that this Exception is thrown if the expected metadata is missing
        $this->expectExceptionMessage('Total results not provided in meta content');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content that is missing the key piece of data that we require (total_results)
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 2,
                    'fail_total_results' => 146,
                    'size' => 100,
                ],
            ],
            'results' => [],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // This should trigger the exception to be thrown
        $this->searchService->getDocumentTotal('content');

        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testListDocuments(): void
    {
        $fakeOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $fakeTwo = $this->objFromFixture(DataObjectFake::class, 'two');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing the metadata for a couple of the DataObjects that are in our fixture
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 2,
                    'size' => 100,
                ],
            ],
            'results' => [
                [
                    'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $fakeOne->ID),
                    'record_id' => $fakeOne->ID,
                    'record_base_class' => DataObjectFake::class,
                    'source_class' => DataObjectFake::class,
                    'title' => 'Dataobject one',
                    'page_content' => '',
                ],
                [
                    'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $fakeTwo->ID),
                    'record_id' => $fakeTwo->ID,
                    'record_base_class' => DataObjectFake::class,
                    'source_class' => DataObjectFake::class,
                    'title' => 'Dataobject two',
                    'page_content' => '',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $expectedDocuments = [
            [
                'title' => 'Dataobject one',
                'html_text' => 'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
            ],
            [
                'title' => 'Dataobject two',
                'html_text' => 'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
            ],
        ];

        $documents = $this->searchService->listDocuments('content');

        // Check that the total matches what was in the meta response
        $this->assertCount(2, $documents);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());

        $resultDocuments = [];

        foreach ($documents as $document) {
            $resultDocuments[] = $document->toArray();
        }

        $this->assertEquals($expectedDocuments, $resultDocuments);
    }

    public function testListDocumentsEmpty(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with empty results
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 0,
                    'size' => 100,
                ],
            ],
            'results' => [],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $documents = $this->searchService->listDocuments('content');

        // Check that the total matches what was in the meta response
        $this->assertCount(0, $documents);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testListDocumentsError(): void
    {
        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content containing errors
        $body = json_encode([
            [
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // We expect this to throw an exception
        $this->searchService->listDocuments('content');

        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetDocuments(): void
    {
        $fakeOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $fakeTwo = $this->objFromFixture(DataObjectFake::class, 'two');

        $idOne = sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $fakeOne->ID);
        $idTwo = sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $fakeTwo->ID);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing the metadata for a couple of the DataObjects that are in our fixture
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 2,
                    'size' => 100,
                ],
            ],
            'results' => [
                [
                    'id' => $idOne,
                    'record_id' => $fakeOne->ID,
                    'record_base_class' => DataObjectFake::class,
                    'source_class' => DataObjectFake::class,
                    'title' => 'Dataobject one',
                    'page_content' => '',
                ],
                // Doubling this one up to check that we only get one
                [
                    'id' => $idTwo,
                    'record_id' => $fakeTwo->ID,
                    'record_base_class' => DataObjectFake::class,
                    'source_class' => DataObjectFake::class,
                    'title' => 'Dataobject two',
                    'page_content' => '',
                ],
                [
                    'id' => $idTwo,
                    'record_id' => $fakeTwo->ID,
                    'record_base_class' => DataObjectFake::class,
                    'source_class' => DataObjectFake::class,
                    'title' => 'Dataobject two',
                    'page_content' => '',
                ],
            ],
        ]);

        $expectedDocuments = [
            [
                'title' => 'Dataobject one',
                'html_text' => 'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
            ],
            [
                'title' => 'Dataobject two',
                'html_text' => 'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
            ],
        ];

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $documents = $this->searchService->getDocuments([$idOne, $idTwo]);

        // Check that the total matches what was in the meta response
        $this->assertCount(2, $documents);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());

        $resultDocuments = [];

        foreach ($documents as $document) {
            $resultDocuments[] = $document->toArray();
        }

        $this->assertEquals($expectedDocuments, $resultDocuments);
    }

    public function testGetDocumentsEmpty(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with empty results
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 0,
                    'size' => 100,
                ],
            ],
            'results' => [],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $documents = $this->searchService->getDocuments([123, 321]);

        // Check that the total matches what was in the meta response
        $this->assertCount(0, $documents);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetDocumentsError(): void
    {
        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content containing errors
        $body = json_encode([
            [
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // We expect this to throw an exception
        $this->searchService->getDocuments([123, 321]);

        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetDocument(): void
    {
        $fake = $this->objFromFixture(DataObjectFake::class, 'one');
        $id = sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $fake->ID);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content, containing the metadata for a couple of the DataObjects that are in our fixture
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 2,
                    'size' => 100,
                ],
            ],
            'results' => [
                // Doubling this one up to check that we only get one
                [
                    'id' => $id,
                    'record_id' => $fake->ID,
                    'record_base_class' => DataObjectFake::class,
                    'source_class' => DataObjectFake::class,
                    'title' => 'Dataobject one',
                    'page_content' => '',
                ],
                [
                    'id' => $id,
                    'record_id' => $fake->ID,
                    'record_base_class' => DataObjectFake::class,
                    'source_class' => DataObjectFake::class,
                    'title' => 'Dataobject one',
                    'page_content' => '',
                ],
            ],
        ]);

        $expectedDocument = [
            'title' => 'Dataobject one',
            'html_text' => 'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
        ];

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $resultDocument = $this->searchService->getDocument($id);

        // Check that the total matches what was in the meta response
        $this->assertNotNull($resultDocument);
        $this->assertEquals($expectedDocument, $resultDocument->toArray());
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetDocumentEmpty(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with empty results
        $body = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 0,
                    'size' => 100,
                ],
            ],
            'results' => [],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $document = $this->searchService->getDocument(123);

        // Check that there were no results (so we'd expect null for our one expected document)
        $this->assertNull($document);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testGetDocumentError(): void
    {
        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content containing errors
        $body = json_encode([
            [
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // We expect this to throw an exception
        $this->searchService->getDocument(123);

        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testAddDocuments(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $documentThree = $this->objFromFixture(DataObjectFake::class, 'three');

        $documents = [];
        $documents[] = DataObjectDocument::create($documentOne);
        $documents[] = DataObjectDocument::create($documentThree);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with our results
        $body = json_encode([
            [
                'id' => 'doc-123',
                'errors' => [],
            ],
            [
                'id' => 321, // We'll check that this is cast to string
                'errors' => [],
            ],
            [
                'id' => '321', // Should be removed as a duplicate of the above
                'errors' => [],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $expectedIds = [
            'doc-123',
            '321',
        ];

        $resultIds = $this->searchService->addDocuments($documents);

        $this->assertEqualsCanonicalizing($expectedIds, $resultIds);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testAddDocumentsEmpty(): void
    {
        // Adding an empty array of documents, we would expect no API calls to be made
        $resultIds = $this->searchService->addDocuments([]);

        // We would expect the results to be empty
        $this->assertEqualsCanonicalizing([], $resultIds);
    }

    public function testAddDocumentsError(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $documents = [DataObjectDocument::create($documentOne)];

        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content containing errors
        $body = json_encode([
            [
                'id' => 'doc-123',
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // We expect this to throw an Exception
        $this->searchService->addDocuments($documents);

        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testAddDocument(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $document = DataObjectDocument::create($documentOne);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with our single result
        $body = json_encode([
            [
                'id' => 'doc-123',
                'errors' => [],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $resultId = $this->searchService->addDocument($document);

        $this->assertEquals('doc-123', $resultId);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testAddDocumentEmpty(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $document = DataObjectDocument::create($documentOne);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with our single result
        $body = json_encode([]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // Kinda just checking that the array_shift correctly returns null if no results were presented from Elastic
        $resultId = $this->searchService->addDocument($document);

        $this->assertNull($resultId);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testRemoveDocuments(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $documentTwo = $this->objFromFixture(DataObjectFake::class, 'two');
        $documentThree = $this->objFromFixture(DataObjectFake::class, 'three');

        $documents = [];
        // This should be deleted
        $documents[] = DataObjectDocument::create($documentOne);
        // This should NOT be deleted (because it never existed)
        $documents[] = DataObjectDocument::create($documentTwo);
        // This should be deleted
        $documents[] = DataObjectDocument::create($documentThree);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with our results
        $body = json_encode([
            [
                'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentOne->ID),
                'deleted' => true,
            ],
            [
                'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentTwo->ID),
                'deleted' => false,
            ],
            [
                'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentThree->ID),
                'deleted' => true,
            ],
            [
                'id' => 123, // Test that int is cast to string
                'deleted' => true,
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $expectedIds = [
            sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentOne->ID),
            sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentTwo->ID),
            sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentThree->ID),
            '123',
        ];

        $resultIds = $this->searchService->addDocuments($documents);

        $this->assertEqualsCanonicalizing($expectedIds, $resultIds);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testRemoveDocumentsEmpty(): void
    {
        // Removing an empty array of documents, we would expect no API calls to be made
        $resultIds = $this->searchService->removeDocuments([]);

        // We would expect the results to be empty
        $this->assertEqualsCanonicalizing([], $resultIds);
    }

    public function testRemoveDocumentsError(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $documents = [DataObjectDocument::create($documentOne)];

        $this->expectExceptionMessage('Testing failure');

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Body content containing errors
        $body = json_encode([
            [
                'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentOne->ID),
                'errors' => [
                    'Testing failure',
                ],
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // We expect this to throw an Exception
        $this->searchService->removeDocuments($documents);

        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testRemoveDocument(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $document = DataObjectDocument::create($documentOne);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content with our single result
        $body = json_encode([
            [
                'id' => sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentOne->ID),
                'deleted' => true,
            ],
        ]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        $expectedId = sprintf('silverstripe_searchservice_tests_fake_dataobjectfake_%s', $documentOne->ID);

        $resultId = $this->searchService->removeDocument($document);

        $this->assertEquals($expectedId, $resultId);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testRemoveDocumentEmpty(): void
    {
        $documentOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $document = DataObjectDocument::create($documentOne);

        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // Valid body content but with no results
        $body = json_encode([]);

        // Append this mock response to our stack
        $this->mock->append(new Response(200, $headers, $body));

        // Kinda just checking that the array_shift correctly returns null if no results were presented from Elastic
        $resultId = $this->searchService->removeDocument($document);

        $this->assertNull($resultId);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    public function testRemoveAllDocuments(): void
    {
        // Valid headers
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        // First response, listing out the documents that are available (which we'll then remove)
        $bodyOne = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 2,
                    'size' => 3,
                ],
            ],
            'results' => [
                [
                    'id' => 'doc1',
                    'record_id' => '1',
                ],
                [
                    'id' => 'doc2',
                    'record_id' => '2',
                ],
                [
                    'id' => 'doc3',
                    'record_id' => '3',
                ],
            ],
        ]);
        // Second response is from our delete request. Adding a mix of deleted true/false. The way our "remove all"
        // feature works is that we request a list of all currently available documents, and then request that they
        // are removed by their IDs. It is possible that documents are removed through the Elastic Admin UI between
        // our two requests. deleted = false does *not* get added to the count of removed documents
        $bodyTwo = json_encode([
            [
                'id' => 'doc1',
                'deleted' => true,
            ],
            [
                'id' => 'doc2',
                'deleted' => false,
            ],
            [
                'id' => 'doc3',
                'deleted' => true,
            ],
        ]);
        // Third response, listing out the documents that are available after our delete request. We'll return some
        // more items to be deleted (testing that the loop functions)
        $bodyThree = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    // Two pages of results, 3 here, and then we'll have 2 more later
                    'total_pages' => 2,
                    // Total of 4 documents, 3 of which are presented now
                    'total_results' => 5,
                    'size' => 3,
                ],
            ],
            'results' => [
                [
                    'id' => 'doc1',
                    'record_id' => '1',
                ],
                [
                    'id' => 'doc2',
                    'record_id' => '2',
                ],
                [
                    'id' => 'doc3',
                    'record_id' => '3',
                ],
            ],
        ]);
        // Fourth response is from the second delete request
        $bodyFour = json_encode([
            [
                'id' => 'doc4',
                'deleted' => true,
            ],
            [
                'id' => 'doc5',
                'deleted' => true,
            ],
        ]);
        // Fifth (and final) response is for when we request available documents after our second delete request. We'll
        // return no results here, indicating that everything has been deleted
        $bodyFive = json_encode([
            'meta' => [
                'page' => [
                    'current' => 1,
                    'total_pages' => 1,
                    'total_results' => 0,
                    'size' => 100,
                ],
            ],
            'results' => [],
        ]);

        // Append our mocks
        $this->mock->append(new Response(200, $headers, $bodyOne));
        $this->mock->append(new Response(200, $headers, $bodyTwo));
        $this->mock->append(new Response(200, $headers, $bodyThree));
        $this->mock->append(new Response(200, $headers, $bodyFour));
        $this->mock->append(new Response(200, $headers, $bodyFive));

        $numRemoved = $this->searchService->removeAllDocuments('content');

        // A total of 5 documents were requested to be removed, but only 4 returned deleted = true
        $this->assertEqualsCanonicalizing(4, $numRemoved);
        // And make sure nothing is left in our Response Stack. This would indicate that every Request we expect to make
        // has been made
        $this->assertEquals(0, $this->mock->count());
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The field configuration that we want to use for our classes and tests
        IndexConfiguration::config()->set(
            'indexes',
            [
                'content' => [
                    'includeClasses' => [
                        Page::class => [
                            'fields' => [
                                'title' => true,
                            ],
                        ],
                        DataObjectFake::class => [
                            'fields' => [
                                'title' => true,
                                'html_text' => [
                                    'property' => 'getDBHTMLText',
                                ],
                            ],
                        ],
                        Member::class => [
                            'fields' => [
                                'first_name' => [
                                    'property' => 'FirstName',
                                ],
                                'surname' => true,
                            ],
                        ],
                    ],
                ],
            ]
        );
        IndexConfiguration::config()->set('crawl_page_content', false);

        // Set up a mock handler/client so that we can feed in mock responses that we expected to get from the API
        $this->mock = new MockHandler([]);
        $handler = HandlerStack::create($this->mock);
        $client = new GuzzleClient(['handler' => $handler]);

        $config = [
            'host' => 'https://api.elastic.com',
            'app-search' => [
                'token' => 'test-token',
            ],
            'Client' => $client,
        ];

        $elasticClient = new ElasticClient($config);
        $indexConfiguration = $this->mockConfig();
        $documentBuilder = Injector::inst()->get(DocumentBuilder::class);

        $this->searchService = EnterpriseSearchService::create($elasticClient, $indexConfiguration, $documentBuilder);
    }

}
