<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataQuery;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\GridField\SearchReindexFormAction;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Jobs\ReindexJob;
use SilverStripe\SearchService\Jobs\RemoveDataObjectJob;
use SilverStripe\SearchService\Services\AppSearch\AppSearchService;
use SilverStripe\SearchService\Tasks\SearchReindex;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

class SearchAdmin extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'search-service';

    private static $menu_title = 'Search Service';

    private static $menu_icon_class = 'font-icon-search';

    private static $required_permission_codes = 'CMS_ACCESS_SearchAdmin';

    /**
     * @param null $id
     * @param null $fields
     * @return Form
     * @throws IndexingServiceException
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

        $docsGrid = GridField::create('IndexedDocuments', 'Documents by Index', $this->buildIndexedDocumentsList());
        $docsGrid->getConfig()->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(5);
        $isAdmin = Permission::check('ADMIN');
        if ($isAdmin) {
            $docsGrid->getConfig()->addComponent(new SearchReindexFormAction());
        }

        $fields[] = $docsGrid;

        if ($isAdmin) {
            $fullReindexBaseURL = Director::absoluteURL("/dev/tasks/" . SearchReindex::config()->get('segment'));
            $fields[] = LiteralField::create(
                'ReindexAllURL',
                sprintf(
                    '<div style="padding-bottom: 30px; margin-top: -30px; position: relative;">
                    <a href="%s" target="_blank" style="
                        font-size: small;
                        background-color: #da273b;
                        color: white;
                        padding: 7px;
                        border-radius: 3px;"
                    >Trigger Full Reindex on All</a>
                </div>',
                    $fullReindexBaseURL
                )
            );
        }

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
     * @throws IndexingServiceException
     */
    private function buildIndexedDocumentsList(): ArrayList
    {
        $list = ArrayList::create();

        /** @var IndexingInterface $indexer */
        $indexer = Injector::inst()->get(IndexingInterface::class);

        $configuration = SearchServiceExtension::singleton()->getConfiguration();

        foreach ($configuration->getIndexes() as $index => $data) {
            $localCount = 0;
            foreach ($configuration->getClassesForIndex($index) as $class) {
                $query = new DataQuery($class);
                $query->where('SearchIndexed IS NOT NULL');
                if (property_exists($class, 'ShowInSearch')) {
                    $query->where('ShowInSearch = 1');
                }
                $this->extend('updateQuery', $query, $data);
                $localCount += $query->count();
            }

            $result = new IndexedDocumentsResult();
            $result->IndexName = AppSearchService::environmentizeIndex($index);
            $result->DBDocs = $localCount;
            $result->RemoteDocs = $indexer->getDocumentTotal($index);
            $list->push($result);
        }

        $this->extend('updateDocumentList', $list);

        return $list;
    }

    public function providePermissions(): array
    {
        $title = $this->menu_title();
        return [
            'CMS_ACCESS_SearchAdmin' => [
                'name' => _t(
                    'SilverStripe\\CMS\\Controllers\\CMSMain.ACCESS',
                    "Access to '{title}' section",
                    ['title' => $title]
                ),
                'category' => _t('SilverStripe\\Security\\Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => _t(
                    __CLASS__ . '.ACCESS_HELP',
                    'Allow viewing of search configuration and status, and links to external resources.'
                )
            ],
        ];
    }
}
