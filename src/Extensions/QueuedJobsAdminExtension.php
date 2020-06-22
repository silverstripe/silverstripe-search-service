<?php


namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\Form;
use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Jobs\RemoveDataObjectJob;

class QueuedJobsAdminExtension extends Extension
{
    /**
     * Remove jobs from the list that don't make sense to create from the admin (and won't work)
     *
     * @param Form $form
     */
    public function updateEditForm(Form $form)
    {
        $field = $form->Fields()->dataFieldByName('JobType');
        /* @var DropdownField $field */
        if ($field) {
            $source = $field->getSource();
            unset($source[IndexJob::class]);
            unset($source[RemoveDataObjectJob::class]);
            $field->setSource($source);
        }
    }
}
