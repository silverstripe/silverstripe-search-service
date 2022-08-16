<?php

namespace SilverStripe\SearchService\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\SearchService\Tasks\SearchReindex;

class SearchReindexFormAction implements GridField_ColumnProvider, GridField_ActionProvider, GridField_ActionMenuItem
{

    public function getTitle($gridField, $record, $columnName) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return 'Trigger full reindex';
    }

    public function getCustomAction($gridField, $record) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return GridField_FormAction::create(
            $gridField,
            'FullReindex' . $record->IndexName,
            'Trigger Full Reindex',
            'dofullreindex',
            ['IndexName' => $record->IndexName]
        )->addExtraClass(
            'action-menu--handled btn btn-info btn-sm'
        );
    }

    public function getExtraData($gridField, $record, $columnName) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $field = $this->getCustomAction($gridField, $record);

        if (!$field) {
            return null;
        }

        return $field->getAttributes();
    }

    public function getGroup($gridField, $record, $columnName) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return GridField_ActionMenuItem::DEFAULT_GROUP;
    }

    public function augmentColumns($gridField, &$columns) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    public function getColumnMetadata($gridField, $columnName) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }

        return null;
    }

    public function getColumnsHandled($gridField) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return ['Actions'];
    }

    public function getColumnContent($gridField, $record, $columnName) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $field = $this->getCustomAction($gridField, $record);

        if (!$field) {
            return null;
        }

        return $field->Field();
    }

    public function getActions($gridField) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return ['dofullreindex'];
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName !== 'dofullreindex') {
            return;
        }

        $fullReindexBaseURL = Director::absoluteURL('/dev/tasks/' . SearchReindex::config()->get('segment'));
        $fullIndexThisIndexURL = sprintf('%s?onlyIndex=%s', $fullReindexBaseURL, $arguments['IndexName']);
        Director::test($fullIndexThisIndexURL);

        Controller::curr()->getResponse()->setStatusCode(
            200,
            'Reindex triggered for '. $arguments['IndexName']
        );
    }

}
