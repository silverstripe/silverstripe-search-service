<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\View\ViewableData;

class IndexedDocumentsResult extends ViewableData
{

    public string $IndexName;

    public int $DBDocs;

    public int $RemoteDocs;

    public function summaryFields(): array
    {
        return [
            'IndexName' => 'Index Name',
            'DBDocs' => 'Documents Indexed in Database',
            'RemoteDocs' => 'Documents Indexed Remotely',
        ];
    }

}
