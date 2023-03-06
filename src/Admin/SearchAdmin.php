<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Controllers\CMSMain;
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
use SilverStripe\SearchService\Tasks\SearchReindex;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

class SearchAdmin extends LeftAndMain implements PermissionProvider
{

    private const PERMISSION_ACCESS = 'CMS_ACCESS_SearchAdmin';
    private const PERMISSION_REINDEX = 'SearchAdmin_ReIndex';

    private static string $url_segment = 'search-service';

    private static string $menu_title = 'Search Service';

    private static string $menu_icon_class = 'font-icon-search';

    private static string $required_permission_codes = self::PERMISSION_ACCESS;

    /**
     * @param null $id
     * @param null $fields
     * @throws IndexingServiceException
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);
        $canReindex = Permission::check(self::PERMISSION_REINDEX);

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

        $indexedDocumentsList = $this->buildIndexedDocumentsList();

        if (!$indexedDocumentsList->count() && !$indexedDocumentsList->dataClass()) {
            // No indexes have been configured

            // Indexed documents warning field
            $indexedDocumentsWarningField = LiteralField::create(
                'IndexedDocumentsWarning',
                '<div class="alert alert-warning">' .
                '<strong>No indexes found.</strong>' .
                'Indexes must be configured before indexed documents can be listed or re-indexed' .
                '</div>'
            );

            $fields[] = $indexedDocumentsWarningField;
        } else {
            // Indexed documents field
            $indexDocumentsField = GridField::create('IndexedDocuments', 'Documents by Index', $indexedDocumentsList);
            $indexDocumentsField->getConfig()->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(5);

            if ($canReindex) {
                $indexDocumentsField->getConfig()->addComponent(new SearchReindexFormAction());
            }

            $fields[] = $indexDocumentsField;

            // Reindex all URL field
            if ($canReindex) {
                $fullReindexBaseURL = Director::absoluteURL('/dev/tasks/' . SearchReindex::config()->get('segment'));
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
                ],
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
            $result->IndexName = $indexer->environmentizeIndex($index);
            $result->DBDocs = $localCount;
            $result->RemoteDocs = $indexer->getDocumentTotal($index);
            $list->push($result);
        }

        $this->extend('updateDocumentList', $list);

        return $list;
    }

    public function providePermissions(): array
    {
        return [
            self::PERMISSION_ACCESS => [
                'name' => _t(
                    CMSMain::class . '.ACCESS',
                    "Access to '{title}' section",
                    ['title' => $this->menu_title()]
                ),
                'category' => _t(Permission::class . '.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => _t(
                    self::class . '.ACCESS_HELP',
                    'Allow viewing of search configuration and status, and links to external resources.'
                ),
            ],
            self::PERMISSION_REINDEX => [
                'name' => _t(
                    self::class . '.ReIndexLabel',
                    'Trigger Full ReIndex'
                ),
                'category' => _t(
                    self::class . '.Category',
                    'Search Service'
                ),
            ],
        ];
    }

}
