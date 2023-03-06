<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\View\ViewableData;

/**
 * @property string $IndexName
 * @property int $DBDocs
 * @property int $RemoteDocs
 */
class IndexedDocumentsResult extends ViewableData
{

    public function summaryFields(): array
    {
        return [
            'IndexName' => 'Index Name',
            'DBDocs' => 'Documents Indexed in Database',
            'RemoteDocs' => 'Documents Indexed Remotely',
        ];
    }

}
