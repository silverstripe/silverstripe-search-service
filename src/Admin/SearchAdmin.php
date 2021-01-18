<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Jobs\ReindexJob;
use SilverStripe\SearchService\Jobs\RemoveDataObjectJob;
use SilverStripe\SearchService\Services\AppSearch\AppSearchService;
use SilverStripe\Subsites\Model\Subsite;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

class SearchAdmin extends LeftAndMain
{
    private static $url_segment = 'search-service';

    private static $menu_title = 'Search Service';

    private static $menu_icon_class = 'font-icon-search';

    /**
     * @param null $id
     * @param null $fields
     * @return Form
     * @throws \SilverStripe\SearchService\Exception\IndexingServiceException
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);

        /** @var IndexingInterface $indexService */
        $indexService = Injector::inst()->get(IndexingInterface::class);
        $externalURL = $indexService->getExternalURL();
        $docsURL = $indexService->getDocumentationURL();

        $fields = [];
        if ($externalURL !== null || $docsURL !== null) {
            $fields[] = HeaderField::create('ExternalLinksHeader', 'External Links')
                ->setAttribute('style', 'font-weight: 300;');

            if ($externalURL !== null) {
                $fields[] = LiteralField::create(
                    'ExternalURL',
                    sprintf(
                        '<div><a href="%s" target="_blank" style="font-size: medium">%s</a></div>',
                        $externalURL,
                        $indexService->getExternalURLDescription() ?? 'External URL'
                    )
                );
            }

            if ($docsURL !== null) {
                $fields[] = LiteralField::create(
                    'DocsURL',
                    sprintf(
                        '<div><a href="%s" target="_blank" style="font-size: medium">Documentation URL</a></div>',
                        $docsURL
                    )
                );
            }

            $fields[] = LiteralField::create(
                'Divider',
                '<div class="clear" style="margin-top: 16px; height: 32px; border-top: 1px solid #ced5e1"></div>'
            );
        }

        /** @var GridField $docsGrid */
        $docsGrid = GridField::create('IndexedDocuments', 'Documents by Index', $this->buildIndexedDocumentsList());
        $docsGrid->getConfig()->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(5);

        $fields[] = $docsGrid;

        $fields[] = HeaderField::create('QueuedJobsHeader', 'Queued Jobs Status')
            ->setAttribute('style', 'font-weight: 300;');

        $rootQJQuery = QueuedJobDescriptor::get()
            ->filter([
                'Implementation' => [
                    ReindexJob::class,
                    IndexJob::class,
                    RemoveDataObjectJob::class,
                    ClearIndexJob::class,
                ]
            ]);

        $inProgressStatuses = [
            QueuedJob::STATUS_RUN,
            QueuedJob::STATUS_WAIT,
            QueuedJob::STATUS_INIT,
            QueuedJob::STATUS_NEW,
        ];

        $stoppedStatuses = [QueuedJob::STATUS_BROKEN, QueuedJob::STATUS_PAUSED];

        $fields[] = NumericField::create(
            'InProgressJobs',
            'In Progress',
            $rootQJQuery->filter(['JobStatus' => $inProgressStatuses])->count()
        )
        ->setReadonly(true)
        ->setRightTitle('i.e. status is one of: ' . implode(', ', $inProgressStatuses));

        $fields[] = NumericField::create(
            'StoppedJobs',
            'Stopped',
            $rootQJQuery->filter(['JobStatus' => $stoppedStatuses])->count()
        )
        ->setReadonly(true)
        ->setRightTitle('i.e. status is one of: ' . implode(', ', $stoppedStatuses));

        return $form->setFields(FieldList::create($fields));
    }

    /**
     * @return ArrayList
     * @throws \SilverStripe\SearchService\Exception\IndexingServiceException
     */
    private function buildIndexedDocumentsList(): ArrayList
    {
        $list = ArrayList::create();

        /** @var IndexingInterface $indexer */
        $indexer = Injector::inst()->get(IndexingInterface::class);

        $configuration = SearchServiceExtension::singleton()->getConfiguration();
        foreach ($configuration->getIndexes() as $index => $data) {
            $localCount = 0;
            $stashValue = null;
            $where = 'SearchIndexed IS NOT NULL';
            if (isset($data['subsite_id']) && is_numeric($data['subsite_id'])) {
                $stashValue = Subsite::$disable_subsite_filter;
                Subsite::disable_subsite_filter(true);
                $where .= " AND SubsiteID = {$data['subsite_id']}";
            }

            foreach ($configuration->getClassesForIndex($index) as $class) {
                $localCount += $class::get()->where($where)->count();
            }

            if ($stashValue !== null) {
                Subsite::disable_subsite_filter($stashValue);
            }

            $result = new IndexedDocumentsResult();
            $result->IndexName = AppSearchService::environmentizeIndex($index);
            $result->DBDocs = $localCount;
            $result->RemoteDocs = $indexer->getDocumentTotal($index);
            $list->push($result);
        }

        return $list;
    }
}
