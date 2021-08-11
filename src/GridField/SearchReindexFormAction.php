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
    public function getTitle($gridField, $record, $columnName)
    {
        return 'Trigger full reindex';
    }

    public function getCustomAction($gridField, $record)
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

    public function getExtraData($gridField, $record, $columnName)
    {
        $field = $this->getCustomAction($gridField, $record);

        if (!$field) {
            return;
        }

        return $field->getAttributes();
    }

    public function getGroup($gridField, $record, $columnName)
    {
        return GridField_ActionMenuItem::DEFAULT_GROUP;
    }

    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }
    }

    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    public function getColumnContent($gridField, $record, $columnName)
    {
        $field = $this->getCustomAction($gridField, $record);

        if (!$field) {
            return;
        }

        return $field->Field();
    }

    public function getActions($gridField)
    {
        return ['dofullreindex'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName !== 'dofullreindex') {
            return;
        }

        $fullReindexBaseURL = Director::absoluteURL("/dev/tasks/" . SearchReindex::config()->get('segment'));
        $fullIndexThisIndexURL = sprintf('%s?onlyIndex=%s', $fullReindexBaseURL, $arguments['IndexName']);
        Director::test($fullIndexThisIndexURL);

        Controller::curr()->getResponse()->setStatusCode(
            200,
            'Reindex triggered for '. $arguments['IndexName']
        );
    }
}
