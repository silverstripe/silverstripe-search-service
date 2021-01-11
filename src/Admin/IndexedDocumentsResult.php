<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\View\ViewableData;

class IndexedDocumentsResult extends ViewableData
{
    public function summaryFields()
    {
        return [
            'IndexName' => 'Index Name',
            'DBDocs' => 'Documents Indexed in Database',
            'RemoteDocs' => 'Documents Indexed Remotely',
        ];
    }
}
