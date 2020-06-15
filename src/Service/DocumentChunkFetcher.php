<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use InvalidArgumentException;

class DocumentChunkFetcher
{
    use Injectable;

    /**
     * @var DocumentFetcherInterface
     */
    private $fetcher;

    /**
     * DocumentChunkFetcher constructor.
     * @param DocumentFetcherInterface $fetcher
     */
    public function __construct(DocumentFetcherInterface $fetcher)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * @param int $chunkSize
     * @return iterable
     * @see https://github.com/silverstripe/silverstripe-framework/pull/8940/files
     */
    public function chunk(int $chunkSize = 100): iterable
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException(sprintf(
                '%s::%s: chunkSize must be greater than or equal to 1',
                __CLASS__,
                __METHOD__
            ));
        }

        $currentChunk = 0;
        while ($chunk = $this->fetcher->fetch($chunkSize, $chunkSize * $currentChunk)) {
            foreach ($chunk as $item) {
                yield $item;
            }

            if (sizeof($chunk) < $chunkSize) {
                break;
            }
            $currentChunk++;
        }
    }
}
