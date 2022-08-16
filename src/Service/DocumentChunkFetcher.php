<?php

namespace SilverStripe\SearchService\Service;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;

class DocumentChunkFetcher
{

    use Injectable;

    private ?DocumentFetcherInterface $fetcher;

    public function __construct(DocumentFetcherInterface $fetcher)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * @see https://github.com/silverstripe/silverstripe-framework/pull/8940/files
     */
    public function chunk(int $chunkSize = 100): iterable
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException(sprintf(
                '%s::%s: chunkSize must be greater than or equal to 1',
                self::class,
                __METHOD__
            ));
        }

        $currentChunk = 0;

        while ($chunks = $this->fetcher->fetch($chunkSize, $chunkSize * $currentChunk)) {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }

            if (sizeof($chunks) < $chunkSize) {
                break;
            }

            $currentChunk++;
        }
    }

}
